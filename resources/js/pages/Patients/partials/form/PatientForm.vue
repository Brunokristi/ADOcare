<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import AddressAutocomplete from '@/components/Address/AddressAutocomplete.vue'
import MapSelector from '@/components/Address/MapSelector.vue'
import { useAddressForm } from '@/composables/address'
import useAuthStore from '@/stores/auth'
import { useApi } from '@/composables/useApi'
import type { Branch, Doctor, InsuranceCompany, Patient, Country, User } from '@/types/models'
import { formatBranchFullName, formatUserFullName } from '@/utils/formatUtils'

const props = defineProps<{
    patient?: Patient
    submitted?: boolean
    errors?: { [key: string]: string } | null
    disabled?: boolean
    allowAssignmentEditing?: boolean
    companyId?: number
}>()

const emit = defineEmits<{
    (e: 'update:patient', patient: Patient): void
    (e: 'clear-error', key: string): void
}>()

const submitted = computed(() => !!props.submitted)
const errors = computed(() => props.errors ?? {})

const authStore = useAuthStore()
const { list, listScoped } = useApi()

const branchOptions = ref<Branch[]>([])
const nurseOptions = ref<User[]>([])
const branchesError = ref<Error | null>(null)
const nursesError = ref<Error | null>(null)

// using a single typed empty object avoids repeating `as Patient` everywhere
const emptyPatient: Patient = {} as Patient
const localPatient = ref<Patient>(props.patient ? { ...props.patient } : emptyPatient)

// -------------------- Doctors / Insurance --------------------
const sexOptions = [
    { label: 'Muž', value: 'M' },
    { label: 'Žena', value: 'F' },
]

const doctorOptions = ref<{ id: number; name: string }[]>([])
const insuranceOptions = ref<{ id: number; name: string }[]>([])
const countryOptions = ref<{ id: number; name: string; code: string }[]>([])
// branchOptions / nurseOptions are handled via the scoped API helper

const canEditAssignments = computed(
    () => (props.allowAssignmentEditing ?? (authStore.isManager || authStore.isSuperadmin)),
)

async function loadFavouriteDoctors() {
    const branchId = authStore.currentBranch?.id
    if (!branchId) {
        return { data: [] as Doctor[], error: null }
    }

    const { data, error } = await listScoped<Doctor>(`favourite-doctors`, { branchId })

    doctorOptions.value = (data ?? []).map((doc) => ({
        id: doc.id,
        name: `${doc.title ?? ''} ${doc.first_name} ${doc.last_name}`.replace(/\s+/g, ' ').trim(),
    }))

    const selectedId = localPatient.value.doctor_id as unknown as number | null | undefined
    if (selectedId && !doctorOptions.value.some((o) => o.id === selectedId)) {
        ; (localPatient.value as any).doctor_id = null
    }

    return { data, error }
}

async function loadInsuranceCompanies() {
    const { data, error } = await list<InsuranceCompany>('/insurance-companies', { all: true })
    insuranceOptions.value = (data ?? []).map((ic) => ({
        id: ic.id,
        name: ic.name ?? '<Neznáma poisťovňa>',
    }))
    return { data, error }
}

async function loadCountries() {
    const { data, error } = await list<Country>('/countries', { all: true })
    if (!data || !Array.isArray(data)) {
        countryOptions.value = []
        return { data: [], error }
    }

    countryOptions.value = data.map((c) => ({
        id: c.id,
        name: c.name ?? 'Neznáma krajina',
        code: c.code ?? '',
    }))

    return { data, error }
}

async function loadBranches(companyId?: number) {
    branchesError.value = null
    try {
        const { data, error } = await listScoped<Branch>('/branches', companyId, { all: true })
        if (error) throw error
        branchOptions.value = data ?? []
        return { data: branchOptions.value, error: null }
    } catch (error) {
        branchesError.value = error as Error
        branchOptions.value = []
        return { data: branchOptions.value, error: branchesError.value }
    }
}

async function loadNursesForBranch(branchId: number | null | undefined, companyId?: number) {
    nurseOptions.value = []
    nursesError.value = null
    if (!branchId) {
        return { data: nurseOptions.value, error: null }
    }

    try {
        const { data, error } = await listScoped<User>(`/nurses`, { branchId, companyId })
        if (error) throw error
        nurseOptions.value = data
        return { data: nurseOptions.value, error: null }
    } catch (error) {
        nursesError.value = error as Error
        nurseOptions.value = []
        return { data: nurseOptions.value, error: nursesError.value }
    }
}

