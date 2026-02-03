<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import { LMap, LMarker, LTileLayer } from '@vue-leaflet/vue-leaflet'
import type { Company, User } from '@/types/models'

const toast = useToast()
const company = ref<Partial<Company & { representative?: User }>>({} as any)
const loading = ref(false)
const mapRef = ref<any>(null)
const center = ref([48.1486, 17.1077])
const zoom = ref(13)
const representativeOptions = ref<User[]>([])

onMounted(async () => {
    loading.value = true
    try {
        const data = await api.fetchEntity<Company>('v1/my-company')
        if (data) {
            company.value = data
            if (company.value.latitude && company.value.longitude) {
                center.value = [company.value.latitude, company.value.longitude]
            }
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
    try {
        const payload = { ...company.value }
        await api.patch(`v1/companies/${company.value.id}`, payload)
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Spoločnosť bola upravená' })
    } catch (e) {
        console.error('Failed to save company', e)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť spoločnosť' })
    }
}

async function onMapClick(e: any) {
    try {
        const lat = e.latlng.lat
        const lon = e.latlng.lng
        company.value.latitude = lat
        company.value.longitude = lon
        center.value = [lat, lon]
        // reverse geocode to fill address
        const res = await api.get('/v1/geocode/reverse', { params: { lat, lon } })
        if (res && res.data) {
            const place = res.data
            company.value.address = place.address || company.value.address
            company.value.city = place.city || company.value.city
            company.value.psc = place.postcode || company.value.psc
        }
    } catch (err) {
        console.error('Reverse geocode failed', err)
    }
}
</script>

<template>
    <div class="p-4">
        <h2 class="text-xl mb-4">Nastavenia spoločnosti</h2>

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
                    </div>
                </div>

                <div class="card mb-4">
                    <h3 class="text-lg mb-2">Adresa</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Ulica + číslo</label>
                            <InputText v-model="company.address" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Mesto</label>
                            <InputText v-model="company.city" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">PSČ</label>
                            <InputText v-model="company.psc" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Zadajte pozíciu kliknutím na mapu</label>
                        </div>
                    </div>
                    <div class="mt-3 h-64 rounded-md overflow-hidden">
                        <LMap ref="mapRef" :center="center" :zoom="zoom" :useGlobalLeaflet="false" style="height:100%"
                            @click="onMapClick">
                            <LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
                            <LMarker v-if="company.latitude && company.longitude"
                                :lat-lng="[company.latitude, company.longitude]" />
                        </LMap>
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
                    <Button label="Uložiť" class="bg-accent!" @click="save" />
                </div>
            </div>

            <div>
                <div class="card">
                    <h3 class="text-lg mb-2">Rýchle informácie</h3>
                    <p v-if="company && company.name">{{ company.name }}</p>
                </div>
            </div>
        </div>

    </div>
</template>
