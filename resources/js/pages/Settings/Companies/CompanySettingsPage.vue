<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import CompanySettingsTabs from '@/components/Company/CompanySettingsTabs.vue'
import { useUiOverlayStore } from '@/stores/uiOverlay'
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
import type { Company, User } from '@/types/models'
import { mergeAddressParts } from '@/utils/formatUtils'

type CompanyTab =
    | 'firma'
    | 'fakturacia'
    | 'kontakt'
    | 'upozornenia'
    | 'peciatka'
    | 'lokality-navstev'

const toast = useToast()
const auth = useAuthStore()
const uiOverlayStore = useUiOverlayStore()

const company = ref<Company & { representative?: User }>({} as any)
const loading = ref(true)
const saving = ref(false)
const submitted = ref(false)

const representativeOptions = ref<User[]>([])
const notificationSettings = ref<NotificationSetting[]>(defaultNotificationSettings())
const visitLocations = ref<VisitLocation[]>([])
const visitLocationQuery = ref<string | null>(null)
const activeTab = ref<CompanyTab>('firma')

const createCompanyForm = ref({
    name: '',
    register: '',
    ico: '',
    dic: '',
})

const isCreateMode = computed(() => auth.isSuperadmin && !router.currentRoute.value.params.companyId)
const hasRepresentativeOptions = computed(() => representativeOptions.value.length > 0)

const { addressQuery, init, onAutocompleteSelected, onMapClick, resolveBeforeSave } = useAddressForm(company)

const selectedStampFile = ref<File | null>(null)
const stampPreviewUrl = ref<string | null>(null)
const stampIsFromServer = ref(false)

const STAMP_REQUIRED_WIDTH = 300
const STAMP_REQUIRED_HEIGHT = 100
const STAMP_MAX_SIZE_MB = 5

function appendField(formData: FormData, key: string, value: unknown) {
    formData.append(key, value === null || value === undefined ? '' : String(value))
}

function getCompanySaveUrl() {
    return auth.isSuperadmin && router.currentRoute.value.params.companyId
        ? `v1/companies/${company.value.id}?_method=PATCH`
        : 'v1/my-company?_method=PATCH'
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
        await clearSelectedStamp()
        return
    }

    if (file.size > STAMP_MAX_SIZE_MB * 1024 * 1024) {
        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: `Maximálna veľkosť súboru je ${STAMP_MAX_SIZE_MB} MB.`,
            life: 5000,
        })
        await clearSelectedStamp()
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
            await clearSelectedStamp()
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
        await clearSelectedStamp()
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

