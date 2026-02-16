<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import api from '@/services/api'
import AddressAutocomplete from '@/components/Address/AddressAutocomplete.vue'
import MapSelector from '@/components/Address/MapSelector.vue'
import { useAddressForm } from '@/composables/address'
import useAuthStore from '@/stores/auth'
import type { Doctor, InsuranceCompany, Patient } from '@/types/models'

const props = defineProps<{
    patient: Patient
    submitted?: boolean
    errors?: { [key: string]: string } | null
    disabled?: boolean
}>()

const emit = defineEmits<{
    (e: 'update:patient', patient: Patient): void
    (e: 'clear-error', key: string): void
}>()

const submitted = computed(() => !!props.submitted)
const errors = computed(() => props.errors ?? {})

const authStore = useAuthStore()

const localPatient = ref<Patient>({ ...(props.patient ?? ({} as Patient)) })

// -------------------- Doctors / Insurance --------------------
const sexOptions = [
    { label: 'Muž', value: 'M' },
    { label: 'Žena', value: 'F' },
]

const doctorOptions = ref<{ id: number; name: string }[]>([])
const insuranceOptions = ref<{ id: number; name: string }[]>([])

async function loadFavouriteDoctors() {
    const branchId = authStore.currentBranch?.id
    if (!branchId) {
        doctorOptions.value = []
        return
    }

    const doctors = await api.fetchEntities<Doctor>(`v1/branches/${branchId}/favourite-doctors`)

    doctorOptions.value = doctors.map((doc) => ({
        id: doc.id,
        name: `${doc.title ?? ''} ${doc.first_name} ${doc.last_name}`.replace(/\s+/g, ' ').trim(),
    }))

    const selectedId = localPatient.value.doctor_id as unknown as number | null | undefined
    if (selectedId && !doctorOptions.value.some((o) => o.id === selectedId)) {
        ; (localPatient.value as any).doctor_id = null
    }
}

async function loadInsuranceCompanies() {
    const insuranceCompanies = await api.fetchEntities<InsuranceCompany>('/v1/insurance-companies', { all: true })
    insuranceOptions.value = insuranceCompanies.map((ic) => ({
        id: ic.id,
        name: ic.name ?? '<Neznáma poisťovňa>',
    }))
}

watch(
    () => authStore.currentBranch?.id,
    async () => {
        await loadFavouriteDoctors()
    },
    { immediate: true }
)

onMounted(async () => {
    await loadFavouriteDoctors()
    await loadInsuranceCompanies()
})

// -------------------- Personal number --------------------
function onPersonalNumberInput(e: Event) {
    const input = e.target as HTMLInputElement
    const digitsOnly = input.value.replace(/\D+/g, '')
    localPatient.value.personal_number = digitsOnly
}

watch(
    () => localPatient.value.personal_number,
    (val) => {
        if (!val) return
        const clean = val.replace(/\D+/g, '')
        if (val !== clean) localPatient.value.personal_number = clean
    }
)

// -------------------- Address (shared composable) --------------------
// addressEntity mirrors patient fields but uses `psc` for postal code (composable convention)
const addressEntity = ref<Record<string, any> | null>(null)
const { addressQuery, init: initAddressForm, onAutocompleteSelected, onMapClick: addressOnMapClick } = useAddressForm(addressEntity)

const doctorSelectRef = ref<any>(null)

const onDoctorSelectShow = async () => {
    await loadFavouriteDoctors()
}

const openDoctorsSettingsFromFooter = async () => {
    doctorSelectRef.value?.hide?.()
    window.open('/settings/doctors', '_blank', 'noopener,noreferrer')
}




// ==== Watch

// Sync local patient + inputs when parent patient changes
watch(
    () => props.patient,
    (p) => {
        const next = { ...(p ?? ({} as Patient)) }
        localPatient.value = next

        // initialize addressEntity (composable expects `psc` for postal code)
        addressEntity.value = { ...next, psc: (next as any).zip ?? next.psc }
        initAddressForm()
    },
    { immediate: true, deep: true }
)

// propagate local changes up to parent (avoid loops)
watch(
    localPatient,
    (val) => {
        try {
            const parentVal = props.patient ?? ({} as Patient)
            if (JSON.stringify(val) !== JSON.stringify(parentVal)) {
                emit('update:patient', { ...(val as Patient) })
            }
        } catch {
            emit('update:patient', { ...(val as Patient) })
        }
    },
    { deep: true }
)

