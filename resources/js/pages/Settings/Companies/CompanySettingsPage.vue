<script setup lang="ts">
import { ref, onMounted, watchEffect } from 'vue'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import AddressAutocomplete from '@/components/Address/AddressAutocomplete.vue'
import MapSelector from '@/components/Address/MapSelector.vue'
import { useUiOverlayStore } from '@/stores/uiOverlay'
import { useAddressForm } from '@/composables/address'
import type { Company, User } from '@/types/models'
import { mergeAddressParts } from '@/utils/formatUtils'

type NotificationSetting = {
    key: string
    label: string
    enabled: boolean
    emails: string[]
}

const defaultNotificationSettings = (): NotificationSetting[] => ([
    { key: 'car_maintenance', label: 'Údržba áut', enabled: false, emails: [] },
])

function normalizeEmailList(emails: string[]) {
    return Array.from(
        new Set(
            emails
                .map((email) => email.trim())
                .filter((email) => email.length > 0)
        )
    )
}

function normalizeNotificationSettings(raw: unknown): NotificationSetting[] {
    if (!Array.isArray(raw)) {
        return defaultNotificationSettings()
    }

    const normalized = raw
        .map((item, index) => ({
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

const toast = useToast()
const uiOverlayStore = useUiOverlayStore()
const company = ref<Company & { representative?: User }>({} as any)
const loading = ref(true)
const saving = ref(false)
const submitted = ref(false)
const representativeOptions = ref<User[]>([])
const notificationSettings = ref<NotificationSetting[]>(defaultNotificationSettings())
const { addressQuery, init, onAutocompleteSelected, onMapClick, resolveBeforeSave } = useAddressForm(company)

const stampInputRef = ref<HTMLInputElement | null>(null)
const selectedStampFile = ref<File | null>(null)
const stampPreviewUrl = ref<string | null>(null)
const stampIsFromServer = ref(false)

const STAMP_REQUIRED_WIDTH = 300
const STAMP_REQUIRED_HEIGHT = 100
const STAMP_MAX_SIZE_MB = 5

init()

function removeNotificationSetting(index: number) {
    if (index === 0) {
        return
    }

    notificationSettings.value.splice(index, 1)

    if (!notificationSettings.value.length) {
        notificationSettings.value = defaultNotificationSettings()
    }
}

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value || saving.value)
})

onMounted(async () => {
    loading.value = true
    const auth = useAuthStore()

    try {
        const url = auth.isSuperadmin && router.currentRoute.value.params.companyId
            ? `v1/companies/${Number(router.currentRoute.value.params.companyId)}`
            : 'v1/my-company'

        const data = await api.fetchEntity<Company>(url)

        if (data) {
            company.value = data
            notificationSettings.value = cloneNotificationSettings(
                normalizeNotificationSettings((data as any).notification_settings)
            )

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
        }

        try {
            const repUrl = auth.isSuperadmin && router.currentRoute.value.params.companyId
                ? `v1/companies/${Number(router.currentRoute.value.params.companyId)}/users`
                : 'v1/my-company/users'

            representativeOptions.value = await api.fetchEntities<User>(repUrl)
        } catch (e) {
            console.error('Failed to fetch representative users', e)
        }
    } catch (e) {
        console.error('Failed to load company', e)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa načítať spoločnosť', life: 5000 })
    } finally {
        loading.value = false
    }
})

async function save() {
    submitted.value = true

    if (!company.value.id) return

    const auth = useAuthStore()

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
    } catch (err) {
        console.error('Address resolution before save failed', err)
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
            notificationSettings.value.some((setting) => Boolean(setting.enabled)) ? '1' : '0'
        )
        formData.append(
            'notification_settings',
            JSON.stringify(
                notificationSettings.value.map((setting) => ({
                    key: setting.key.trim(),
                    label: setting.label.trim(),
                    enabled: Boolean(setting.enabled),
                    emails: normalizeEmailList(setting.emails),
                }))
            )
        )

        if (selectedStampFile.value) {
            formData.append('stamp', selectedStampFile.value)
        }

        const saveUrl = auth.isSuperadmin && router.currentRoute.value.params.companyId
            ? `v1/companies/${company.value.id}?_method=PATCH`
            : 'v1/my-company?_method=PATCH'

        await api.post(saveUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Spoločnosť bola upravená', life: 3000 })
    } catch (e) {
        console.error('Failed to save company', e)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť spoločnosť', life: 5000 })
    } finally {
        saving.value = false
    }
}

