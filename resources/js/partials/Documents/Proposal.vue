<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';

type PatientCategory = 'H' | 'I' | 'F';
type ExpectedDuration = 'do1mesiac' | 'do3mesiacov' | 'do6mesiacov' | 'nad6mesiacov';

interface DocumentData {
  facilityName: string;
  facilityAddress: string;

  patientName: string;
  patientIdNumber: string;
  patientHealthCode: string;
  patientCurrentAddress: string;
  patientPreviousAddress: string;

  prescriptionNote: string;
  doctorDiagnosis: string;
  nurseDiagnosis: string;

  patientCategory?: PatientCategory;

  carePlan: string;
  treatmentOutcomes: string;

  expectedDuration?: ExpectedDuration;

  doctorName: string;
  documentDate: string;
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
  patientPreviousAddress: '',
  prescriptionNote: '',
  doctorDiagnosis: '',
  nurseDiagnosis: '',
  patientCategory: undefined,
  carePlan: '',
  treatmentOutcomes: '',
  expectedDuration: undefined,
  doctorName: '',
  documentDate: '',
});

onMounted(async () => {
  await loadProposal(String(route.params.documentId));
});

async function loadProposal(documentId: string) {
  loading.value = true;

  try {
    const res = await api.get(`/v1/proposals/${documentId}`);
    const proposal = res.data?.proposal_data ?? {};

    documentData.value = {
      facilityName: proposal.company_name ?? '',
      facilityAddress: proposal.company_address ?? '',
      patientName: proposal.patient_name ?? '',
      patientIdNumber: proposal.patient_birth_number ?? '',
      patientHealthCode: proposal.insurance_code ?? '',
      patientCurrentAddress: proposal.patient_address ?? '',
      patientPreviousAddress: proposal.patient_previous_address ?? '',
      prescriptionNote: proposal.epicrisis ?? '',
      doctorDiagnosis: proposal.diagnosis ?? '',
      nurseDiagnosis: proposal.nurse_diagnosis ?? '',
      patientCategory: Array.isArray(proposal.mobility) ? proposal.mobility[0] : undefined,
      carePlan: proposal.care_plan ?? '',
      treatmentOutcomes: formatProcedures(proposal.procedures ?? []),
      expectedDuration: mapExpectedDuration(proposal.expected_duration),
      doctorName: proposal.doctor_name ?? '',
      documentDate: proposal.date ?? '',
    };
  } finally {
    loading.value = false;
  }
}

function mapExpectedDuration(v?: string): ExpectedDuration | undefined {
  const mapping: Record<string, ExpectedDuration> = {
    one_month: 'do1mesiac',
    three_months: 'do3mesiacov',
    six_months: 'do6mesiacov',
    over_six_months: 'nad6mesiacov',
  };
  return mapping[v ?? ''] ?? undefined;
}

function translateFrequency(value: string): string {
  const frequencyMap: Record<string, string> = {
    daily: 'Denne',
    every_other_day: 'Každý druhý deň',
    three_times_weekly: '3x týždenne',
    twice_weekly: '2x týždenne',
    once_weekly: '1x týždenne',
    twice_monthly: '2x mesačne',
    once_monthly: '1x mesačne',
    as_needed: 'Podľa potreby',
  };
  return frequencyMap[value] ?? value;
}

function formatProcedures(procs: any[]) {
  return procs.map(p => `${p.code} – ${translateFrequency(p.frequency)}`).join('\n');
}

