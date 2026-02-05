<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';

interface DocumentData {
  facilityName: string;
  facilityAddress: string;

  patientName: string;
  patientIdNumber: string;
  patientHealthCode: string;
  patientCurrentAddress: string;

  userName: string;
  doctorName: string;

  formData: Record<string, any>;
}

const route = useRoute();
const loading = ref(false);

const documentData = ref<DocumentData>({
  facilityName: '',
  facilityAddress: '',
  patientName: '',
  patientIdNumber: '',
  patientHealthCode: '',
  patientCurrentAddress: '',
  userName: '',
  doctorName: '',
  formData: {},
});

onMounted(async () => {
  await loadRecord(String(route.params.documentId));
});

async function loadRecord(documentId: string) {
  loading.value = true;

  try {
    const res = await api.get(`/v1/records/${documentId}`);
    const record = res.data?.record_data ?? {};

    documentData.value = {
      facilityName: record.company_name ?? '',
      facilityAddress: record.company_address ?? '',
      patientName: record.patient_name ?? '',
      patientIdNumber: record.patient_birth_number ?? '',
      patientHealthCode: record.insurance_code ?? '',
      patientCurrentAddress: record.patient_address ?? '',
      userName: record.user_name ?? '',
      doctorName: record.doctor_name ?? '',
      formData: record.form_data ?? {},
    };
  } finally {
    loading.value = false;
  }
}

function formatValue(value: any): string {
  if (Array.isArray(value)) {
    return value.join(', ');
  }
  if (typeof value === 'object' && value !== null) {
    return JSON.stringify(value);
  }
  return String(value ?? '');
}

function printPage() {
  requestAnimationFrame(() => window.print());
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- Toolbar -->
    <Toolbar
      class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print"
    >
      <template #start>
        <span class="text-heading-accent">
          Vstupný záznam sesterského posúdenia zdravotného stavu pacienta
        </span>
      </template>

      <template #end>
        <Button
          icon="bi bi-printer"
          class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          @click="printPage"
        />
      </template>
    </Toolbar>

    <div v-if="!loading" class="record-sheet-wrapper">
      <div id="record-sheet">
        <div class="text-center font-bold text-lg mb-3">
          VSTUPNÝ ZÁZNAM SESTERSKÉHO POSÚDENIA ZDRAVOTNÉHO STAVU PACIENTA
        </div>

        <div class="sheet-grid">
          <div class="top">
            <table class="w-full border-collapse text-sm mb-2">
              <tbody>
                <tr>
                  <td class="border border-black p-2 w-1/2">
                    Zdravotnícke zariadenie:<br />
                    <strong>{{ documentData.facilityName }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/2">
                    so sídlom v:<br />
                    <strong>{{ documentData.facilityAddress }}</strong>
                  </td>
                </tr>
              </tbody>
            </table>

            <table class="w-full border-collapse text-sm mb-2">
              <tbody>
                <tr>
                  <td class="border border-black p-2 w-1/2">
                    Meno, priezvisko, titul pacienta/pacientky:<br />
                    <strong>{{ documentData.patientName }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/4">
                    Rodné číslo:<br />
                    <strong>{{ documentData.patientIdNumber }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/4">
                    Kód ZP:<br />
                    <strong>{{ documentData.patientHealthCode }}</strong>
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-2" colspan="3">
                    Bydlisko:<br />
                    <strong class="clamp-1">{{ documentData.patientCurrentAddress }}</strong>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="middle">
            <div class="overflow-y-auto">
              <table class="w-full border-collapse text-sm">
                <tbody>
                  <tr v-for="(value, key) in documentData.formData" :key="key" class="border-b border-black">
                    <td class="border border-black p-2 w-1/3 font-semibold text-xs">
                      {{ key }}
                    </td>
                    <td class="border border-black p-2 w-2/3 text-xs">
                      {{ formatValue(value) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="footer">
            <table class="w-full border-collapse text-sm">
              <tbody>
                <tr>
                  <td class="border border-black p-2 w-1/2">
                    Ošetrujúci lekár:<br />
                    <strong>{{ documentData.doctorName }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/2">
                    Sestra/Zdravotný pracovník:<br />
                    <strong>{{ documentData.userName }}</strong>
                  </td>
                </tr>
              </tbody>
            </table>

            <div class="mt-12 grid grid-cols-2 gap-12 text-sm">
              <div class="text-center">
                <div class="border-t-1 border-black mb-2"></div>
                podpis lekára a pečiatka
              </div>
              <div class="text-center">
                <div class="border-t-1 border-black mb-2"></div>
                podpis sestry/zdravotného pracovníka a pečiatka
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>


<style>
/* A4 root */
#record-sheet {
  width: 210mm;
  height: 297mm;
  margin: 0 auto;
  background: white;
  box-sizing: border-box;
  padding: 14mm;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.record-sheet-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

/* grid: top fixed, middle fills, footer fixed */
.sheet-grid {
  display: grid;
  grid-template-rows: auto 1fr auto;
  gap: 6px;
  height: 100%;
}

.middle {
  min-height: 0;
  overflow: hidden;
}

.footer {
  min-height: 150px;
}

/* clamp long address a bit */
.clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

@page {
  size: A4;
  margin: 0;
}

@media print {
  body { margin: 0; padding: 0; }
  body * { visibility: hidden !important; }

  #record-sheet,
  #record-sheet * {
    visibility: visible !important;
  }

  #record-sheet {
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    margin: 0 auto;
    box-shadow: none;
  }

  .no-print,
  .p-toolbar {
    display: none !important;
  }
}
</style>
