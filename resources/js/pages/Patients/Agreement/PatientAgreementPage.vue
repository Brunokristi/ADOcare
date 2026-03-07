<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { storeToRefs } from 'pinia';
import Button from 'primevue/button';
import api from '@/services/api';
import DocumentAlert from '@/components/DocumentAlert.vue';
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

// Document existence check
const documentExists = ref(false);
const documentId = ref<number | null>(null);
const dialogVisible = ref(false);
const date = ref<Date>(new Date());
const submitted = ref(false);

// Validation
const isDateValid = computed(() => !!date.value);

async function checkDocumentExists() {
  if (!patientId.value || !date.value) return;

  try {
    const res = await api.post('/v1/documents/check-exists', {
      type: 'agreement',
      date: toLocalYMD(date.value),
      patient_id: patientId.value,
    });
    documentExists.value = res.data.exists ?? false;
    documentId.value = res.data.document_id ?? null;
    if (documentExists.value) {
      dialogVisible.value = true;
    }
  } catch (err) {
    console.error('Failed to check document existence:', err);
    documentExists.value = false;
  }
}

watch([() => date.value, () => patientId.value], () => {
  checkDocumentExists();
});

function closeDialog() {
  dialogVisible.value = false;
}

function validateForm(): boolean {
  return isDateValid.value && !!patientId.value;
}

const toLocalYMD = (d: Date) => {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
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
      date: date.value ? toLocalYMD(date.value) : toLocalYMD(new Date()),
      branch_id: currentBranch.value?.id,
    };

    const res = await api.post('/v1/agreements', payload);
    const data = res.data.data;
    if (data?.document_id) {
      toast.add({
        severity: 'success',
        summary: 'Úspešne vytvorené',
        detail: 'Dohoda o poskytovaní zdravotnej starostlivosti bola úspešne vytvorená.',
        life: 3000,
      });

      router.push({
        name: 'documents-agreement',
        params: { documentId: data.document_id },
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
  <DocumentAlert :visible="dialogVisible" :document-id="documentId" document-url="/documents/agreement/{id}"
    @update:visible="dialogVisible = $event" @close="closeDialog" @deleted="checkDocumentExists" />

  <div class="flex flex-col gap-6">
    <form @submit.prevent="generateDocument" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-6">

        <div>
          <label class="block text-normal mb-2">
            Dátum
          </label>
          <DatePicker v-model="date" dateFormat="dd.mm.yy" :showIcon="false" class="w-full"
            inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none !border-0" />
          <small v-if="submitted && !isDateValid" class="text-danger">
            Dátum je povinný.
          </small>
        </div>
      </section>

      <div class="flex justify-end">
        <Button type="submit" :disabled="!currentPatient"
          class="relative flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey px-4 py-2 rounded-md text-white w-100">
          Generovať dokument
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>
  </div>
</template>