// Branch / nurse loading is handled via the scoped list helper, which returns data and errors.

watch(
    () => authStore.currentBranch?.id,
    async () => {
        await loadFavouriteDoctors()
    },
    { immediate: true }
)

watch(
    canEditAssignments,
    async (enabled) => {
        if (!enabled) return
        const { error: branchesError } = await loadBranches(props.companyId)
        if (branchesError) console.error('Failed to load branches', branchesError)

        const { error: nursesError } = await loadNursesForBranch(localPatient.value.branch_id)
        if (nursesError) console.error('Failed to load nurses', nursesError)
    },
)

onMounted(async () => {
    await loadFavouriteDoctors()
    await loadInsuranceCompanies()
    await loadCountries()
    if (canEditAssignments.value) {
        const { error: branchesError } = await loadBranches(props.companyId)
        if (branchesError) console.error('Failed to load branches', branchesError)

        const { error: nursesError } = await loadNursesForBranch(localPatient.value.branch_id)
        if (nursesError) console.error('Failed to load nurses', nursesError)
    }
})

watch(
    () => localPatient.value.branch_id,
    (branchId) => {
        if (canEditAssignments.value) {
            void loadNursesForBranch(branchId).then(({ error }) => {
                if (error) console.error('Failed to load nurses', error)
            })
        }
    },
)

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
        const next: Patient = p ? { ...p } : emptyPatient
        localPatient.value = next

        // initialize addressEntity (composable expects `psc` for postal code)
        addressEntity.value = { ...next, psc: next.zip }
        initAddressForm()
    },
    { immediate: true, deep: true }
)