async function loadRepresentativeOptions() {
    try {
        const repUrl = auth.isSuperadmin && router.currentRoute.value.params.companyId
            ? `v1/companies/${Number(router.currentRoute.value.params.companyId)}/users`
            : 'v1/my-company/users'

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
    if (isCreateMode.value) {
        await createCompany()
        return true
    }

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

        appendField(formData, 'name', company.value.name ?? '')
        appendField(formData, 'register', company.value.register ?? '')
        appendField(formData, 'ico', company.value.ico ?? '')
        appendField(formData, 'dic', company.value.dic ?? '')
        appendField(formData, 'ic_dph', company.value.ic_dph ?? '')
        appendField(formData, 'iban', company.value.iban ?? '')
        appendField(formData, 'bic', company.value.bic ?? '')
        appendField(formData, 'phone', company.value.phone ?? '')
        appendField(formData, 'email', company.value.email ?? '')
        appendField(formData, 'invoice_number', company.value.invoice_number ?? 0)
        appendField(formData, 'address', company.value.address ?? '')
        appendField(formData, 'city', company.value.city ?? '')
        appendField(formData, 'psc', company.value.psc ?? '')
        appendField(
            formData,
            'representative_id',
            company.value.representative_id ? String(company.value.representative_id) : ''
        )
        appendField(formData, 'latitude', company.value.latitude ?? '')
        appendField(formData, 'longitude', company.value.longitude ?? '')
        appendField(
            formData,
            'send_notifications',
            notificationSettings.value.some((setting) => Boolean(setting.enabled)) ? '1' : '0',
        )
        appendField(
            formData,
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
        appendField(
            formData,
            'visit_locations',
            JSON.stringify(visitLocations.value.map((location) => normalizeVisitLocation(location))),
        )

        if (selectedStampFile.value) {
            formData.append('stamp', selectedStampFile.value)
        }

        await api.post(getCompanySaveUrl(), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        if (!silentSuccess) {
            toast.add({ severity: 'success', summary: 'Uložené', detail: 'Zmeny boli uložené', life: 3000 })
        }

        return true
    } catch (error) {
        console.error('Failed to save company', error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť zmeny', life: 5000 })
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

async function loadCompany() {
    try {
        const url = auth.isSuperadmin && router.currentRoute.value.params.companyId
            ? `v1/companies/${Number(router.currentRoute.value.params.companyId)}`
            : 'v1/my-company'

        const data = await api.fetchEntity<Company>(url)

        if (!data) return

        company.value = data as Company & { representative?: User }
        notificationSettings.value = cloneNotificationSettings(
            normalizeNotificationSettings((data as any).notification_settings)
        )
        visitLocations.value = normalizeVisitLocations((data as any).visit_locations)

        notificationSettings.value = notificationSettings.value.map((setting) => ({
            ...setting,
            emails: stripCompanyEmail(setting.emails, company.value.email ?? ''),
        }))

        addressQuery.value =
            mergeAddressParts(company.value.address, company.value.city, company.value.psc) || company.value.address

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

async function createCompany() {
    const name = createCompanyForm.value.name.trim()
    const register = createCompanyForm.value.register.trim()
    const ico = createCompanyForm.value.ico.trim()
    const dic = createCompanyForm.value.dic.trim()

    if (!name) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Názov spoločnosti je povinný', life: 5000 })
        return
    }

    saving.value = true

    try {
        const response = await api.post('v1/companies', {
            name,
            register: register || null,
            ico: ico || null,
            dic: dic || null,
        })

        const payload = response?.data?.data ?? response?.data ?? null
        const createdCompanyId = Number(payload?.id)

        if (!Number.isFinite(createdCompanyId) || createdCompanyId <= 0) {
            throw new Error('Missing created company id in response')
        }

        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Spoločnosť bola vytvorená', life: 3000 })
        await router.push({ name: 'superadmin-company-overview', params: { companyId: createdCompanyId } })
    } catch (error) {
        console.error('Failed to create company', error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa vytvoriť spoločnosť', life: 5000 })
    } finally {
        saving.value = false
    }
}

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value || saving.value)
})

watch(
    notificationSettings,
    (settings) => {
        settings.forEach((setting) => {
            const original = [...setting.emails]
            const cleaned = stripCompanyEmail(
                sanitizeEmailList(setting.emails),
                company.value.email ?? ''
            )

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
    { deep: true }
)

onMounted(async () => {
    init()

    if (isCreateMode.value) {
        loading.value = false
        return
    }

    loading.value = true

    try {
        await Promise.all([
            loadCompany(),
            loadRepresentativeOptions(),
        ])
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
    <div class="py-6 pb-24">
        <form @submit.prevent="save()" class="flex flex-col gap-4">
            <div v-if="isCreateMode" class="card">
                <section class="bg-tag3 p-5 rounded-md">
                    <div class="mb-4">
                        <h3 class="text-sm text-accent">Vytvorenie spoločnosti</h3>
                        <p class="text-sm text-tag2">
                            Najprv vytvorte spoločnosť. Ostatné údaje doplníte v prehľade spoločnosti po vytvorení.
                        </p>
                    </div>

                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-6">
                            <label class="block text-normal mb-1">Názov</label>
                            <InputText
                                v-model="createCompanyForm.name"
                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                            />
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="block text-normal mb-1">Zapísaná v registri</label>
                            <InputText
                                v-model="createCompanyForm.register"
                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                            />
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="block text-normal mb-1">IČO</label>
                            <InputText
                                v-model="createCompanyForm.ico"
                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                            />
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="block text-normal mb-1">DIČ</label>
                            <InputText
                                v-model="createCompanyForm.dic"
                                class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                            />
                        </div>
                    </div>
                </section>
            </div>

            <div v-else class="card">
                <CompanySettingsTabs
                    :active-tab="activeTab"
                    :company="company"
                    :representative-options="representativeOptions"
                    :address-query="addressQuery"
                    :notification-settings="notificationSettings"
                    :visit-locations="visitLocations"
                    :visit-location-query="visitLocationQuery"
                    :stamp-preview-url="stampPreviewUrl"
                    :show-users-tab="false"
                    :show-branches-tab="false"
                    :show-name-error="submitted && !company.name"
                    :show-register-error="submitted && !company.register"
                    :show-ico-error="submitted && !company.ico"
                    :show-dic-error="submitted && !company.dic"
                    :show-address-error="submitted && !addressQuery"
                    :show-representative-error="submitted && representativeOptions.length > 0 && !company.representative_id"
                    :before-tab-change="saveBeforeTabChange"
                    @update:active-tab="activeTab = $event as CompanyTab"
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
                    :label="saving ? (isCreateMode ? 'Vytváram...' : 'Ukladám...') : (isCreateMode ? 'Vytvoriť spoločnosť' : 'Uložiť')"
                    class="bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white min-w-32"
                />
            </div>
        </form>
    </div>
</template>