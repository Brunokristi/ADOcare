<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

import Button from 'primevue/button';
import Toolbar from 'primevue/toolbar';

type CoverSheet = {
  batchNumber: number;
  fileName: string;
  amount: number;
  periodFrom: string;
  periodTo: string;
  performedBy: string;
  performedDate: string;
  companyName: string;
  branchName: string;
  patients?: number[];
};

const authStore = useAuthStore();
const route = useRoute();

const batchNumber = computed(() => Number(route.query.batchNumber ?? 0));
const patientIds = computed<number[]>(() => {
  const raw = route.query.patientIds;
  if (!raw) return [];
  try {
    const arr = JSON.parse(String(raw));
    return Array.isArray(arr) ? arr.map((x) => Number(x)).filter((x) => x > 0) : [];
  } catch {
    return [];
  }
});

const sheet = computed<CoverSheet>(() => ({
  batchNumber: batchNumber.value,
  fileName: String(route.query.fileName ?? `davka.${batchNumber.value}.txt`),
  amount: Number(route.query.amount ?? 0),
  periodFrom: String(route.query.periodFrom ?? ''),
  periodTo: String(route.query.periodTo ?? ''),
  performedBy: String(route.query.performedBy ?? ''),
  performedDate: String(route.query.performedDate ?? ''),
  companyName: String(route.query.companyName ?? ''),
  branchName: String(route.query.branchName ?? ''),
  patients: patientIds.value, 
}));


const formattedAmount = computed(
  () =>
    sheet.value.amount.toLocaleString('sk-SK', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }) + '€',
);

function formatDate(dateStr: string) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('sk-SK');
}

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

function buildPayload() {
  const period0 = String(route.query.period0 ?? sheet.value.periodFrom ?? '');
  const period1 = String(route.query.period1 ?? sheet.value.periodTo ?? '');

  return {
    batchNumber: sheet.value.batchNumber,
    batchType: { code: String(route.query.batchTypeCode ?? 'N') },
    insurance: { id: Number(route.query.insuranceId ?? 0) },
    period: [period0, period1],
    user: { id: authStore.user?.id },
    branch: { id: authStore.currentBranch?.id },
    company: { id: authStore.currentBranch?.company_id },
    patients: (sheet.value.patients ?? []).map((id: number) => ({ id })),
  };
}

async function downloadTxt() {
  try {
    const payload = buildPayload();

    const res = await api.post('/v1/batches/download', payload, {
      responseType: 'blob',
      headers: { Accept: 'text/plain' },
    });

    const blob = new Blob([res.data], { type: 'text/plain;charset=utf-8' });
    triggerDownload(blob, sheet.value.fileName);
  } catch (err: any) {
    // show JSON validation errors if backend returns 422 as Blob
    const blob = err?.response?.data;
    if (blob instanceof Blob) {
      console.error('TXT download error:', await blob.text());
    } else {
      console.error('TXT download failed', err);
    }
  }
}

// ✅ Toolbar button: PDF statement
async function downloadStatementPdf() {
  try {
    const payload = buildPayload();

    const res = await api.post('/v1/batches/statement-pdf', payload, {
      responseType: 'blob',
      headers: { Accept: 'application/pdf' },
    });

    const blob = new Blob([res.data], { type: 'application/pdf' });
    const pdfName = `sprievodny_list_${sheet.value.batchNumber}.pdf`;
    triggerDownload(blob, pdfName);
  } catch (err: any) {
    const blob = err?.response?.data;
    if (blob instanceof Blob) {
      console.error('PDF download error:', await blob.text());
    } else {
      console.error('PDF download failed', err);
    }
  }
}

function printPage() {
  window.print();
}
</script>


<template>
  <div class="flex flex-col gap-4 cover-sheet-page">
      <div class="bg-tag3 justify-between flex items-center !p-3 rounded-md">
      
      <div class="flex items-center gap-2">
        <i class="bi bi-file-earmark" />
        {{ sheet.fileName }}
      </div>

      <Button
        icon="bi bi-download"
        class="!bg-accent !border-accent hover:!bg-darkgrey hover:!border-darkgrey !h-7"
        @click="downloadTxt"
      />
    </div>

    <!-- Toolbar -->
    <Toolbar
      class="!bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between"
    >
      <template #start>
        <span class="text-heading-accent">
          Sprievodný list
        </span>
      </template>

      <template #end>
        <div class="flex items-center gap-2">
          <Button
            icon="bi bi-download"
            class="!bg-accent !border-accent hover:!bg-darkgrey hover:!border-darkgrey !h-7"
            @click="downloadStatementPdf"
          />

          <Button
            icon="bi bi-printer"
            class="!bg-accent !border-accent hover:!bg-darkgrey hover:!border-darkgrey !h-7"
            @click="printPage"
          />
        </div>
      </template>
    </Toolbar>

    <div class="cover-sheet-wrapper">
      <div id="cover-sheet">
        <div class="text-center font-bold text-lg mb-4">
          SPRIEVODNÝ LIST
        </div>

        <table class="w-full border-collapse border-b-0 text-sm">
          <tbody>
            <tr>
              <td class="border border-black p-2 align-top text-right">
                Sprievodný list k:
                <strong>{{ sheet.fileName }}</strong>
              </td>
            </tr>

            <tr>
              <td class="border border-black p-2 align-top w-full">
                <strong>Vykázaná suma:</strong><br />
                {{ formattedAmount }}
              </td>
            </tr>

            <tr>
              <td class="border border-black border-b-0 p-2 align-top w-full">
                <strong>Obdobie:</strong><br />
                {{ formatDate(sheet.periodFrom) }} - {{ formatDate(sheet.periodTo) }}
              </td>
            </tr>
          </tbody>
        </table>

        <table class="w-full border-collapse text-sm">
          <tbody>
            <tr>
              <td class="border border-black p-2 align-top">
                <strong>Vykázal:</strong><br />
                {{ sheet.performedBy }}
              </td>
              <td class="border border-black p-2 align-top">
                <strong>Vykázané dňa:</strong><br />
                {{ formatDate(sheet.performedDate) }}
              </td>
            </tr>

            <tr>
              <td class="border border-black p-2 align-top">
                <strong>Spoločnosť:</strong><br />
                {{ sheet.companyName }}
              </td>
              <td class="border border-black p-2 align-top">
                <strong>Prevádzka:</strong><br />
                {{ sheet.branchName }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style>
#cover-sheet {
  width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background: white;
  box-sizing: border-box;
  padding: 20mm;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
}

.cover-sheet-wrapper {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 2rem;
  overflow: auto;
  background: transparent;
}

@page {
  size: A4;
  margin: 0;
}

@media print {
  body {
    margin: 0;
    padding: 0;
  }

  body * {
    visibility: hidden !important;
  }

  #cover-sheet,
  #cover-sheet * {
    visibility: visible !important;
  }

  #cover-sheet {
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    margin: 0 auto;
    box-shadow: none;
  }
}
</style>
