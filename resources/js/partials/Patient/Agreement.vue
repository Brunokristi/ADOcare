<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { storeToRefs } from 'pinia';
import api from '@/services/api';
import { usePatientStore } from '@/stores/patientStore';
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const { currentBranch } = storeToRefs(authStore)

const router = useRouter();
const toast = useToast();

const patientStore = usePatientStore();
patientStore.loadFromStorage();

const { current: currentPatient } = storeToRefs(patientStore);

const patientId = computed(() => currentPatient.value?.id ?? null);

// Form fields
const date = ref<Date>(new Date());
const submitted = ref(false);

// Validation
const isDateValid = computed(() => !!date.value);

function validateForm(): boolean {
  return isDateValid.value && !!patientId.value;
}

async function generateDocument() {
  submitted.value = true;

  if (!validateForm()) {
    toast.add({
      severity: 'error',
      summary: 'Chyba validácie',
      detail: 'Vyplňte všetky povinné polia.',
      life: 3000,
    });
    return;
  }

  if (!patientId.value) {
    toast.add({
      severity: 'error',
      summary: 'Chýbajúci pacient',
      detail: 'Najprv vyberte pacienta.',
      life: 3000,
    });
    return;
  }

  try {
    const payload = {
      patient_id: patientId.value,
      date: date.value ? new Date(date.value).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
      branch_id: currentBranch.value?.id,
    };

    const res = await api.post('/v1/agreements', payload);

    if (res.data?.document_id) {
      toast.add({
        severity: 'success',
        summary: 'Úspešne vytvorené',
        detail: 'Dohoda o poskytovaní zdravotnej starostlivosti bola úspešne vytvorená.',
        life: 3000,
      });

      router.push({
        name: 'documents-agreement',
        params: { documentId: res.data.document_id },
      });
    }
  } catch (err: any) {
    console.error('Failed to generate agreement document:', err);

    const message =
      err?.response?.data?.errors
        ? Object.values(err.response.data.errors).flat()[0]
        : err?.response?.data?.message || err?.message || 'Chyba pri vytváraní dohody.';

    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: message as string,
      life: 5000,
    });
  }
}

onMounted(() => {
  if (!currentPatient.value) {
    toast.add({
      severity: 'warn',
      summary: 'Chýbajúci pacient',
      detail: 'Najprv vyberte pacienta.',
      life: 3000,
    });
  }
});
</script>

<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="generateDocument" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-6">

        <div>
          <label class="block text-normal mb-2">
            Dátum <span class="text-warning">*</span>
          </label>
          <DatePicker
            v-model="date"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0"
          />
          <small v-if="submitted && !isDateValid" class="text-warning">
            Dátum je povinný.
          </small>
        </div>
      </section>

      <div class="flex justify-end">
        <Button
          type="submit"
          :disabled="!currentPatient"
          class="relative flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey px-4 py-2 rounded-md text-white w-100"
        >
          Generovať dokument
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>
  </div>
</template>