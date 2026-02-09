<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import AddressAutocomplete from '@/components/Address/AddressAutocomplete.vue'
import MapSelector from '@/components/Address/MapSelector.vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import useAddressForm from '@/composables/useAddressForm'
import useFormValidator, { required, email } from '@/composables/useFormValidator'
import AlertBar from '@/components/AlertBar.vue'
import type { Company, User } from '@/types/models'
import { mergeAddressParts } from '@/utils/formatUtils'

const toast = useToast()
const company = ref<Company & { representative?: User }>({} as any)
const loading = ref(true)
const saving = ref(false)
const representativeOptions = ref<User[]>([])
const { addressQuery, init, onAutocompleteSelected, onMapClick: onMapClickAddress, resolveBeforeSave } = useAddressForm(company)

const alert = ref<{ severity: 'error' | 'success', message: string } | null>(null)

const validator = useFormValidator(
    {
        name: [required('Názov je povinný')],
        email: [email('Neplatný email')],
    },
    () => ({
        name: company.value.name,
        email: company.value.email,
    })
)

init()

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
        const ok = validator.validateAll()
        if (!ok) {
            alert.value = { severity: 'error', message: 'Opravte chyby vo formulári a skúste to znova' }
            saving.value = false
            return
        }
        await resolveBeforeSave()
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
    } finally {
        saving.value = false
    }
}

</script>

<template>
    <LoadingOverlay :show="loading || saving" :text="loading ? 'Načítavam...' : 'Ukladám...'" />
    <div class="p-4">
        <div v-if="alert?.message" class="mb-4">
            <AlertBar :message="alert.message" :severity="alert.severity" :closable="true" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <div class="card mb-4">
                    <h3 class="text-lg mb-2">Všeobecné informácie</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Názov</label>
                            <InputText v-model="company.name" class="w-full"
                                @blur="() => { validator.setTouched('name'); validator.validateField('name') }" />
                            <div v-if="validator.getError('name')" class="text-red-600 text-sm mt-1">{{
                                validator.getError('name') }}</div>
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
                            <AddressAutocomplete v-model="addressQuery" @selected="(s) => onAutocompleteSelected(s)" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm mt-3">Zadajte pozíciu kliknutím na mapu</label>
                    </div>
                    <div class="mt-3">
                        <MapSelector :latitude="company.latitude" :longitude="company.longitude"
                            @update="({ lat, lon }) => onMapClickAddress(lat, lon)" />
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
                            <InputText v-model="company.email" class="w-full"
                                @blur="() => { validator.setTouched('email'); validator.validateField('email') }" />
                            <div v-if="validator.getError('email')" class="text-red-600 text-sm mt-1">{{
                                validator.getError('email') }}</div>
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