async function clearSelectedStamp() {
    if (stampIsFromServer.value && company.value.id) {
        try {
            await api.delete(`v1/companies/${company.value.id}/stamp`)
            company.value.stamp_path = null
        } catch (e) {
            console.error('Failed to delete stamp', e)
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
    } catch (err) {
        console.error(err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa spracovať obrázok.', life: 5000 })
        clearSelectedStamp()
    }
}
</script>

<template>
    <div class="py-6 pb-24">
        <form @submit.prevent="save" class="flex flex-col gap-4">
            <div class="card">
                <TabView>
                    <TabPanel header="Firma">
                        <div class="flex flex-col gap-5">
                            <section class="bg-tag3 p-5 rounded-md">
                                <div class="mb-4">
                                    <h3 class="text-normal font-medium">Základné údaje</h3>
                                    <p class="text-sm text-gray-500">
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
                                    <h3 class="text-normal font-medium">Zodpovedná osoba</h3>
                                    <p class="text-sm text-gray-500">
                                        Osoba, ktorá je naviazaná na spoločnosť v systéme.
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
                                            <span>{{ option.first_name }} {{ option.last_name }}</span>
                                        </template>
                                        <template #value="{ value }">
                                            <span v-if="value">
                                                {{ representativeOptions.find((u) => u.id === value)?.first_name }}
                                                {{ representativeOptions.find((u) => u.id === value)?.last_name }}
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

                    <TabPanel header="Fakturácia">
                        <div class="flex flex-col gap-5">
                            <section class="bg-tag3 p-5 rounded-md">
                                <div class="mb-4">
                                    <h3 class="text-normal font-medium">Číslovanie faktúr</h3>
                                    <p class="text-sm text-gray-500">
                                        Nastavenie aktuálneho čísla pre ďalšiu faktúru.
                                    </p>
                                </div>

                                <div class="grid grid-cols-12 gap-4">
                                    <div class="col-span-12 md:col-span-6">
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

                            <section class="bg-tag3 p-5 rounded-md">
                                <div class="mb-4">
                                    <h3 class="text-normal font-medium">Bankové údaje</h3>
                                    <p class="text-sm text-gray-500">
                                        Údaje používané pri vystavovaní dokladov a platbách.
                                    </p>
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
                        </div>
                    </TabPanel>

                    <TabPanel header="Kontakt">
                        <div class="flex flex-col gap-5">
                            <section class="bg-tag3 p-5 rounded-md">
                                <div class="mb-4">
                                    <h3 class="text-normal font-medium">Kontaktné údaje</h3>
                                    <p class="text-sm text-gray-500">
                                        Základné kontaktné údaje spoločnosti.
                                    </p>
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
                                    <h3 class="text-normal font-medium">Adresa spoločnosti</h3>
                                    <p class="text-sm text-gray-500">
                                        Vyhľadajte a uložte adresu spoločnosti.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-normal mb-1">Adresa (ulica, mesto, PSČ)</label>
                                    <AddressAutocomplete v-model="addressQuery" @selected="onAutocompleteSelected" />
                                    <small v-if="submitted && !addressQuery" class="text-danger">
                                        Adresa je povinná.
                                    </small>
                                </div>
                            </section>

                            <section class="bg-tag3 p-5 rounded-md">
                                <div class="mb-4">
                                    <h3 class="text-normal font-medium">Poloha na mape</h3>
                                    <p class="text-sm text-gray-500">
                                        Presnú polohu nastavíte kliknutím do mapy.
                                    </p>
                                </div>

                                <MapSelector
                                    :latitude="company.latitude"
                                    :longitude="company.longitude"
                                    @update="onMapClick"
                                />
                            </section>
                        </div>
                    </TabPanel>

                    <TabPanel header="Upozornenia">
                        <div class="flex flex-col gap-5">
                            <section class="bg-tag3 p-5 rounded-md">
                                <div class="mb-4">
                                    <h3 class="text-normal font-medium">Nastavenie upozornení</h3>
                                    <p class="text-sm text-gray-500">
                                        Každý typ upozornenia nastavujete samostatne.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-4">
                                    <div
                                        v-for="(setting, index) in notificationSettings"
                                        :key="setting.key || index"
                                        class="rounded-md bg-white p-4 flex flex-col gap-4"
                                    >
                                        <div class="grid grid-cols-12 gap-4">
                                            <div class="col-span-12 md:col-span-4">
                                                <label class="block text-normal mb-1">Typ notifikácie</label>
                                                <InputText
                                                    v-model="setting.label"
                                                    class="w-full"
                                                    :readonly="index === 0"
                                                    :placeholder="index === 0 ? 'Údržba áut' : 'Napr. Nové faktúry'"
                                                />
                                                <small v-if="index === 0" class="text-gray-500 block mt-1">
                                                    Prvá notifikácia je pevne viazaná na údržbu áut.
                                                </small>
                                            </div>

                                            <div class="col-span-12 md:col-span-6">
                                                <label class="block text-normal mb-1">E-maily príjemcov</label>
                                                <Chips
                                                    v-model="setting.emails"
                                                    separator=","
                                                    addOnBlur
                                                    class="w-full"
                                                    inputClass="w-full!"
                                                />
                                                <small class="text-gray-500 block mt-1">
                                                    Email potvrďte Enterom alebo čiarkou.
                                                </small>
                                            </div>

                                            <div class="col-span-12 md:col-span-2 flex md:justify-end md:items-start">
                                                <div class="flex flex-col gap-2 w-full md:w-auto">
                                                    <label class="inline-flex items-center gap-2 text-sm text-darkgrey">
                                                        <InputSwitch v-model="setting.enabled" />
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
                                    </div>
                                </div>
                            </section>
                        </div>
                    </TabPanel>

                    <TabPanel header="Pečiatka">
                        <div class="flex flex-col gap-5">
                            <section class="bg-tag3 p-5 rounded-md">
                                <div class="mb-4">
                                    <h3 class="text-normal font-medium">Súbor pečiatky</h3>
                                    <p class="text-sm text-gray-500">
                                        Povolený je iba PNG s transparentným pozadím, max. 300 x 100 px.
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
                                        class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
                                        @click="stampInputRef?.click()"
                                    />
                                </div>

                                <div v-if="stampPreviewUrl" class="mt-3 flex flex-col gap-4">
                                    <div class="border rounded-md p-3 inline-block bg-white self-start">
                                        <img :src="stampPreviewUrl" alt="Preview pečiatky" class="max-h-32 object-contain" />
                                    </div>

                                    <div class="flex gap-3">
                                        <Button
                                            label="Nahrať novú"
                                            type="button"
                                            outlined
                                            @click="stampInputRef?.click()"
                                        />
                                        <Button
                                            label="Odstrániť"
                                            type="button"
                                            text
                                            severity="danger"
                                            @click="clearSelectedStamp"
                                        />
                                    </div>
                                </div>
                            </section>
                        </div>
                    </TabPanel>
                </TabView>
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