<script setup lang="ts">
import { ref, onMounted, watchEffect } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';
import { useUiOverlayStore } from '@/stores/uiOverlay';
import DocumentShell from '@/components/DocumentShell.vue';

interface CPData {
    company_name: string;
    user_id: number;
    ico: string;
    city: string;
    user_name: string;
  job_title?: string;
    start_date: string;
    end_date: string;
  trip_purpose?: string;
    month: string;
    year: string;
    car_model: string;
    car_license_plate: string;
    representative_name: string;
    representative_id: number | null;
    lastday_previous_month: string;
}

const route = useRoute();
const loading = ref(false);
const uiOverlayStore = useUiOverlayStore();
const previewUrl = ref<string>('');

const cpData = ref<CPData>({
    company_name: '',
    user_id: 0,
    ico: '',
    city: '',
    user_name: '',
    job_title: 'Terénna zdravotná sestra',
    start_date: '',
    end_date: '',
    trip_purpose: 'Zdravotná starostlivosť o pacientov v domácom prostredí',
    month: '',
    year: '',
    car_model: '',
    car_license_plate: '',
    representative_name: '',
    representative_id: null,
    lastday_previous_month: '',
});

onMounted(async () => {
  await loadCP(String(route.params.documentId));
});

watchEffect(() => {
  uiOverlayStore.setContentLoading(loading.value);
});

async function loadCP(documentId: string) {
  loading.value = true;

  try {
    const res = await api.get(`/v1/cps/${documentId}`);
    const cp = res.data?.data?.cp_data ?? {};

    cpData.value = {
        company_name: cp.company_name ?? '',
        ico: cp.ico ?? '',
        user_id: cp.user_id ?? '',
        city: cp.city ?? '',
        user_name: cp.user_name ?? '',
        job_title: cp.job_title ?? 'Terénna zdravotná sestra',
        start_date: cp.start_date ?? '',
        end_date: cp.end_date ?? '',
        trip_purpose: cp.trip_purpose ?? 'Zdravotná starostlivosť o pacientov v domácom prostredí',
        month: cp.month ?? '',
        year: cp.year ?? '',
        car_model: cp.car_model ?? '',
        car_license_plate: cp.car_license_plate ?? '',
        representative_name: cp.representative_name ?? '',
        representative_id: cp.representative_id ?? null,
        lastday_previous_month: cp.lastday_previous_month ?? '',
    };

    await loadPreviewUrl(documentId);
    console.log('Loaded CP data:', cpData.value);
  } catch (error) {
    console.error('Failed to load agreement:', error);
  } finally {
    loading.value = false;
  }
}

async function loadPreviewUrl(documentId: string) {
  try {
    const res = await api.get(`/v1/cps/${documentId}/preview-url`);
    previewUrl.value = res.data?.data?.preview_url ?? '';
  } catch (error) {
    console.error('Failed to load CP preview URL:', error);
    previewUrl.value = '';
  }
}

</script>

<template>
  <DocumentShell
    title="Cestovný príkaz"
    :previewUrl="previewUrl"
    :downloadOptions="[
      {
        url: `/api/v1/cps/${route.params.documentId}/download`,
        fileType: 'PDF',
        contentType: 'application/pdf',
      },
    ]"
    :showPrintButton="true"
  />
</template>


