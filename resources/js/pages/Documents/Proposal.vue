<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';
import LoadingOverlay from '@/components/LoadingOverlay.vue';

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
  doctorDiagnoses: string[];
  nurseDiagnoses: string[];

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
  doctorDiagnoses: [],
  nurseDiagnoses: [],
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
    const proposal = res.data.data?.proposal_data ?? {};

    // Handle both single string and array formats for diagnoses
    const doctorDiagnoses = Array.isArray(proposal.diagnosis)
      ? proposal.diagnosis.map((d: any) => (typeof d === 'string' ? d : d.description ?? ''))
      : (proposal.diagnosis ? [proposal.diagnosis] : []);

    const nurseDiagnoses = Array.isArray(proposal.nurse_diagnosis)
      ? proposal.nurse_diagnosis.map((d: any) => (typeof d === 'string' ? d : d.description ?? ''))
      : (proposal.nurse_diagnosis ? [proposal.nurse_diagnosis] : []);

    documentData.value = {
      facilityName: proposal.company_name ?? '',
      facilityAddress: proposal.company_address ?? '',
      patientName: proposal.patient_name ?? '',
      patientIdNumber: proposal.patient_birth_number ?? '',
      patientHealthCode: proposal.insurance_code ?? '',
      patientCurrentAddress: proposal.patient_address ?? '',
      patientPreviousAddress: proposal.patient_previous_address ?? '',
      prescriptionNote: proposal.epicrisis ?? '',
      doctorDiagnoses,
      nurseDiagnoses,
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
    weekdays: 'V pracovné dni',
    weekends: 'Počas víkendov a sviatkov',
    as_needed: 'Podľa potreby',
  };
  return frequencyMap[value] ?? value;
}

function formatProcedures(procs: any[]) {
  return procs.map(p => `${p.code} – ${translateFrequency(p.frequency)}`).join(', ');
}

function formatDate(v?: string) {
  if (!v) return '';
  return new Date(v).toLocaleDateString('sk-SK');
}

async function printPage() {
  await nextTick()
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  const src = document.getElementById('proposal-sheet')
  if (!src) return

  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.style.opacity = '0'
  document.body.appendChild(iframe)

  const doc = iframe.contentDocument || iframe.contentWindow?.document
  const win = iframe.contentWindow
  if (!doc || !win) {
    document.body.removeChild(iframe)
    return
  }

  const headPieces: string[] = []

  document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
    const href = (link as HTMLLinkElement).href
    if (href) headPieces.push(`<link rel="stylesheet" href="${href}">`)
  })

  document.querySelectorAll('style').forEach(style => {
    headPieces.push(`<style>${style.innerHTML}</style>`)
  })

  headPieces.push(`
    <style>
      @page { size: A4; margin: 0; }
      html, body { margin: 0; padding: 0; background: #fff; }
      * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

      /* make it printable normally */
      #proposal-sheet {
        position: static !important;
        inset: auto !important;
        margin: 0 auto !important;
        box-shadow: none !important;
        width: 210mm !important;
        height: 297mm !important;
        overflow: hidden !important;
      }
    </style>
  `)

  doc.open()
  doc.write(`
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8" />
        ${headPieces.join('\n')}
      </head>
      <body>
        ${src.outerHTML}
      </body>
    </html>
  `)
  doc.close()

  const links = Array.from(doc.querySelectorAll('link[rel="stylesheet"]')) as HTMLLinkElement[]
  await Promise.all(
    links.map(
      l =>
        new Promise<void>(resolve => {
          if ((l as any).sheet) return resolve()
          l.addEventListener('load', () => resolve(), { once: true })
          l.addEventListener('error', () => resolve(), { once: true })
        })
    )
  )

  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  win.focus()

  // Some Chrome builds behave better with a tiny delay
  setTimeout(() => win.print(), 0)

  setTimeout(() => {
    try {
      document.body.removeChild(iframe)
    } catch {}
  }, 500)
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


    <div v-if="!loading" class="proposal-sheet-wrapper">
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
                    Bydlisko prechodné:<br />
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
                  <td class="border border-black p-2 w-1/2">
                    Lekárska diagnóza:<br />
                    <strong>
                      {{ documentData.doctorDiagnoses.length > 0
                        ? documentData.doctorDiagnoses.map((d: string) => d.split(' - ')[0]).join(', ')
                        : '-'
                      }}
                    </strong>
                  </td>
                  <td class="border border-black p-2 w-1/2">
                    Sesterská diagnóza:<br />
                    <strong>
                      {{ documentData.nurseDiagnoses.length > 0
                        ? documentData.nurseDiagnoses.map((d: string) => d.split(' - ')[0]).join(', ')
                        : '-'
                      }}
                    </strong>
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
                podpis odborného zástupcu poskytovateľa ošetrovateľskej starostlivosti a pečiatka
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
    position: fixed !important;
    inset: 0 !important;
    margin: 0 auto!important;
    box-shadow: none !important;
  }

  .no-print,
  .p-toolbar {
    display: none !important;
  }
}

</style>
