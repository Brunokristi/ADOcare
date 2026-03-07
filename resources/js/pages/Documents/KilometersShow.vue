<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

type CoverSheet = {
  batchNumber: number
  fileName: string
  amount: number
  totalKilometers: number
  periodFrom: string
  periodTo: string
  performedBy: string
  performedDate: string
  companyName: string
  branchName: string
  insuranceName?: string
  patients?: number[]
}

type KilometersBatchPayload = {
  document_id: number
  batchNumber: number
  batchType: { code: string }
  insurance: { id: number }
  period: string[] // [from,to]
  user?: { id: number }
  branch?: { id: number }
  company?: { id: number | null }
  patients?: { id: number }[]
  meta?: {
    fileName?: string
    amount?: number
    totalKilometers?: number
    performedBy?: string
    performedDate?: string
    companyName?: string
    branchName?: string
    insuranceName?: string
  }
}

const route = useRoute()
const documentId = computed(() => Number(route.params.documentId))

const loading = ref(false)
const payload = ref<KilometersBatchPayload | null>(null)

const sheet = computed<CoverSheet>(() => {
  const p = payload.value

  // fallback-safe
  const periodFrom = p?.period?.[0] ?? ''
  const periodTo = p?.period?.[1] ?? ''

  return {
    batchNumber: p?.batchNumber ?? 0,
    fileName: p?.meta?.fileName ?? `davka.${p?.batchNumber ?? 0}.txt`,
    amount: Number(p?.meta?.amount ?? 0),
    totalKilometers: Number(p?.meta?.totalKilometers ?? 0),
    periodFrom,
    periodTo,
    performedBy: String(p?.meta?.performedBy ?? ''),
    performedDate: String(p?.meta?.performedDate ?? ''),
    companyName: String(p?.meta?.companyName ?? ''),
    branchName: String(p?.meta?.branchName ?? ''),
    insuranceName: String(p?.meta?.insuranceName ?? ''),
    patients: (p?.patients ?? []).map(x => x.id),
  }
})

const formattedAmount = computed(
  () =>
    sheet.value.amount.toLocaleString('sk-SK', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }) + '€'
)

const formattedKilometers = computed(
  () =>
    sheet.value.totalKilometers.toLocaleString('sk-SK', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }) + ' km'
)

function formatDate(dateStr: string) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('sk-SK')
}

// function triggerDownload(blob: Blob, filename: string) {
//   const url = URL.createObjectURL(blob)
//   const a = document.createElement('a')
//   a.href = url
//   a.download = filename
//   a.click()
//   URL.revokeObjectURL(url)
// }

async function loadDocument() {
  loading.value = true
  try {
    const res = await api.get(`/v1/kilometers-batches/${documentId.value}`)
    payload.value = (res.data?.data?.kilometers_batch ?? null) as KilometersBatchPayload | null
  } finally {
    loading.value = false
  }
}

function printPage() {
  window.print()
}

function buildDownloadPayloadFromStored(p: KilometersBatchPayload) {
  return {
    batchNumber: p.batchNumber,
    batchType: { code: p.batchType?.code ?? 'N' },
    insurance: { id: p.insurance?.id ?? 0 },
    period: p.period ?? [],
    user: { id: p.user?.id },
    branch: { id: p.branch?.id },
    company: { id: p.company?.id ?? null },
    patients: (p.patients ?? []).map(x => ({ id: x.id })),
  }
}

async function downloadTxt() {
  if (!payload.value) return
  const reqPayload = buildDownloadPayloadFromStored(payload.value)

  const res = await api.post('/v1/batches/kilometers/download', reqPayload, {
    responseType: 'blob',
    headers: { Accept: 'text/plain' },
  })

  const blob = new Blob([res.data], { type: 'text/plain;charset=utf-8' })
  triggerDownload(blob, sheet.value.fileName)
}

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

onMounted(loadDocument)
</script>

<template>
  <div class="flex flex-col gap-4 cover-sheet-page">
    <div class="bg-tag3 justify-between flex items-center p-3! rounded-md">
      <div class="flex items-center gap-2">
        <i class="bi bi-file-earmark" />
        {{ sheet.fileName }}
      </div>

      <div class="flex items-center gap-2">
        <Button icon="bi bi-download" class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          :disabled="loading || !payload" @click="downloadTxt" />
      </div>
    </div>


    <LoadingOverlay :show="loading" text="" />

    <div class="flex flex-col gap-4">
      <!-- Toolbar -->
      <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
        <template #start>
          <span class="text-heading-accent">
            Sprievodný list
          </span>
        </template>

        <template #end>
          <Button icon="bi bi-printer" class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
            @click="printPage" />
        </template>
      </Toolbar>

      <div class="cover-sheet-wrapper">
        <div id="cover-sheet">
          <div class="text-center font-bold text-lg mb-4">
            SPRIEVODNÝ LIST | vykázané kilometre
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
                <td class="border border-black p-2 align-top w-full">
                  <strong>Počet kilometrov:</strong><br />
                  {{ formattedKilometers }}
                </td>
              </tr>

              <tr>
                <td class="border border-black border-b-0 p-2 align-top w-full">
                  <strong>Obdobie:</strong><br />
                  {{ formatDate(sheet.periodFrom) }} - {{ formatDate(sheet.periodTo) }}
                </td>
              </tr>

              <tr>
                <td class="border border-black border-b-0 p-2 align-top w-full">
                  <strong>Poisťovňa:</strong><br />
                  {{ sheet.insuranceName }}
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

          <div v-if="loading" class="mt-4 text-center text-sm text-gray-500">
            Načítavam...
          </div>

          <div v-else-if="!payload" class="mt-4 text-center text-sm text-danger">
            Dokument sa nepodarilo načítať.
          </div>
        </div>
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
    position: fixed !important;
    inset: 0 !important;
    margin: 0 auto !important;
    box-shadow: none !important;
  }

  .no-print,
  .p-toolbar {
    display: none !important;
  }
}
</style>
