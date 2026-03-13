<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { usePatientStore } from '@/stores/patientStore';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useToast } from 'primevue/usetoast';
import PatientForm from './PatientForm.vue';
import type { IModalContentProps } from '@/types/ui';
import usePatientFormValidation from '@/composables/usePatientFormValidation';
import type { Patient } from '@/types/models';


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

// Branch/nurse assignment is now handled by PatientForm when the user is allowed.

</script>


<template>
    <PatientForm :disabled="isManagerView" v-if="patient" v-model:patient="patient" :submitted="submitted"
        :errors="errors" @clear-error="clearError"
        :allow-assignment-editing="isManagerView || useAuthStore().isSuperadmin" />

    <div class="mt-4 flex justify-end">
        <Button label="Uložiť" class="bg-accent! px-md! text-white! hover:bg-darkgrey! border-0!"
            @click="(async () => { submitted = true; if (!validateForm()) return; await savePatient(); })()" />
    </div>
</template>
