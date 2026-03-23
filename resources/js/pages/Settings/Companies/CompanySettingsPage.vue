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

const toast = useToast()
const uiOverlayStore = useUiOverlayStore()
const company = ref<Company & { representative?: User }>({} as any)
const loading = ref(true)
const saving = ref(false)
const submitted = ref(false)
const representativeOptions = ref<User[]>([])
const { addressQuery, init, onAutocompleteSelected, onMapClick, resolveBeforeSave } = useAddressForm(company)

const stampInputRef = ref<HTMLInputElement | null>(null)
const selectedStampFile = ref<File | null>(null)
const stampPreviewUrl = ref<string | null>(null)
const stampIsFromServer = ref(false)

const STAMP_REQUIRED_WIDTH = 300
const STAMP_REQUIRED_HEIGHT = 100
const STAMP_MAX_SIZE_MB = 5

init()

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
            addressQuery.value = mergeAddressParts(company.value.address, company.value.city, company.value.psc) || company.value.address
            if (data.stamp_path) {
                try {
                    const blob = await api.get(`v1/companies/${data.id}/stamp`, { responseType: 'blob' })
                    stampPreviewUrl.value = URL.createObjectURL(blob.data)
                    stampIsFromServer.value = true
                } catch {
                    // stamp missing on disk, ignore
                }
            }
        }
        // fetch company users for representative selection
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

    // MIME/type check
    if (file.type !== 'image/png') {
        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Je povolený iba PNG súbor.',
            life: 5000,
        })
        clearSelectedStamp()
        return
    }

    // file size check
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
                detail: `Obrázok môže mať maximálne ${STAMP_REQUIRED_WIDTH} × ${STAMP_REQUIRED_HEIGHT} px.`,
                life: 5000,
            })
            clearSelectedStamp()
            return
        }

        // clear local state only (no server call — user is replacing an existing stamp, which will be overwritten on save)
        if (stampPreviewUrl.value) URL.revokeObjectURL(stampPreviewUrl.value)
        stampIsFromServer.value = false
        selectedStampFile.value = file
        stampPreviewUrl.value = URL.createObjectURL(file)
        if (stampInputRef.value) stampInputRef.value.value = ''
    } catch (err) {
        console.error(err)
        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Nepodarilo sa spracovať obrázok.',
            life: 5000,
        })
        clearSelectedStamp()
    }
}

</script>

<template>
    <div class="py-8">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <div class="card mb-4">
                    <h3 class="text-accent text-normal mb-2">Všeobecné informácie</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Názov</label>
                            <InputText v-model="company.name" class="w-full" />
                            <small v-if="submitted && !company.name" class="text-danger">
                                Názov je povinný.
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Zapísaná v registri</label>
                            <InputText v-model="company.register" class="w-full" />
                            <small v-if="submitted && !company.register" class="text-danger">
                                Zapísaná v registri je povinná.
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">IČO</label>
                            <InputText v-model="company.ico" class="w-full" />
                            <small v-if="submitted && !company.ico" class="text-danger">
                                IČO je povinné.
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">DIČ</label>
                            <InputText v-model="company.dic" class="w-full" />
                            <small v-if="submitted && !company.dic" class="text-danger">
                                DIČ je povinné.
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">IČ DPH</label>
                            <InputText v-model="company.ic_dph" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">IBAN</label>
                            <InputText v-model="company.iban" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">BIČ</label>
                            <InputText v-model="company.bic" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Číselný rad faktúr (Aktuálne číslo FA)</label>
                            <InputNumber
                                v-model="company.invoice_number"
                                :min="0"
                                :useGrouping="false"
                                class="w-full"
                                inputClass="w-full"
                            />
                        </div>

                    </div>
                </div>

                <div class="card mb-4">
                    <h3 class="text-accent text-normal mb-2">Adresa</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm mb-1">Adresa (ulica, mesto, PSČ)</label>
                            <AddressAutocomplete v-model="addressQuery" @selected="onAutocompleteSelected" />
                            <small v-if="submitted && !addressQuery" class="text-danger">
                                Adresa je povinná.
                            </small>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm mt-3">Zadajte pozíciu kliknutím na mapu</label>
                    </div>
                    <div class="mt-3">
                        <MapSelector :latitude="company.latitude" :longitude="company.longitude" @update="onMapClick" />
                    </div>
                </div>

                <div class="card mb-4">
                    <h3 class="text-accent text-normal mb-2">Kontaktné informácie</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Telefón</label>
                            <InputText v-model="company.phone" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Email</label>
                            <InputText v-model="company.email" class="w-full" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm mb-1">Zodpovedná osoba</label>
                            <Select v-model="company.representative_id" :options="representativeOptions"
                                optionLabel="first_name" optionValue="id" class="w-full">
                                <template #option="{ option }">
                                    <span>{{ option.first_name }} {{ option.last_name }}</span>
                                </template>
                                <template #value="{ value }">
                                    <span v-if="value">
                                        {{representativeOptions.find(u => u.id === value)?.first_name}}
                                        {{representativeOptions.find(u => u.id === value)?.last_name}}
                                    </span>
                                </template>
                            </Select>
                            <small v-if="submitted && !company.representative_id" class="text-danger">
                                Zodpovedná osoba je povinná.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-span-2">
                    <h3 class="text-accent text-normal mb-2">Zdroje spoločnosti</h3>
                    <label class="block text-sm mb-1">Pečiatka spoločnosti</label>


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
                        <small class="text-gray-500">
                            Povolený je iba PNG s transparentným pozadím, max. 300 × 100 px.
                        </small>
                    </div>

                    <div v-if="stampPreviewUrl" class="mt-3">
                        <div class="relative border rounded-md p-3 inline-block">
                            <img
                                :src="stampPreviewUrl"
                                alt="Preview pečiatky"
                                class="max-h-32 object-contain"
                            />

                            <button
                                type="button"
                                class="absolute top-2 right-2 text-danger hover:bg-danger hover:text-white rounded px-2 h-7"
                                @click="clearSelectedStamp"
                                aria-label="Odstrániť pečiatku"
                            >
                                <i class="bi bi-eraser"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button label="Uložiť"
                        class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
                        @click="save" :disabled="saving || loading" />
                </div>
            </div>
        </div>

    </div>
</template>
