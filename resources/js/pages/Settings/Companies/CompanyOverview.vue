<script setup lang="ts">
import { computed, markRaw, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Chip from 'primevue/chip'
import Button from 'primevue/button'

import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import AddressAutocomplete from '@/components/Address/AddressAutocomplete.vue'
import MapSelector from '@/components/Address/MapSelector.vue'
import { useAddressForm } from '@/composables/address'
import { useAuthStore } from '@/stores/auth'
import { useUiOverlayStore } from '@/stores/uiOverlay'
import useModal from '@/composables/useModal'
import api from '@/services/api'
import type { Branch, Company, User } from '@/types/models'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import { formatUserFullName, mergeAddressParts } from '@/utils/formatUtils'
import BranchForm from '../Branches/BranchForm.vue'
import UserForm from '../Users/UserForm.vue'

type VisitLocation = {
    address: string
    street: string
    city: string
    zip: string
    latitude: number | null
    longitude: number | null
    place_id?: string
}

type NotificationSetting = {
    key: string
    label: string
    enabled: boolean
    emails: string[]
}

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

const stampInputRef = ref<HTMLInputElement | null>(null)
const selectedStampFile = ref<File | null>(null)
const stampPreviewUrl = ref<string | null>(null)
const stampIsFromServer = ref(false)

const STAMP_REQUIRED_WIDTH = 300
const STAMP_REQUIRED_HEIGHT = 100
const STAMP_MAX_SIZE_MB = 5
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const { addressQuery, init, onAutocompleteSelected, onMapClick, resolveBeforeSave } = useAddressForm(company)

function defaultNotificationSettings(): NotificationSetting[] {
    return [{ key: 'car_maintenance', label: 'Údržba áut', enabled: false, emails: [] }]
}

function isValidEmail(email: string) {
    return EMAIL_REGEX.test(email.trim())
}

function sanitizeEmailList(emails: string[]) {
    return Array.from(
        new Set(
            emails
                .map((email) => email.trim().toLowerCase())
                .filter((email) => email.length > 0)
                .filter((email) => isValidEmail(email)),
        ),
    )
}

function normalizeEmailList(emails: string[]) {
    return sanitizeEmailList(emails)
}

function stripCompanyEmail(emails: string[], companyEmail: string) {
    const requiredEmail = companyEmail.trim()

    if (!requiredEmail) {
        return normalizeEmailList(emails)
    }

    return normalizeEmailList(emails).filter((email) => email !== requiredEmail)
}

function withCompanyEmail(emails: string[], companyEmail: string) {
    const normalized = stripCompanyEmail(emails, companyEmail)
    const requiredEmail = companyEmail.trim()

    if (requiredEmail) {
        normalized.unshift(requiredEmail)
    }

    return normalized
}

function normalizeNotificationSettings(raw: unknown): NotificationSetting[] {
    if (!Array.isArray(raw)) {
        return defaultNotificationSettings()
    }

    const normalized = raw
        .map((item: any, index: number) => ({
            key: typeof item?.key === 'string' && item.key.trim() ? item.key.trim() : `notification_${index + 1}`,
            label: typeof item?.label === 'string' ? item.label.trim() : '',
            enabled: typeof item?.enabled === 'boolean' ? item.enabled : true,
            emails: Array.isArray(item?.emails) ? normalizeEmailList(item.emails) : [],
        }))
        .filter((item) => item.label.length > 0 || item.emails.length > 0)

    return normalized.length > 0 ? normalized : defaultNotificationSettings()
}

function cloneNotificationSettings(settings: NotificationSetting[]) {
    return settings.map((setting) => ({
        key: setting.key,
        label: setting.label,
        enabled: setting.enabled,
        emails: [...setting.emails],
    }))
}

function normalizeVisitLocation(raw: any): VisitLocation {
    return {
        address: typeof raw?.address === 'string' ? raw.address.trim() : '',
        street: typeof raw?.street === 'string' ? raw.street.trim() : '',
        city: typeof raw?.city === 'string' ? raw.city.trim() : '',
        zip: typeof raw?.zip === 'string' ? raw.zip.trim() : '',
        latitude: typeof raw?.latitude === 'number' ? raw.latitude : null,
        longitude: typeof raw?.longitude === 'number' ? raw.longitude : null,
        place_id: typeof raw?.place_id === 'string' ? raw.place_id.trim() : undefined,
    }
}

function normalizeVisitLocations(raw: unknown): VisitLocation[] {
    if (!Array.isArray(raw)) {
        return []
    }

    return raw
        .map((item) => normalizeVisitLocation(item))
        .filter((item) => item.address.length > 0 || item.street.length > 0 || item.city.length > 0)
}

function formatVisitLocation(location: VisitLocation) {
    return location.address || [location.street, location.city, location.zip].filter(Boolean).join(', ')
}

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

async function openEditUser(userId: number) {
    const result = await openModal(markRaw(UserForm), { userId, companyId }, { header: 'Upraviť používateľa', style: { width: '800px' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Používateľ bol uložený', life: 3000 })
        userRemote.value?.reload()
    }
}

async function openCreateUser() {
    const result = await openModal(markRaw(UserForm), { companyId }, { header: 'Pridať používateľa', style: { width: '800px' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Používateľ bol vytvorený', life: 3000 })
        userRemote.value?.reload()
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

        if (stampInputRef.value) {
            stampInputRef.value.value = ''
        }
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

    if (stampInputRef.value) {
        stampInputRef.value.value = ''
    }
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

async function save() {
    submitted.value = true

    if (!company.value.id) return

    if (
        !company.value.name ||
        !company.value.register ||
        !company.value.ico ||
        !company.value.dic ||
        !addressQuery.value ||
        !company.value.representative_id
    ) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyplňte povinné údaje', life: 5000 })
        return
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
        formData.append('representative_id', String(company.value.representative_id ?? ''))
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

        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Spoločnosť bola upravená', life: 3000 })
    } catch (error) {
        console.error('Failed to save company', error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť spoločnosť', life: 5000 })
    } finally {
        saving.value = false
    }
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
            field: 'edit', header: '', width: '3rem', component: ActionButtons, componentOptions: [
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
            field: 'edit', header: '', width: '3rem', component: ActionButtons, componentOptions: [
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

        <form @submit.prevent="save" class="flex flex-col gap-4">
            <div class="card">
                <Tabs v-model:value="activeTab">
                    <TabList>
                        <Tab value="branches">Pobočky</Tab>
                        <Tab value="users">Používatelia</Tab>
                        <Tab value="firma">Základné údaje</Tab>
                        <Tab value="fakturacia">Fakturácia</Tab>
                        <Tab value="kontakt">Kontakt</Tab>
                        <Tab value="upozornenia">Upozornenia</Tab>
                        <Tab value="peciatka">Vizuálne prvky</Tab>
                        <Tab value="lokality-navstev">Adresár</Tab>
                        
                    </TabList>

                    <TabPanels>
                        <TabPanel value="branches">
                            <div class="flex flex-col gap-5">
                                <section class="bg-tag3 p-5 rounded-md">
                                    <UniversalDataTable :options="branchOptions" />
                                </section>
                            </div>
                        </TabPanel>

                        <TabPanel value="users">
                            <div class="flex flex-col gap-5">
                                <section class="bg-tag3 p-5 rounded-md">
                                    <UniversalDataTable :options="userOptions" />
                                </section>
                            </div>
                        </TabPanel>

                        <TabPanel value="firma">
                            <div class="flex flex-col gap-5">
                                <section class="bg-tag3 p-5 rounded-md">
                                    <div class="mb-4">
                                        <p class="text-sm text-accent">
                                            Hlavné identifikačné údaje spoločnosti.
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-12 gap-4">
                                        <div class="col-span-12 md:col-span-6">
                                            <label class="block text-normal mb-1">Názov</label>
                                            <InputText
                                                v-model="company.name"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                            <small v-if="submitted && !company.name" class="text-danger">
                                                Názov je povinný.
                                            </small>
                                        </div>

                                        <div class="col-span-12 md:col-span-6">
                                            <label class="block text-normal mb-1">Zapísaná v registri</label>
                                            <InputText
                                                v-model="company.register"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                            <small v-if="submitted && !company.register" class="text-danger">
                                                Zapísaná v registri je povinná.
                                            </small>
                                        </div>

                                        <div class="col-span-12 md:col-span-4">
                                            <label class="block text-normal mb-1">IČO</label>
                                            <InputText
                                                v-model="company.ico"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                            <small v-if="submitted && !company.ico" class="text-danger">
                                                IČO je povinné.
                                            </small>
                                        </div>

                                        <div class="col-span-12 md:col-span-4">
                                            <label class="block text-normal mb-1">DIČ</label>
                                            <InputText
                                                v-model="company.dic"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                            <small v-if="submitted && !company.dic" class="text-danger">
                                                DIČ je povinné.
                                            </small>
                                        </div>

                                        <div class="col-span-12 md:col-span-4">
                                            <label class="block text-normal mb-1">IČ DPH</label>
                                            <InputText
                                                v-model="company.ic_dph"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                        </div>
                                    </div>
                                </section>

                                <section class="bg-tag3 p-5 rounded-md">
                                    <div class="mb-4">
                                        <h3 class="text-sm text-accent">Zodpovedná osoba</h3>
                                        <p class="text-sm text-tag2">
                                            Osoba sa zobrazuje na vybraných dokumentoch. Môže to byť napríklad majiteľ firmy alebo iný zodpovedný pracovník.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-normal mb-1">Zodpovedná osoba</label>
                                        <Select
                                            v-model="company.representative_id"
                                            :options="representativeOptions"
                                            optionLabel="first_name"
                                            optionValue="id"
                                            class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                        >
                                            <template #option="{ option }">
                                                <span>{{ formatUserFullName(option) }}</span>
                                            </template>
                                            <template #value="{ value }">
                                                <span v-if="value">
                                                    {{ formatUserFullName(representativeOptions.find((u) => u.id === value) as User) }}
                                                </span>
                                            </template>
                                        </Select>
                                        <small v-if="submitted && !company.representative_id" class="text-danger">
                                            Zodpovedná osoba je povinná.
                                        </small>
                                    </div>
                                </section>
                            </div>
                        </TabPanel>

                        <TabPanel value="fakturacia">
                            <div class="flex flex-col gap-5">
                                <section class="bg-tag3 p-5 rounded-md">
                                    <div class="mb-4">
                                        <h3 class="text-sm text-accent">Bankové údaje</h3>
                                    </div>

                                    <div class="grid grid-cols-12 gap-4">
                                        <div class="col-span-12 md:col-span-6">
                                            <label class="block text-normal mb-1">IBAN</label>
                                            <InputText
                                                v-model="company.iban"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                        </div>

                                        <div class="col-span-12 md:col-span-6">
                                            <label class="block text-normal mb-1">BIC</label>
                                            <InputText
                                                v-model="company.bic"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                        </div>
                                    </div>
                                </section>

                                <section class="bg-tag3 p-5 rounded-md">
                                    <div class="mb-4">
                                        <h3 class="text-sm text-accent">Číslovanie faktúr</h3>
                                    </div>

                                    <div class="grid grid-cols-12 gap-4">
                                        <div class="col-span-12">
                                            <label class="block text-normal mb-1">Aktuálne číslo faktúry</label>
                                            <InputNumber
                                                v-model="company.invoice_number"
                                                :min="0"
                                                :useGrouping="false"
                                                class="w-full"
                                                inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </TabPanel>

                        <TabPanel value="kontakt">
                            <div class="flex flex-col gap-5">
                                <section class="bg-tag3 p-5 rounded-md">
                                    <div class="mb-4">
                                        <h3 class="text-sm text-accent">Kontaktné údaje</h3>
                                    </div>

                                    <div class="grid grid-cols-12 gap-4">
                                        <div class="col-span-12 md:col-span-6">
                                            <label class="block text-normal mb-1">Telefón</label>
                                            <InputText
                                                v-model="company.phone"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                        </div>

                                        <div class="col-span-12 md:col-span-6">
                                            <label class="block text-normal mb-1">Email</label>
                                            <InputText
                                                v-model="company.email"
                                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                                            />
                                        </div>
                                    </div>
                                </section>

                                <section class="bg-tag3 p-5 rounded-md">
                                    <div class="mb-4">
                                        <h3 class="text-sm text-accent">Adresa spoločnosti</h3>
                                    </div>

                                    <div class="mb-4">
                                        <label class="block text-normal mb-1">Adresa</label>
                                        <AddressAutocomplete
                                            v-model="addressQuery"
                                            @selected="onAutocompleteSelected"
                                            class="w-full border-0!"
                                            inputClass="border-0! shadow-none! outline-none! focus:ring-0! focus:shadow-none!"
                                        />
                                        <small v-if="submitted && !addressQuery" class="text-danger">
                                            Adresa je povinná.
                                        </small>
                                    </div>

                                    <MapSelector
                                        :latitude="company.latitude"
                                        :longitude="company.longitude"
                                        :disabled="authStore.currentRole === 'manager'"
                                        @update="onMapClick"
                                    />
                                </section>
                            </div>
                        </TabPanel>

                        <TabPanel value="upozornenia">
                            <div class="flex flex-col gap-5">
                                <section class="bg-tag3 p-5 rounded-md">
                                    <div class="mb-4">
                                        <h3 class="text-sm text-accent">Nastavenie upozornení</h3>
                                        <p class="text-sm text-tag2">
                                            Dostávajte upozorenia na dôležité udalosti. Aktivujte iba upozornenia, ktoré sú pre vás dôležité.
                                        </p>
                                    </div>

                                    <div class="flex flex-col gap-4">
                                        <div
                                            v-for="(setting, index) in notificationSettings"
                                            :key="setting.key || index"
                                            class="rounded-md bg-white p-4 flex flex-col gap-4"
                                        >
                                            <div class="grid grid-cols-12 gap-4">
                                                <div class="col-span-12 md:col-span-10">
                                                    <h2 class="block text-heading mb-1">{{ setting.label }}</h2>
                                                </div>

                                                <div class="col-span-12 md:col-span-2 flex md:justify-end md:items-start">
                                                    <div class="flex flex-col gap-2 w-full md:w-auto">
                                                        <label class="inline-flex items-center gap-2 text-sm text-darkgrey">
                                                            <ToggleSwitch v-model="setting.enabled" />
                                                            {{ setting.enabled ? 'Zapnuté' : 'Vypnuté' }}
                                                        </label>

                                                        <Button
                                                            v-if="index > 0"
                                                            type="button"
                                                            label="Odstrániť"
                                                            text
                                                            severity="danger"
                                                            class="justify-start md:justify-center"
                                                            @click="removeNotificationSetting(index)"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-span-12 md:col-span-6">
                                                <label class="block text-normal mb-1">E-maily príjemcov</label>
                                                <div v-if="company.email" class="mb-2 flex flex-wrap gap-2">
                                                    <Chip :label="company.email" />
                                                </div>
                                                <Chips
                                                    v-model="setting.emails"
                                                    separator="," 
                                                    addOnBlur
                                                    class="w-full"
                                                    inputClass="w-full! border-0! outline-none! shadow-none! focus:ring-0! focus:shadow-none!"
                                                />
                                                <small class="text-mini text-tag2 block mt-1">
                                                    Email potvrďte stlačením tlačidla enter alebo čiarkou.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </TabPanel>

                        <TabPanel value="peciatka">
                            <div class="flex flex-col gap-5">
                                <section class="bg-tag3 rounded-md p-5">
                                    <div class="mb-4">
                                        <h3 class="text-normal font-medium">Vizuálne prvky spoločnosti</h3>
                                        <p class="text-sm text-tag2">
                                            Grafické prvky spoločnosti, ktoré sa zobrazujú na dokumentoch.
                                        </p>
                                    </div>

                                    <div class="flex flex-col gap-4">
                                        <div class="flex flex-col gap-4 rounded-md bg-white p-4">
                                            <div>
                                                <h2 class="block text-heading">Pečiatka spoločnosti</h2>
                                                <p class="text-sm text-tag2">
                                                    Nahrajte obrázok pečiatky vo formáte PNG. Maximálne rozmery sú 300x100 px.
                                                </p>
                                            </div>

                                            <input
                                                ref="stampInputRef"
                                                type="file"
                                                accept="image/png"
                                                class="hidden"
                                                @change="handleStampSelected"
                                            />

                                            <div v-if="!stampPreviewUrl" class="flex items-center gap-3">
                                                <Button
                                                    label="Nahrať"
                                                    type="button"
                                                    class="bg-accent! border-accent! px-2! text-white! hover:bg-darkgrey! hover:border-darkgrey!"
                                                    @click="stampInputRef?.click()"
                                                />
                                            </div>

                                            <div v-else class="mt-3">
                                                <div class="relative inline-block overflow-visible rounded-md border bg-white p-3 group">
                                                    <img
                                                        :src="stampPreviewUrl"
                                                        alt="Preview pečiatky"
                                                        class="block max-h-32 object-contain"
                                                    />

                                                    <button
                                                        type="button"
                                                        class="absolute z-20 flex h-7 w-7 items-center justify-center rounded-md bg-danger text-white cursor-pointer opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                                                        style="top: 0.2rem; right: 0.2rem;"
                                                        @click="clearSelectedStamp"
                                                    >
                                                        <i class="bi bi-eraser"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </TabPanel>

                        <TabPanel value="lokality-navstev">
                            <div class="flex flex-col gap-5">
                                <section class="bg-tag3 p-5 rounded-md">
                                    <div class="mb-4">
                                        <h3 class="text-sm text-accent">Často navštevované lokality</h3>
                                        <p class="text-sm text-tag2">
                                            Pridajte lokality, ktoré manažér často navštevuje. Tieto lokality budú slúžiť k vytvoreniu denného záznamu ciest manažéra.
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-12 gap-4">
                                        <div class="col-span-12">
                                            <label class="block text-normal mb-1">Vyhľadajte lokalitu</label>
                                            <AddressAutocomplete
                                                v-model="visitLocationQuery"
                                                class="w-full"
                                                inputClass="border-0! outline-none! shadow-none! focus:ring-0! focus:shadow-none!"
                                                @selected="addVisitLocation"
                                            />
                                        </div>
                                    </div>

                                    <div class="mt-5">
                                        <div v-if="visitLocations.length" class="flex flex-wrap gap-2">
                                            <Chip
                                                v-for="(location, index) in visitLocations"
                                                :key="`${location.address}-${location.latitude ?? 'na'}-${location.longitude ?? 'na'}-${index}`"
                                                removable
                                                :label="formatVisitLocation(location)"
                                                class="max-w-full"
                                                @remove="removeVisitLocation(index)"
                                            />
                                        </div>

                                        <div v-else class="text-sm text-tag2">
                                            Zatiaľ nie sú pridané žiadne lokality.
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
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