// propagate local changes up to parent (avoid loops)
watch(
    localPatient,
    (val) => {
        try {
            const parentVal: Patient = props.patient ?? emptyPatient
            if (JSON.stringify(val) !== JSON.stringify(parentVal)) {
                emit('update:patient', { ...val })
            }
        } catch {
            emit('update:patient', { ...val })
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
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Meno
                </label>
                <InputText :disabled="disabled" v-model.trim="localPatient.first_name" fluid
                    :invalid="submitted && !localPatient.first_name"
                    :class="{ 'bg-transparent!': disabled, 'opacity-50!': disabled }" />
                <small v-if="submitted && errors.first_name" class="text-danger">{{ errors.first_name }}</small>
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Priezvisko
                </label>
                <InputText :disabled="disabled" v-model.trim="localPatient.last_name" fluid
                    :invalid="submitted && !localPatient.last_name"
                    :class="{ 'bg-transparent!': disabled, 'opacity-50!': disabled }" />
                <small v-if="submitted && errors.last_name" class="text-danger">{{ errors.last_name }}</small>
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Titul
                </label>
                <InputText :disabled="disabled" v-model.trim="localPatient.title" fluid
                    :class="{ 'opacity-50!': disabled }" />
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Rodné číslo
                </label>
                <InputText :disabled="disabled" v-model="localPatient.personal_number" maxlength="11"
                    inputmode="numeric" pattern="[0-9]*" fluid :invalid="submitted && !localPatient.personal_number"
                    :class="{ 'opacity-50!': disabled }" @input="onPersonalNumberInput" />
                <small v-if="submitted && errors.personal_number" class="text-danger">{{ errors.personal_number
                    }}</small>
            </div>

            <div class="col-span-2">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Pohlavie
                </label>
                <Select :disabled="disabled" v-model="localPatient.sex" :options="sexOptions" optionLabel="label"
                    optionValue="value" fluid :invalid="submitted && !localPatient.sex"
                    :class="{ 'opacity-50!': disabled }" />
                <small v-if="submitted && errors.sex" class="text-danger">{{ errors.sex }}</small>
            </div>

            <div class="col-span-2">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Národnosť
                </label>
                <Select :disabled="disabled" v-model="localPatient.country_id" :options="countryOptions"
                    optionLabel="name" optionValue="id" fluid filter :invalid="submitted && !localPatient.country_id"
                    :class="{ 'opacity-50!': disabled }" />
                <small v-if="submitted && errors.country_id" class="text-danger">{{ errors.country_id }}</small>
            </div>

            <div class="col-span-4">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Kontakt
                </label>
                <InputText :disabled="disabled" v-model.trim="localPatient.contact" fluid
                    :class="{ 'opacity-50!': disabled }" />
                <small v-if="submitted && errors.contact" class="text-danger">{{ errors.contact }}</small>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Zdravotné detaily</label>
            </div>

            <div class="col-span-6">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Lekár
                </label>

                <Select ref="doctorSelectRef" :disabled="disabled" v-model="localPatient.doctor_id"
                    :options="doctorOptions" optionLabel="name" optionValue="id" fluid filter
                    :invalid="submitted && !localPatient.doctor_id" :class="{ 'opacity-50!': disabled }"
                    @show="onDoctorSelectShow">
                    <template #footer>
                        <div class="p-2">
                            <Button label="Pridať nového lekára"
                                class="w-full! bg-accent! text-white! text-normal! rounded-md hover:bg-darkgrey! border-0!"
                                icon="bi bi-plus" type="button" @click.prevent.stop="openDoctorsSettingsFromFooter" />
                        </div>
                    </template>
                </Select>

                <small v-if="submitted && errors.doctor_id" class="text-danger">
                    {{ errors.doctor_id }}
                </small>
            </div>

            <div class="col-span-6">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Poisťovňa
                </label>
                <Select :disabled="disabled" v-model="localPatient.insurance_company_id" :options="insuranceOptions"
                    optionLabel="name" optionValue="id" fluid :invalid="submitted && !localPatient.insurance_company_id"
                    :class="{ 'opacity-50!': disabled }" />
                <small v-if="submitted && errors.insurance_company_id" class="text-danger">
                    {{ errors.insurance_company_id }}
                </small>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Adresa</label>
            </div>

            <div class="col-span-12">
                <label :class="['block text-normal mb-1', disabled && 'opacity-50!']">
                    Adresa (ulica, mesto, PSČ)
                </label>
                <AddressAutocomplete v-model="addressQuery" @selected="onAutocompleteSelected" class="w-full"
                    :disabled="disabled" :invalid="submitted && !!errors.address"
                    :class="{ 'opacity-50!': disabled }" />
                <small v-if="submitted && errors.address" class="text-danger">{{ errors.address }}</small>
            </div>

            <div class="col-span-12">
                <MapSelector :latitude="localPatient.latitude" :longitude="localPatient.longitude"
                    :disabled="authStore.currentRole === 'manager'" @update="addressOnMapClick" />
                <small v-if="submitted && errors.coordinates" class="text-danger">{{ errors.coordinates }}</small>
            </div>
        </div>

        <div v-if="canEditAssignments" class="grid grid-cols-12 gap-4">
            <div class="col-span-6">
                <label class="block text-normal mb-1">Prevádzka</label>
                <Select v-model="localPatient.branch_id" :options="branchOptions" optionLabel="address" optionValue="id"
                    fluid :invalid="submitted && !localPatient.branch_id">
                    <template #value="slotProps">
                        <span v-if="slotProps.value">
                            {{formatBranchFullName(branchOptions.find(b => b.id === slotProps.value) as Branch)
                            }}</span>
                        <span v-else>Vybrať prevádzku</span>
                    </template>
                    <template #option="slotProps">
                        <span v-if="slotProps.option">
                            {{ formatBranchFullName(slotProps.option) }}</span>
                    </template>
                </Select>
                <small v-if="submitted && errors.branch_id" class="text-danger">{{ errors.branch_id }}</small>
            </div>

            <div class="col-span-6">
                <label class="block text-normal mb-1">Zdravotná Sestra</label>
                <Select v-model="localPatient.nurse_id" :options="nurseOptions" optionLabel="first_name"
                    optionValue="id" fluid :invalid="submitted && !localPatient.nurse_id">
                    <template #value="slotProps">
                        <span v-if="slotProps.value">
                            {{formatUserFullName(nurseOptions.find(n => n.id === slotProps.value) as User)}}</span>
                        <span v-else>Vybrať sestru</span>
                    </template>
                    <template #option="slotProps">
                        <span v-if="slotProps.option">
                            {{ formatUserFullName(slotProps.option) }}</span>
                    </template>
                </Select>
                <small v-if="submitted && errors.nurse_id" class="text-danger">{{ errors.nurse_id }}</small>
            </div>
        </div>
    </div>
</template>
