<script setup lang="ts">
import { ref } from 'vue';
import { usePatientStore } from '@/stores/patientStore';
import { type Patient } from '@/types/models';
import { useToast } from 'primevue/usetoast';
import PatientForm from './PatientForm.vue';
import type { IModalContentProps } from '@/types/ui';
import useAuthStore from '@/stores/auth';


const patientStore = usePatientStore();
const props = defineProps<IModalContentProps & { patientId: number; }>();

const patient = ref<Patient>({} as Patient);
// validation handled by composable
import usePatientFormValidation from '@/composables/usePatientFormValidation';
const { submitted, errors, validateForm, clearError } = usePatientFormValidation(patient);


const toast = useToast();

const createPatient = async () => {
    // basic validation
    if (!patient.value.first_name || !patient.value.last_name) {
        return;
    }

    // use api to save patient
    try {
        const branchId = useAuthStore().currentBranch?.id ?? null;
        if (!branchId) {
            throw new Error('Nie ste priradený k žiadnej pobočke. Skúste sa znovu prihlásiť.');
        }
        await patientStore.createPatient(patient.value, branchId)
        toast.add({ severity: 'success', summary: 'Pacient vytvorený', detail: `Pacient ${patient.value.first_name} bol úspešne vytvorený.` });
        if (props.modalResolve) {
            props.modalResolve(patient.value);
        }
    } catch (e) {
        console.error('Failed to save patient', e);
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť pacienta. Skúste to znova.', life: 5000 });
    }

};

</script>


<template>
    <PatientForm v-if="patient" v-model:patient="patient" :submitted="submitted" :errors="errors" @clear-error="clearError" />
    <div class="mt-4 flex justify-end">
        <Button label="Uložiť" class="bg-accent! px-md! text-white! hover:bg-darkgrey! border-0!"
            @click="(async () => { submitted = true; if (!validateForm()) return; await createPatient(); })()" />
    </div>
</template>