function formatDate(v?: string) {
  if (!v) return '';
  return new Date(v).toLocaleDateString('sk-SK');
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
          Návrh na poskytovanie ošetrovateľskej starostlivosti
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

    <div class="proposal-sheet-wrapper">
      <div id="proposal-sheet">
        <div class="text-center font-bold text-lg mb-3">
          NÁVRH NA POSKYTOVANIE OŠETROVATEĽSKEJ STAROSTLIVOSTI
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

            <table class="w-full border-collapse text-sm mb-2" style="table-layout: fixed;">
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
                    Bydlisko trvalé:<br />
                    <strong class="clamp-1">{{ documentData.patientCurrentAddress }}</strong>
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-2" colspan="3">
                    <strong>Kategória pacienta:</strong>
                    <div class="mt-2 flex flex-col gap-1">
                      <label class="line">
                        <input type="checkbox" :checked="documentData.patientCategory==='H'" disabled />
                        <span>H – pacient/pacientka s obmedzenou pohyblivosťou (50%)</span>
                      </label>
                      <label class="line">
                        <input type="checkbox" :checked="documentData.patientCategory==='I'" disabled />
                        <span>I – imobilný pacient/pacientka (75%)</span>
                      </label>
                      <label class="line">
                        <input type="checkbox" :checked="documentData.patientCategory==='F'" disabled />
                        <span>F – psychiatrická diagnóza / mentálne retardovaný (75%)</span>
                      </label>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <table class="w-full border-collapse text-sm mb-2">
              <tbody>
                <tr>
                  <td class="border border-black p-2">
                    Lekárska diagnóza:<br />
                    <strong>{{ documentData.doctorDiagnosis }}</strong>
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-2">
                    Sesterská diagnóza:<br />
                    <strong>{{ documentData.nurseDiagnosis }}</strong>
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
                    <strong>Epikríza a zdôvodnenie:</strong>
                    <div class="fill-box whitespace-pre-line">
                      {{ documentData.prescriptionNote }}
                    </div>
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-2">
                    <strong>Plán ošetrovateľskej starostlivosti:</strong>
                    <div class="fill-box whitespace-pre-line">
                      {{ documentData.carePlan }}
                    </div>
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-2">
                    <strong>Výkony a frekvencia:</strong>
                    <div class="fill-box whitespace-pre-line">
                      {{ documentData.treatmentOutcomes }}
                    </div>
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-2">
                    <strong>Predpokladaná dĺžka:</strong>
                    <div class="mt-2 flex gap-3 flex-wrap">
                      <label class="line">
                        <input type="radio" :checked="documentData.expectedDuration==='do1mesiac'" disabled />
                        <span>do 1 mesiaca</span>
                      </label>
                      <label class="line">
                        <input type="radio" :checked="documentData.expectedDuration==='do3mesiacov'" disabled />
                        <span>do 3 mesiacov</span>
                      </label>
                      <label class="line">
                        <input type="radio" :checked="documentData.expectedDuration==='do6mesiacov'" disabled />
                        <span>do 6 mesiacov</span>
                      </label>
                      <label class="line">
                        <input type="radio" :checked="documentData.expectedDuration==='nad6mesiacov'" disabled />
                        <span>nad 6 mesiacov</span>
                      </label>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="footer">
            <table class="w-full border-collapse text-sm">
              <tbody>
                <tr>
                  <td class="border border-black p-2 w-3/4">
                    Lekár:<br />
                    <strong>{{ documentData.doctorName }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/4">
                    Dátum:<br />
                    <strong>{{ formatDate(documentData.documentDate) }}</strong>
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
                podpis odborného zástupcu
              </div>
            </div>
          </div>
        </div>
        <!-- /sheet-grid -->
      </div>
    </div>
  </div>
</template>


<style>
/* A4 root */
#proposal-sheet {
  width: 210mm;
  height: 297mm;              /* lock exact page height */
  margin: 0 auto;
  background: white;
  box-sizing: border-box;
  padding: 14mm;              /* smaller than 20mm to gain space */
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
  overflow: hidden;           /* never spill to page 2 */
  display: flex;
  flex-direction: column;
}

.proposal-sheet-wrapper {
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

/* middle contains 3 blocks that share remaining height */
.middle {
  display: grid;
  grid-template-rows: 1fr 1fr 1fr;
  gap: 6px;
  min-height: 0; /* important so children can shrink */
}

.footer {
    min-height: 150px;
}

.block-table {
  height: 100%;
}

.fill-box {
  margin-top: 6px;
  height: calc(100% - 18px); /* space for label line */
  overflow: hidden;
  word-break: break-word;
}

/* compact checkbox/radio lines */
.line {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.line input {
  margin-top: 3px;
}

/* clamp long address a bit */
.clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
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

  #proposal-sheet,
  #proposal-sheet * {
    visibility: visible !important;
  }

  #proposal-sheet {
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
