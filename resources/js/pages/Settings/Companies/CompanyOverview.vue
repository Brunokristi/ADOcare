<script setup lang="ts">
import { computed, markRaw, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'

import CompanySettingsTabs from '@/components/Company/CompanySettingsTabs.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { useAddressForm } from '@/composables/address'
import {
    cloneNotificationSettings,
    defaultNotificationSettings,
    normalizeNotificationSettings,
    normalizeVisitLocation,
    normalizeVisitLocations,
    sanitizeEmailList,
    stripCompanyEmail,
    withCompanyEmail,
    type NotificationSetting,
    type VisitLocation,
} from '@/composables/companySettingsShared'
import { useAuthStore } from '@/stores/auth'
import { useUiOverlayStore } from '@/stores/uiOverlay'
import useModal from '@/composables/useModal'
import api from '@/services/api'
import type { Branch, Company, User } from '@/types/models'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import { formatUserFullName, mergeAddressParts } from '@/utils/formatUtils'
import BranchForm from '../Branches/BranchForm.vue'
import UserForm from '../Users/UserForm.vue'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const uiOverlayStore = useUiOverlayStore()
const { openModal } = useModal()

const companyId = Number(route.params.companyId)
const company = ref<Company & { representative?: User }>({} as any)
const loading = ref(true)
const saving = ref(false)
const submitted = ref(false)
const representativeOptions = ref<User[]>([])
const notificationSettings = ref<NotificationSetting[]>(defaultNotificationSettings())
const visitLocations = ref<VisitLocation[]>([])
const visitLocationQuery = ref<string | null>(null)
const activeTab = ref<string>('firma')
const branchRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)
const userRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

const selectedStampFile = ref<File | null>(null)
const stampPreviewUrl = ref<string | null>(null)
const stampIsFromServer = ref(false)
const hasRepresentativeOptions = computed(() => representativeOptions.value.length > 0)

const STAMP_REQUIRED_WIDTH = 300
const STAMP_REQUIRED_HEIGHT = 100
const STAMP_MAX_SIZE_MB = 5

const { addressQuery, init, onAutocompleteSelected, onMapClick, resolveBeforeSave } = useAddressForm(company)

function removeNotificationSetting(index: number) {
    if (index === 0) {
        return
    }

    notificationSettings.value.splice(index, 1)

    if (!notificationSettings.value.length) {
        notificationSettings.value = defaultNotificationSettings()
    }
}

function addVisitLocation(place: {
    address: string
    street: string
    city: string
    zip: string
    latitude: number | null
    longitude: number | null
    place_id?: string
}) {
    const normalized = normalizeVisitLocation(place)

    if (!normalized.address && !normalized.street && !normalized.city) {
        return
    }

    const fingerprint = `${normalized.address}|${normalized.street}|${normalized.city}|${normalized.zip}|${normalized.latitude ?? ''}|${normalized.longitude ?? ''}`
    const alreadyAdded = visitLocations.value.some((location) => {
        const otherFingerprint = `${location.address}|${location.street}|${location.city}|${location.zip}|${location.latitude ?? ''}|${location.longitude ?? ''}`
        return otherFingerprint === fingerprint
    })

    if (alreadyAdded) {
        visitLocationQuery.value = null
        return
    }

    visitLocations.value.push(normalized)
    visitLocationQuery.value = null
}

function removeVisitLocation(index: number) {
    visitLocations.value.splice(index, 1)
}

