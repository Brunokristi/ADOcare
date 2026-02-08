<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';
import LoadingOverlay from '@/components/LoadingOverlay.vue';

interface DocumentData {
  patientName: string;
  patientIdNumber: string;
  userName: string;
  date: string;
  problems: string[];
  otherFindings: string;
  results: string;
  education: string;
  received: string;
}

const route = useRoute();
const loading = ref(false);

const documentData = ref<DocumentData>({
  patientName: '',
  patientIdNumber: '',
  userName: '',
  date: '',
  problems: [],
  otherFindings: '',
  results: '',
  education: '',
  received: '',
});

const problemLabels: Record<string, string> = {
  nutrition: 'výživy',
  mobility: 'mobility',
  elimination: 'vylučovania/vyprázdňovania',
  injections: 'aplikácie s. c. inj.',
  hygiene: 'hygieny',
  wound_care: 'starosti o ranu',
  other_findings: 'iné zistenia',
};

onMounted(async () => {
  await loadNursingDocument(String(route.params.documentId));
});

async function loadNursingDocument(documentId: string) {
  loading.value = true;

  try {
    const res = await api.get(`/v1/leave-documents/${documentId}`);
    const leave = res.data?.leave_data ?? {};

    documentData.value = {
      patientName: leave.patient_name ?? '',
      patientIdNumber: leave.patient_birth_number ?? '',
      userName: leave.user_name ?? '',
      date: leave.date ?? '',
      problems: leave.problems ?? [],
      otherFindings: leave.other_findings ?? '',
      results: leave.results ?? '',
      education: leave.education ?? '',
      received: leave.received ?? '',
    };
  } finally {
    loading.value = false;
  }
}

function getProblemLabel(value: string): string {
  return problemLabels[value] ?? value;
}

function formatDateTime(v?: string) {
  if (!v) return '';
  const date = new Date(v);
  return date.toLocaleString('sk-SK');
}

function printPage() {
  requestAnimationFrame(() => window.print());
}
</script>

<template>
  <LoadingOverlay :show="loading" text="" />

  <div class="flex flex-col gap-4">
    <!-- Toolbar -->
    <Toolbar
      class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print"
    >
      <template #start>
        <span class="text-heading-accent">
          Ošetrovateľská prepúšťacia správa
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


    <div v-if="!loading" class="nursing-sheet-wrapper">
      <div id="nursing-sheet">
        <div class="text-center font-bold text-lg mb-3">
          OŠETROVATEĽSKÁ PREPÚŠŤACIA SPRÁVA
        </div>

        <div class="sheet-grid">
          <div class="top">
            <table class="w-full border-collapse text-sm mb-2">
              <tbody>
                <tr>
                  <td class="border border-black p-2 w-1/2">
                    Meno, priezvisko, titul pacienta/pacientky:<br />
                    <strong>{{ documentData.patientName }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/2">
                    Rodné číslo:<br />
                    <strong>{{ documentData.patientIdNumber }}</strong>
                  </td>
                </tr>
              </tbody>
            </table>

            <table class="w-full border-collapse text-sm mb-2">
              <tbody>
                <tr>
                  <td class="border border-black p-2 w-1/2">
                    Dátum:<br />
                    <strong>{{ formatDateTime(documentData.date) }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/2">
                    Zdravotný pracovník:<br />
                    <strong>{{ documentData.userName }}</strong>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="middle">
            <table class="w-full border-collapse text-sm block-table">
              <tbody>
                <tr>
                  <td class="border border-black p-2">
                    <strong>Pretrvávajúce problémy pri prepustení v oblasti sebaopatery:</strong>
                    <div class="mt-2 flex flex-col gap-1 ml-4">
                      <div v-for="problem in documentData.problems" :key="problem" class="text-sm">
                        • {{ getProblemLabel(problem) }}
                      </div>
                    </div>
                  </td>
                </tr>

                <tr v-if="documentData.otherFindings">
                  <td class="border border-black p-2">
                    <strong>Iné zistenia:</strong>
                    <div class="fill-box whitespace-pre-line">
                      {{ documentData.otherFindings }}
                    </div>
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2">
                    <strong>Vyhodnotenie výsledkov ošetrovateľskej starostlivosti:</strong>
                    <div class="fill-box whitespace-pre-line">
                      {{ documentData.results }}
                    </div>
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2">
                    <strong>Realizovaná edukácia o:</strong>
                    <div class="fill-box whitespace-pre-line">
                      {{ documentData.education }}
                    </div>
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2">
                    <strong>Pacient pri ukončení hospitalizácie prevzal:</strong>
                    <div class="fill-box whitespace-pre-line">
                      {{ documentData.received }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <div class="mt-12 grid grid-cols-2 gap-12 text-sm">
              <div class="text-center">
                <div class="border-t-1 border-black mb-2"></div>
                podpis zdravotného pracovníka
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
#nursing-sheet {
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

.nursing-sheet-wrapper {
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

/* middle contains rows that share remaining height */
.middle {
  display: grid;
  grid-template-rows: auto auto auto auto auto;
  gap: 6px;
  min-height: 0;
}

.footer {
  min-height: 150px;
}

.block-table {
  height: 100%;
}

.fill-box {
  margin-top: 6px;
  height: calc(100% - 18px);
  overflow: hidden;
  word-break: break-word;
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

  #nursing-sheet,
  #nursing-sheet * {
    visibility: visible !important;
  }

  #nursing-sheet {
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
