<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { usePatientStore } from '@/stores/patientStore';
import api from '@/services/api';
import { useToast } from 'primevue/usetoast';
import PatientForm from './PatientForm.vue';
import type { IModalContentProps } from '@/types/ui';
import usePatientFormValidation from '@/composables/usePatientFormValidation';
import type { Patient } from '@/types/models';


const patientStore = usePatientStore();
const props = defineProps<IModalContentProps & { patientId: number; }>();

const patient = ref<any>({} as Patient);
const { submitted, errors, validateForm, clearError } = usePatientFormValidation(patient);

onMounted(async () => {
    try {
        const fetched = await api.fetchEntity<Patient>(`v1/patients/${props.patientId}`);
        patient.value = fetched;
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

</script>


<template>
    <PatientForm v-if="patient" v-model:patient="patient" :submitted="submitted" :errors="errors" @clear-error="clearError" />
    <div class="mt-4 flex justify-end">
        <Button label="Uložiť" class="bg-accent! px-md! text-white! hover:bg-darkgrey! border-0!"
            @click="(async () => { submitted = true; if (!validateForm()) return; await savePatient(); })()" />
    </div>
</template>