async function openEditBranch(branchId: number) {
    const result = await openModal(BranchForm, { branchId, companyId }, { header: 'Upraviť pobočku', style: { width: '90%' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Pobočka bola upravená', life: 3000 })
        branchRemote.value?.reload()
    }
}

async function openCreateBranch() {
    const result = await openModal(BranchForm, { companyId }, { header: 'Pridať pobočku', style: { width: '90%' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Pobočka bola vytvorená', life: 3000 })
        branchRemote.value?.reload()
    }
}

async function syncRepresentativeAfterUserChange() {
    await loadRepresentativeOptions()

    const firstRepresentative = representativeOptions.value[0]

    if (!company.value.representative_id && firstRepresentative) {
        company.value.representative_id = firstRepresentative.id
    }
}

async function openEditUser(userId: number) {
    const result = await openModal(markRaw(UserForm), { userId, companyId }, { header: 'Upraviť používateľa', style: { width: '800px' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Používateľ bol uložený', life: 3000 })

        await Promise.all([
            userRemote.value?.reload(),
            syncRepresentativeAfterUserChange(),
        ])
    }
}

async function openCreateUser() {
    const result = await openModal(markRaw(UserForm), { companyId }, { header: 'Pridať používateľa', style: { width: '800px' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Používateľ bol vytvorený', life: 3000 })

        await Promise.all([
            userRemote.value?.reload(),
            syncRepresentativeAfterUserChange(),
        ])
    }
}

function loadImageDimensions(file: File): Promise<{ width: number; height: number }> {
    return new Promise((resolve, reject) => {
        const img = new Image()
        const url = URL.createObjectURL(file)

        img.onload = () => {
            const width = img.naturalWidth
            const height = img.naturalHeight
            URL.revokeObjectURL(url)
            resolve({ width, height })
        }

        img.onerror = () => {
            URL.revokeObjectURL(url)
            reject(new Error('Nepodarilo sa načítať obrázok'))
        }

        img.src = url
    })
}

async function handleStampSelected(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]

    if (!file) return

    if (file.type !== 'image/png') {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Je povolený iba PNG súbor.', life: 5000 })
        clearSelectedStamp()
        return
    }

    if (file.size > STAMP_MAX_SIZE_MB * 1024 * 1024) {
        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: `Maximálna veľkosť súboru je ${STAMP_MAX_SIZE_MB} MB.`,
            life: 5000,
        })
        clearSelectedStamp()
        return
    }

    try {
        const { width, height } = await loadImageDimensions(file)

        if (width > STAMP_REQUIRED_WIDTH || height > STAMP_REQUIRED_HEIGHT) {
            toast.add({
                severity: 'error',
                summary: 'Chyba',
                detail: `Obrázok môže mať maximálne ${STAMP_REQUIRED_WIDTH} x ${STAMP_REQUIRED_HEIGHT} px.`,
                life: 5000,
            })
            clearSelectedStamp()
            return
        }

        if (stampPreviewUrl.value) {
            URL.revokeObjectURL(stampPreviewUrl.value)
        }

        stampIsFromServer.value = false
        selectedStampFile.value = file
        stampPreviewUrl.value = URL.createObjectURL(file)

        input.value = ''
    } catch (error) {
        console.error(error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa spracovať obrázok.', life: 5000 })
        clearSelectedStamp()
    }
}

async function clearSelectedStamp() {
    if (stampIsFromServer.value && company.value.id) {
        try {
            await api.delete(`v1/companies/${company.value.id}/stamp`)
            company.value.stamp_path = null
        } catch (error) {
            console.error('Failed to delete stamp', error)
            toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa odstrániť pečiatku', life: 5000 })
            return
        }
    }

    if (stampPreviewUrl.value) {
        URL.revokeObjectURL(stampPreviewUrl.value)
    }

    stampIsFromServer.value = false
    selectedStampFile.value = null
    stampPreviewUrl.value = null
}

async function loadCompany() {
    try {
        const url = authStore.isSuperadmin && route.params.companyId ? `v1/companies/${companyId}` : 'v1/my-company'
        const data = await api.fetchEntity<Company>(url)

        if (!data) return

        company.value = data as Company & { representative?: User }
        notificationSettings.value = cloneNotificationSettings(normalizeNotificationSettings((data as any).notification_settings))
        visitLocations.value = normalizeVisitLocations((data as any).visit_locations)

        notificationSettings.value = notificationSettings.value.map((setting) => ({
            ...setting,
            emails: stripCompanyEmail(setting.emails, company.value.email ?? ''),
        }))

        addressQuery.value = mergeAddressParts(company.value.address, company.value.city, company.value.psc) || company.value.address

        if (data.stamp_path) {
            try {
                const blob = await api.get(`v1/companies/${data.id}/stamp`, { responseType: 'blob' })
                stampPreviewUrl.value = URL.createObjectURL(blob.data)
                stampIsFromServer.value = true
            } catch {
                // pečiatka na disku chýba, ignorujeme
            }
        }
    } catch (error) {
        console.error('Failed to load company', error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa načítať spoločnosť', life: 5000 })
    }
}

async function loadRepresentativeOptions() {
    try {
        const repUrl = authStore.isSuperadmin && route.params.companyId ? `v1/companies/${companyId}/users` : 'v1/my-company/users'
        representativeOptions.value = await api.fetchEntities<User>(repUrl)
    } catch (error) {
        console.error('Failed to fetch representative users', error)
    }
}

function isValidForSave(showErrors = true) {
    const representativeRequired = hasRepresentativeOptions.value

    if (
        !company.value.name ||
        !company.value.register ||
        !company.value.ico ||
        !company.value.dic ||
        !addressQuery.value ||
        (representativeRequired && !company.value.representative_id)
    ) {
        if (showErrors) {
            toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyplňte povinné údaje', life: 5000 })
        }
        return false
    }

    return true
}

async function save(options?: { silentSuccess?: boolean; markSubmitted?: boolean }): Promise<boolean> {
    const silentSuccess = options?.silentSuccess ?? false
    const markSubmitted = options?.markSubmitted ?? true

    if (markSubmitted) {
        submitted.value = true
    }

    if (!company.value.id) return false

    if (!isValidForSave(markSubmitted)) {
        return false
    }

    saving.value = true

    try {
        await resolveBeforeSave()
    } catch (error) {
        console.error('Address resolution before save failed', error)
    }

    try {
        const formData = new FormData()

        formData.append('name', company.value.name ?? '')
        formData.append('register', company.value.register ?? '')
        formData.append('ico', company.value.ico ?? '')
        formData.append('dic', company.value.dic ?? '')
        formData.append('ic_dph', company.value.ic_dph ?? '')
        formData.append('iban', company.value.iban ?? '')
        formData.append('bic', company.value.bic ?? '')
        formData.append('phone', company.value.phone ?? '')
        formData.append('email', company.value.email ?? '')
        formData.append('invoice_number', String(company.value.invoice_number ?? 0))
        formData.append('address', company.value.address ?? '')
        formData.append('city', company.value.city ?? '')
        formData.append('psc', company.value.psc ?? '')
        formData.append('representative_id', company.value.representative_id ? String(company.value.representative_id) : '')
        formData.append('latitude', String(company.value.latitude ?? ''))
        formData.append('longitude', String(company.value.longitude ?? ''))
        formData.append(
            'send_notifications',
            notificationSettings.value.some((setting) => Boolean(setting.enabled)) ? '1' : '0',
        )
        formData.append(
            'notification_settings',
            JSON.stringify(
                notificationSettings.value.map((setting) => ({
                    key: setting.key.trim(),
                    label: setting.label.trim(),
                    enabled: Boolean(setting.enabled),
                    emails: withCompanyEmail(setting.emails, company.value.email ?? ''),
                })),
            ),
        )
        formData.append(
            'visit_locations',
            JSON.stringify(visitLocations.value.map((location) => normalizeVisitLocation(location))),
        )

        if (selectedStampFile.value) {
            formData.append('stamp', selectedStampFile.value)
        }

        const saveUrl = authStore.isSuperadmin && route.params.companyId
            ? `v1/companies/${company.value.id}?_method=PATCH`
            : 'v1/my-company?_method=PATCH'

        await api.post(saveUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        if (!silentSuccess) {
            toast.add({ severity: 'success', summary: 'Uložené', detail: 'Spoločnosť bola upravená', life: 3000 })
        }

        return true
    } catch (error) {
        console.error('Failed to save company', error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť spoločnosť', life: 5000 })
        return false
    } finally {
        saving.value = false
    }
}

async function saveBeforeTabChange(currentTab: string, nextTab: string): Promise<boolean> {
    if (currentTab === nextTab) {
        return true
    }

    return await save({
        silentSuccess: true,
        markSubmitted: true,
    })
}

const branchOptions = computed<DataTableOptions<Branch>>(() => ({
    rowKey: 'id',
    endpointUrl: `v1/companies/${companyId}/branches`,
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    extraParams: { with: 'representative', count: 'users' },
    afterInit: ({ remote }) => {
        branchRemote.value = remote
    },
    columns: [
        { field: 'address', header: 'Adresa', sortable: false, render: (_v, row: Branch) => `${row.address || ''} ${row.city ? ', ' + row.city : ''}` },
        { field: 'city', header: 'Mesto', sortable: true },
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'representative', header: 'Obozorný zástupca', sortable: false, render: (v: User) => v ? formatUserFullName(v) : '' },
        { field: 'users_count', header: 'Počet zamestnancov', sortable: true },
        {
            field: 'edit',
            header: '',
            width: '3rem',
            component: ActionButtons,
            componentOptions: [
                { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: Branch) => openEditBranch(row.id) }
            ]
        }
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            confirm: 'Zmazať vybrané pobočky?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/branches', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            }
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => { await openCreateBranch() }
        }
    ]
}))

const userOptions = computed<DataTableOptions<User>>(() => ({
    rowKey: 'id',
    endpointUrl: `v1/companies/${companyId}/users`,
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    extraParams: { company_id: companyId, with: 'role,branches' },
    afterInit: ({ remote }) => { userRemote.value = remote },
    columns: [
        { field: 'first_name', header: 'Meno', sortable: true },
        { field: 'last_name', header: 'Priezvisko', sortable: true },
        { field: 'title', header: 'Titul', sortable: false },
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'phone_number', header: 'Telefón', sortable: false },
        { field: 'email', header: 'Email', sortable: false },
        {
            field: 'edit',
            header: '',
            width: '3rem',
            component: ActionButtons,
            componentOptions: [
                { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: User) => openEditUser(row.id) }
            ]
        }
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            confirm: 'Naozaj vymazať vybraných používateľov?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/users', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            }
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => { await openCreateUser() }
        }
    ]
}))

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value || saving.value)
})

