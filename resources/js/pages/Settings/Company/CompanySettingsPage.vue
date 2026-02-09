<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import AddressAutocomplete from '@/components/Address/AddressAutocomplete.vue'
import MapSelector from '@/components/Address/MapSelector.vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import { searchAutocomplete, fetchPlaceDetails, parseComponents, extractAddressFromPlace } from '@/composables/useAddressAutocomplete'
import type { Company, User } from '@/types/models'
import { mergeAddressParts } from '@/utils/formatUtils'

const toast = useToast()
const company = ref<Company & { representative?: User }>({} as any)
const loading = ref(true)
const saving = ref(false)
const zoom = ref(13)
const representativeOptions = ref<User[]>([])
const addressQuery = ref<string | null>('');

onMounted(async () => {
    loading.value = true
    try {
        const data = await api.fetchEntity<Company>('v1/my-company')
        if (data) {
            company.value = data
            addressQuery.value = mergeAddressParts(company.value.address, company.value.city, company.value.psc) || company.value.address
        }
        // fetch company users for representative selection
        try {
            representativeOptions.value = await api.fetchEntities<User>('v1/my-company/users')
        } catch (e) {
            console.error('Failed to fetch representative users', e)
        }
    } catch (e) {
        console.error('Failed to load company', e)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa načítať spoločnosť' })
    } finally {
        loading.value = false
    }
})

async function save() {
    if (!company.value.id) return
    saving.value = true
    // Best-effort: if address provided but city/psc/coords missing, try to resolve via autocomplete
    try {
        const needResolve = !!company.value.address && (!company.value.city || !company.value.psc || !company.value.latitude || !company.value.longitude)
        if (needResolve) {
            const preds = await searchAutocomplete(company.value.address as string)
            if (preds && preds.length > 0) {
                const first = preds[0]
                const details = await fetchPlaceDetails(first.place_id)
                if (details) {
                    const parsed = parseComponents(details.address_components || [])
                    company.value.address = first.label || company.value.address
                    company.value.city = parsed.city || company.value.city
                    company.value.psc = parsed.zip || company.value.psc
                    company.value.latitude = details.geometry?.location?.lat ?? company.value.latitude
                    company.value.longitude = details.geometry?.location?.lng ?? company.value.longitude
                }
            }
        }
    } catch (err) {
        console.error('Address resolution before save failed', err)
        // continue to attempt save with whatever data we have
    }
    try {
        const payload = { ...company.value }
        await api.patch(`v1/companies/${company.value.id}`, payload)
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Spoločnosť bola upravená' })
    } catch (e) {
        console.error('Failed to save company', e)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť spoločnosť' })
    }
    finally {
        saving.value = false
    }
}

// Only perform reverse geocoding when the user clicks on the map.
// const addressResolveToken = ref(0)
async function onMapClick(payload: { lat: number | null; lon: number | null }) {
    try {
        const lat = payload.lat
        const lon = payload.lon
        company.value.latitude = lat
        company.value.longitude = lon
        if (lat && lon) { zoom.value = 15 }

        if (!lat || !lon) return
        const res = await api.get('/v1/geocode/reverse', { params: { lat, lon } })
        if (res && res.data) {
            const place = res.data
            if (place.address) company.value.address = place.address
            if (place.city) company.value.city = place.city
            if (place.postcode) company.value.psc = place.postcode
            addressQuery.value = mergeAddressParts(company.value.address, company.value.city, company.value.psc) || company.value.address
            // trigger autocomplete to resolve canonical place and emit selected
        }
    } catch (err) {
        console.error('Reverse geocode failed', err)
    }
}

</script>

<template>
    <LoadingOverlay :show="loading || saving" :text="loading ? 'Načítavam...' : 'Ukladám...'" />
    <div class="p-4">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <div class="card mb-4">
                    <h3 class="text-lg mb-2">Všeobecné informácie</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Názov</label>
                            <InputText v-model="company.name" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Zapísaná v registri</label>
                            <InputText v-model="company.register" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">IČO</label>
                            <InputText v-model="company.ico" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">DIČ</label>
                            <InputText v-model="company.dic" class="w-full" />
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

                    </div>
                </div>

                <div class="card mb-4">
                    <h3 class="text-lg mb-2">Adresa</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm mb-1">Adresa (ulica, mesto, PSČ)</label>
                            <AddressAutocomplete v-model="addressQuery" @selected="(s) => {
                                const { city, street, zip, latitude, longitude, } = extractAddressFromPlace(s);
                                company = {
                                    ...company,
                                    city: city || company.city,
                                    address: street || company.address,
                                    psc: zip || company.psc,
                                    latitude: latitude || company.latitude,
                                    longitude: longitude || company.longitude,
                                }
                            }" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm mt-3">Zadajte pozíciu kliknutím na mapu</label>
                    </div>
                    <div class="mt-3">
                        <MapSelector :latitude="company.latitude" :longitude="company.longitude" @update="({ lat, lon }) => {
                            company.latitude = lat
                            company.longitude = lon
                            onMapClick({ lat, lon })
                        }" />
                    </div>
                </div>

                <div class="card mb-4">
                    <h3 class="text-lg mb-2">Kontaktné informácie</h3>
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
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button label="Uložiť" class="bg-accent!" @click="save" :disabled="saving || loading" />
                </div>
            </div>
        </div>

    </div>
</template>
