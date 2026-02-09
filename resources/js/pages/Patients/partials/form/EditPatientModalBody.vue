<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { usePatientStore } from '@/stores/patientStore';
import api from '@/services/api';
import { useToast } from 'primevue/usetoast';
import PatientForm from './PatientForm.vue';
import type { IModalContentProps } from '@/types/ui';
import usePatientFormValidation from '@/composables/usePatientFormValidation';
import type { Branch, Patient, User } from '@/types/models';
import { formatBranchFullName, formatUserFullName } from '@/utils/formatUtils';


const patientStore = usePatientStore();
const props = defineProps<IModalContentProps & { patientId: number; isManagerView: boolean; }>();

const patient = ref<Patient>({} as Patient);
const { submitted, errors, validateForm, clearError } = usePatientFormValidation(patient);

onMounted(async () => {
    try {
        const fetched = await api.fetchEntity<Patient>(`v1/patients/${props.patientId}`, { with: ['nurse', 'doctor', 'insuranceCompany'] });
        patient.value = fetched;

    } catch (e) {
        console.error('Failed to fetch patient', e);
    }
});

const toast = useToast();

const savePatient = async () => {
    if (!patient.value.first_name || !patient.value.last_name) {
        return;
    }

    try {
        const fresh = await patientStore.persistPatientData(patient.value)
        patient.value = fresh;
        toast.add({ severity: 'success', summary: 'Pacient uložený', detail: `Pacient ${patient.value.first_name} bol úspešne uložený.`, life: 3000 });
        if (props.modalResolve) {
            props.modalResolve(patient.value);
        }
    } catch (e) {
        console.error('Failed to save patient', e);
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť pacienta. Skúste to znova.', life: 5000 });
    }

};

// Get branch and nurse options
const branchOptions = ref<Branch[]>([]);
const nurseOptions = ref<User[]>([]);

async function updateNurseOptions() {
    nurseOptions.value = [];
    if (!patient.value.branch_id) {
        return [];
    }
    const nurses = await api.fetchEntities<User>(`v1/branches/${patient.value.branch_id}/nurses`);
    nurseOptions.value = nurses;
}

if (props.isManagerView) {
    onMounted(async () => {
        try {
            const branches = await api.fetchEntities<Branch>('v1/my-company/branches');
            branchOptions.value = branches;

            await updateNurseOptions();
        } catch (e) {
            console.error('Failed to fetch branch or nurse options', e);
        }
    });
    watch(() => patient.value.branch_id, () => {
        updateNurseOptions();
    });
}

</script>


<template>
    <PatientForm :disabled="isManagerView" v-if="patient" v-model:patient="patient" :submitted="submitted"
        :errors="errors" @clear-error="clearError" />
    <div v-if="isManagerView">
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Zdravotné detaily</label>
            </div>

            <div class="col-span-6">
                <label class="block text-normal mb-1">Prevádzka</label>
                <Select v-model="patient.branch_id" :options="branchOptions" optionLabel="address" optionValue="id"
                    fluid filter :invalid="submitted && !patient.branch_id">
                    <template #value="slotProps">
                        <span v-if="slotProps.value">
                            {{formatBranchFullName(branchOptions.find(b => b.id === slotProps.value) as Branch)}}</span>
                        <span v-else>Vybrať prevádzku</span>
                    </template>
                    <template #option="slotProps">
                        <span v-if="slotProps.option">
                            {{ formatBranchFullName(slotProps.option) }}</span>
                    </template>
                </Select>
                <small v-if="submitted && errors.doctor_id" class="text-warning">{{ errors.doctor_id }}</small>
            </div>

            <div class="col-span-6">
                <label class="block text-normal mb-1">Zdravotná Sestra</label>
                <Select v-model="patient.nurse_id" :options="nurseOptions" optionLabel="first_name" optionValue="id"
                    fluid :invalid="submitted && !patient.nurse_id">
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
                <small v-if="submitted && errors.nurse_id" class="text-warning">
                    {{ errors.insurance_company_id }}
                </small>
            </div>
        </div>
    </div>

    <div class="mt-4 flex justify-end">
        <Button label="Uložiť" class="bg-accent! px-md! text-white! hover:bg-darkgrey! border-0!"
            @click="(async () => { submitted = true; if (!validateForm()) return; await savePatient(); })()" />
    </div>
</template>
