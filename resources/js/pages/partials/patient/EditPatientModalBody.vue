<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { usePatientStore } from '@/stores/patientStore';
import api from '@/services/api';
import { useToast } from 'primevue/usetoast';
import PatientForm from './PatientForm.vue';
import type { IModalContentProps } from '@/types/ui';
import usePatientFormValidation from '@/composables/usePatientFormValidation';
import type { Branch, Patient, User } from '@/types/models';


const patientStore = usePatientStore();
const props = defineProps<IModalContentProps & { patientId: number; isManagerView: boolean; }>();

const patient = ref<Patient>({} as Patient);
const { submitted, errors, validateForm, clearError } = usePatientFormValidation(patient);

onMounted(async () => {
    try {
        const fetched = await api.fetchEntity<Patient>(`v1/patients/${props.patientId}`, { with: ['assignedUsers', 'doctor', 'insuranceCompany'] });
        patient.value = fetched;
        console.log('Fetched patient:', fetched);

    } catch (e) {
        console.error('Failed to fetch patient', e);
    }
});

const toast = useToast();

const savePatient = async () => {
    // basic validation
    if (!patient.value.first_name || !patient.value.last_name) {
        return;
    }

    // use api to save patient
    try {
        await patientStore.persistPatientData(patient.value)
        const fresh = await patientStore.fetchPatient(patient.value.id)
        patient.value = fresh;
        toast.add({ severity: 'success', summary: 'Pacient uložený', detail: `Pacient ${patient.value.first_name} bol úspešne uložený.` });
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
const selectedNurseId = ref<number | null>(null);
const selectedBranchId = ref<number | null>(null);

console.log();


onMounted(async () => {
    try {
        const branches = await api.fetchEntities<Branch>('v1/branches');
        branchOptions.value = branches;
        const nurses = await api.fetchEntities<User>('v1/branches/{users}', { role: 'nurse' });
        nurseOptions.value = nurses;
    } catch (e) {
        console.error('Failed to fetch branch or nurse options', e);
    }
});




// Save branch and nurse assignments
const saveBranchAndNurse = async () => {
    // patients/{pattientId}/assign-doctor-and-branch
    try {
        await api.post(`v1/patients/${patient.value.id}/assign-doctor-and-branch`, {
            doctor_id: selectedBranchId.value,
            nurse_id: selectedNurseId.value,
        });
        toast.add({ severity: 'success', summary: 'Priradenie uložené', detail: 'Prevádzka a sestra boli úspešne priradené.' });
    } catch (e) {
        console.error('Failed to save branch and nurse assignments', e);
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť priradenie. Skúste to znova.', life: 5000 });
    }
};


</script>


<template>
    <PatientForm :disabled="isManagerView" v-if="patient" v-model:patient="patient" :submitted="submitted"
        :errors="errors" @clear-error="clearError" />
    <div>
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Zdravotné detaily</label>
            </div>

            <div class="col-span-6">
                <label class="block text-normal mb-1">Prevádzka</label>
                <Select v-model="patient.doctor_id" :options="branchOptions" optionLabel="name" optionValue="id" fluid
                    filter :invalid="submitted && !patient.doctor_id" />
                <small v-if="submitted && errors.doctor_id" class="text-warning">{{ errors.doctor_id }}</small>
            </div>

            <div class="col-span-6">
                <label class="block text-normal mb-1">Zdravotná Sestra</label>
                <Select v-model="patient.assigned_users" :options="nurseOptions" optionLabel="name" optionValue="id"
                    fluid :invalid="submitted && !patient.insurance_company_id" />
                <small v-if="submitted && errors.insurance_company_id" class="text-warning">
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