watch(
    notificationSettings,
    (settings) => {
        settings.forEach((setting) => {
            const original = [...setting.emails]
            const cleaned = stripCompanyEmail(sanitizeEmailList(setting.emails), company.value.email ?? '')

            const removedCount = original.length - cleaned.length
            const changed =
                cleaned.length !== original.length ||
                cleaned.some((email, index) => email !== original[index])

            if (changed) {
                setting.emails = cleaned

                if (removedCount > 0) {
                    toast.add({
                        severity: 'warn',
                        summary: 'Neplatný email',
                        detail: 'Neplatná emailová adresa bola odstránená.',
                        life: 3000,
                    })
                }
            }
        })
    },
    { deep: true },
)

onMounted(async () => {
    loading.value = true

    try {
        init()
        await Promise.all([loadCompany(), loadRepresentativeOptions()])
    } finally {
        loading.value = false
    }
})

onBeforeUnmount(() => {
    if (stampPreviewUrl.value) {
        URL.revokeObjectURL(stampPreviewUrl.value)
    }
})
</script>

<template>
    <div class="p-4">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="text-heading-accent text-xl">
                <span>{{ company.name }}</span>
            </div>
        </div>

        <form @submit.prevent="save()" class="flex flex-col gap-4">
            <div class="card">
                <CompanySettingsTabs
                    :active-tab="activeTab"
                    :company="company"
                    :representative-options="representativeOptions"
                    :address-query="addressQuery"
                    :notification-settings="notificationSettings"
                    :visit-locations="visitLocations"
                    :visit-location-query="visitLocationQuery"
                    :stamp-preview-url="stampPreviewUrl"
                    :disable-map="authStore.currentRole === 'manager'"
                    :show-users-tab="true"
                    :show-branches-tab="true"
                    :user-options="userOptions"
                    :branch-options="branchOptions"
                    :show-name-error="submitted && !company.name"
                    :show-register-error="submitted && !company.register"
                    :show-ico-error="submitted && !company.ico"
                    :show-dic-error="submitted && !company.dic"
                    :show-address-error="submitted && !addressQuery"
                    :show-representative-error="submitted && representativeOptions.length > 0 && !company.representative_id"
                    :before-tab-change="saveBeforeTabChange"
                    @update:active-tab="activeTab = $event"
                    @update:address-query="addressQuery = $event"
                    @update:visit-location-query="visitLocationQuery = $event"
                    @address-selected="onAutocompleteSelected"
                    @map-update="onMapClick"
                    @remove-notification-setting="removeNotificationSetting"
                    @visit-location-selected="addVisitLocation"
                    @remove-visit-location="removeVisitLocation"
                    @stamp-selected="handleStampSelected"
                    @clear-stamp="clearSelectedStamp"
                />
            </div>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="saving || loading"
                    :label="saving ? 'Ukladám...' : 'Uložiť'"
                    class="bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white min-w-32"
                />
            </div>
        </form>
    </div>
</template>