// Sync addressEntity changes back to localPatient
watch(
    addressEntity,
    (val) => {
        if (val) {
            localPatient.value.address = val.address ?? localPatient.value.address
            localPatient.value.city = val.city ?? localPatient.value.city
            localPatient.value.zip = val.psc ?? localPatient.value.zip
            localPatient.value.latitude = val.latitude ?? localPatient.value.latitude
            localPatient.value.longitude = val.longitude ?? localPatient.value.longitude
        }
    },
    { deep: true }
)


</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Osobné údaje</label>
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Meno
                </label>
                <InputText :disabled="disabled" v-model.trim="localPatient.first_name" fluid
                    :invalid="submitted && !localPatient.first_name"
                    :class="{ '!bg-transparent': disabled, '!opacity-50': disabled }" />
                <small v-if="submitted && errors.first_name" class="text-warning">{{ errors.first_name }}</small>
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Priezvisko
                </label>
                <InputText :disabled="disabled" v-model.trim="localPatient.last_name" fluid
                    :invalid="submitted && !localPatient.last_name"
                    :class="{ '!bg-transparent': disabled, '!opacity-50': disabled }" />
                <small v-if="submitted && errors.last_name" class="text-warning">{{ errors.last_name }}</small>
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Titul
                </label>
                <InputText :disabled="disabled" v-model.trim="localPatient.title" fluid
                    :class="{ '!opacity-50': disabled }" />
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Rodné číslo
                </label>
                <InputText :disabled="disabled" v-model="localPatient.personal_number" maxlength="11"
                    inputmode="numeric" pattern="[0-9]*" fluid :invalid="submitted && !localPatient.personal_number"
                    :class="{ '!opacity-50': disabled }" @input="onPersonalNumberInput" />
                <small v-if="submitted && errors.personal_number" class="text-warning">{{ errors.personal_number
                    }}</small>
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Pohlavie
                </label>
                <Select :disabled="disabled" v-model="localPatient.sex" :options="sexOptions" optionLabel="label"
                    optionValue="value" fluid :invalid="submitted && !localPatient.sex"
                    :class="{ '!opacity-50': disabled }" />
                <small v-if="submitted && errors.sex" class="text-warning">{{ errors.sex }}</small>
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Kontakt
                </label>
                <InputText :disabled="disabled" v-model.trim="localPatient.contact" fluid
                    :class="{ '!opacity-50': disabled }" />
                <small v-if="submitted && errors.contact" class="text-warning">{{ errors.contact }}</small>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Zdravotné detaily</label>
            </div>

            <div class="col-span-6">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Lekár
                </label>

                <Select ref="doctorSelectRef" :disabled="disabled" v-model="localPatient.doctor_id"
                    :options="doctorOptions" optionLabel="name" optionValue="id" fluid filter
                    :invalid="submitted && !localPatient.doctor_id" :class="{ '!opacity-50': disabled }"
                    @show="onDoctorSelectShow">
                    <template #footer> Pocet pacientov pre lekara negunguje - lekari manager
                        <div class="p-2 border-t">
                            <Button label="Pridať nového lekára" icon="pi pi-plus" fluid severity="secondary"
                                variant="text" size="small" type="button"
                                @click.prevent.stop="openDoctorsSettingsFromFooter" />
                        </div>
                    </template>
                </Select>

                <small v-if="submitted && errors.doctor_id" class="text-warning">
                    {{ errors.doctor_id }}
                </small>
            </div>

            <div class="col-span-6">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Poisťovňa
                </label>
                <Select :disabled="disabled" v-model="localPatient.insurance_company_id" :options="insuranceOptions"
                    optionLabel="name" optionValue="id" fluid :invalid="submitted && !localPatient.insurance_company_id"
                    :class="{ '!opacity-50': disabled }" />
                <small v-if="submitted && errors.insurance_company_id" class="text-warning">
                    {{ errors.insurance_company_id }}
                </small>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Adresa</label>
            </div>

            <div class="col-span-12">
                <label :class="['block text-normal mb-1', disabled && '!opacity-50']">
                    Adresa (ulica, mesto, PSČ)
                </label>
                <AddressAutocomplete v-model="addressQuery" @selected="onAutocompleteSelected" class="w-full"
                    :disabled="disabled" :invalid="submitted && !!errors.address"
                    :class="{ '!opacity-50': disabled }" />
                <small v-if="submitted && errors.address" class="text-warning">{{ errors.address }}</small>
            </div>

            <div class="col-span-12">
                <MapSelector :latitude="localPatient.latitude" :longitude="localPatient.longitude"
                    @update="addressOnMapClick" />
            </div>
        </div>
    </div>
</template>
