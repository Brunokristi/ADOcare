<script setup lang="ts">
import { ref, onMounted } from 'vue'
import useFormValidator, { required, email } from '@/composables/useFormValidator'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import type { IModalContentProps } from '@/types/ui'
import type { Branch, User } from '@/types/models'
import { mergeAddressParts } from '@/utils/formatUtils'
import AddressAutocomplete from '@/components/Address/AddressAutocomplete.vue'
import { useAddressForm } from '@/composables/address'
import MapSelector from '@/components/Address/MapSelector.vue'
import AlertBar from '@/components/AlertBar.vue'

const props = defineProps<IModalContentProps & { branchId?: number }>()
const toast = useToast()

const branch = ref<Partial<Branch>>({} as any)
const loading = ref(false)
const representativeOptions = ref<User[]>([])
const { addressQuery, init, onAutocompleteSelected, onMapClick } = useAddressForm(branch)
init()

const alert = ref<{ severity: 'error' | 'success', message: string } | null>({ severity: 'error', message: '' })

// Validation schema
const validator = useFormValidator(
    {
        identificator: [required('Identifikátor je povinný')],
        code: [required('Kód je povinný')],
        email: [email('Neplatný email')],
    },
    () => ({
        identificator: branch.value.identificator,
        code: branch.value.code,
        email: branch.value.email,
    })
)

onMounted(async () => {
    loading.value = true
    try {
        representativeOptions.value = await api.fetchEntities<User>('v1/my-company/users')
    } catch (e) {
        console.error('Failed to load representatives', e)
    }

    if (props.branchId) {
        try {
            branch.value = await api.fetchEntity<Branch>(`v1/branches/${props.branchId}`)
            addressQuery.value = mergeAddressParts(branch.value.address, branch.value.city, branch.value.psc) || branch.value.address || ''
        } catch (e) {
            console.error('Failed to load branch', e)
            toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa načítať pobočku' })
        }
    }
    loading.value = false
})

async function save() {
    // validate before saving
    const ok = validator.validateAll()
    if (!ok) {
        alert.value = { severity: 'error', message: 'Opravte chyby vo formulári a skúste to znova' }
        return
    }

    try {
        const payload = { ...branch.value }
        if (props.branchId) {
            await api.patch(`v1/branches/${props.branchId}`, payload)
            alert.value = { severity: 'success', message: 'Pobočka bola upravená' }
        } else {
            await api.post('v1/branches', payload)
            alert.value = { severity: 'success', message: 'Pobočka bola vytvorená' }
        }
        if (props.modalResolve) props.modalResolve(true)
    } catch (e) {
        console.error('Save branch failed', e)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť pobočku' })
        alert.value = { severity: 'error', message: 'Nepodarilo se uložit pobočku' }
    }
}

// handled by useAddressForm (onMapClick)
</script>

<template>
    <div class="p-3">

        <form class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <h3 class="text-lg mb-2">Všeobecné informácie</h3>
                <AlertBar v-if="alert?.message" :message="alert?.message" :severity="alert?.severity" :closable="false"
                    class="mb-3" />
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Identifikátor</label>
                        <InputText v-model="branch.identificator" required class="w-full"
                            @blur="() => { validator.setTouched('identificator'); validator.validateField('identificator') }" />
                        <div v-if="validator.getError('identificator')" class="text-red-600 text-sm mt-1">{{
                            validator.getError('identificator') }}</div>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Kód</label>
                        <InputText v-model="branch.code" required class="w-full"
                            @blur="() => { validator.setTouched('code'); validator.validateField('code') }" />
                        <div v-if="validator.getError('code')" class="text-red-600 text-sm mt-1">{{
                            validator.getError('code') }}
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm mb-1">Obozorný zástupca</label>
                        <Select v-model="branch.representative_id" :options="representativeOptions"
                            optionLabel="first_name" optionValue="id" class="w-full">
                            <template #option="{ option }">
                                <span>{{ option.first_name }} {{ option.last_name }}</span>
                            </template>
                            <template #value="{ value }">
                                <span v-if="value">{{representativeOptions.find(u => u.id === value)?.first_name}} {{
                                    representativeOptions.find(u => u.id === value)?.last_name}}</span>
                            </template>
                        </Select>
                    </div>
                </div>
            </div>

            <div class="col-span-12">
                <h3 class="text-lg mb-2">Adresa</h3>
                <div>
                    <label class="block text-sm mb-1">Adresa</label>
                    <AddressAutocomplete v-model="addressQuery" @selected="onAutocompleteSelected" />
                </div>
                <div>
                    <label class="block text-sm mt-3">Zadajte pozíciu kliknutím na mapu</label>
                </div>
                <div class="mt-3">
                    <MapSelector :latitude="branch.latitude" :longitude="branch.longitude" @update="onMapClick" />
                </div>
            </div>

            <div class="col-span-12">
                <h3 class="text-lg mb-2">Kontaktné informácie</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Telefón</label>
                        <InputText v-model="branch.phone" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Email</label>
                        <InputText v-model="branch.email" class="w-full"
                            @blur="() => { validator.setTouched('email'); validator.validateField('email') }" />
                        <div v-if="validator.getError('email')" class="text-red-600 text-sm mt-1">{{
                            validator.getError('email') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12">
                <h3 class="text-lg mb-2">Časové informácie</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Začiatok ošetrovania</label>
                        <input type="time" v-model="branch.terrain_start_time" class="w-full border p-2 rounded" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Začiatok administratívny</label>
                        <input type="time" v-model="branch.administrative_start_time"
                            class="w-full border p-2 rounded" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Čas na pacienta (min)</label>
                        <InputNumber v-model="branch.per_location_time" :min="0" class="w-full" />
                    </div>
                </div>
            </div>

            <div class="col-span-12 flex justify-end mt-4">
                <Button label="Uložiť" class="bg-accent! px-md! text-white!" @click="save" />
            </div>
        </form>
    </div>
</template>
