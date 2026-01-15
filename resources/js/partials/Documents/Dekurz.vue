<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

type DekurzDay = {
  date: string
  text: string
  terrain_time?: string
  administrative_time?: string
}

type DekurzData = {
  document_id: number
  created_at: string
  user_id: number
  patient_id: number
  patient_name: string
  dekurz_number: string
  month: string
  sections: { text: string; dates: string[] }[]
  days: DekurzDay[]
}

type DocumentPayload = {
  id: number
  user?: any
  patient?: any
}

type DekurzRow = {
  date: string
  leftDateTime: string
  rightTime: string
  text: string
  nurseName: string
}

const route = useRoute()
const loading = ref(false)
const isPrinting = ref(false)

const document = ref<DocumentPayload | null>(null)
const dekurz = ref<DekurzData>({
  document_id: 0,
  created_at: '',
  user_id: 0,
  patient_id: 0,
  patient_name: '',
  dekurz_number: '1',
  month: '',
  sections: [],
  days: [],
})

onMounted(async () => {
  await loadDekurz(String(route.params.documentId))
})

async function loadDekurz(documentId: string) {
  loading.value = true
  try {
    const res = await api.get(`/v1/dekurz/${documentId}`)
    document.value = res.data?.document ?? null
    dekurz.value = res.data?.dekurz_data ?? dekurz.value
  } catch (error) {
    console.error('Failed to load Dekurz:', error)
  } finally {
    loading.value = false
  }
}

function formatDateSK(v?: string) {
  if (!v) return ''
  return new Date(v).toLocaleDateString('sk-SK')
}

function formatTimeSKFromDatetime(v?: string) {
  if (!v) return ''
  const parts = v.split(' ')
  if (parts.length < 2) return ''
  const timePart = parts[1] ?? ''
  const [hh, mm] = timePart.split(':')
  if (!hh || !mm) return ''
  return `${hh}:${mm}`
}

function safeText(t?: string) {
  return (t ?? '').trim()
}

const nurseName = computed(() => {
  const u = document.value?.user
  return (
    u?.full_name ??
    u?.name ??
    u?.username ??
    u?.email ??
    (dekurz.value.user_id ? `ID ${dekurz.value.user_id}` : '')
  )
})

const patientPersonalNumber = computed(() => {
  const p = document.value?.patient
  return p?.personal_number ?? p?.rodne_cislo ?? p?.birth_number ?? ''
})

const patientAddress = computed(() => {
  const p = document.value?.patient
  return p?.address ?? [p?.street, p?.city, p?.country].filter(Boolean).join(', ') ?? ''
})

const insurerCode = computed(() => {
  const p = document.value?.patient
  return p?.insurance_company_code ?? p?.insurer_code ?? p?.insurance_code ?? ''
})

const providerBlock = computed(() => {
  return {
    name: 'Andramed, o.z.',
    line1: 'SNP 8, Fiľakovo',
    line2: 'ADOS',
  }
})

const basePageNumber = computed(() => {
  const n = Number(dekurz.value.dekurz_number ?? 1)
  return Number.isFinite(n) && n > 0 ? n : 1
})

const rows = computed<DekurzRow[]>(() => {
  const src = [...(dekurz.value.days || [])].sort((a, b) => (a.date || '').localeCompare(b.date || ''))
  return src.map(d => {
    const left = d.terrain_time || d.administrative_time || `${d.date} 00:00:00`
    const right = d.administrative_time || d.terrain_time || `${d.date} 00:00:00`

    return {
      date: d.date,
      leftDateTime: `${formatDateSK(d.date)}\n${formatTimeSKFromDatetime(left)}`,
      rightTime: formatTimeSKFromDatetime(right),
      text: safeText(d.text),
      nurseName: nurseName.value,
    }
  })
})

/** Pagination (measures <tr> heights in hidden table) */
const pagedRows = ref<DekurzRow[][]>([])

const measurePageInnerRef = ref<HTMLElement | null>(null)
const measureHeaderRef = ref<HTMLElement | null>(null) // header tbody
const measureItemsWrapRef = ref<HTMLElement | null>(null) // items tbody

function outerHeightWithMargins(el: HTMLElement) {
  const style = window.getComputedStyle(el)
  const mt = parseFloat(style.marginTop || '0')
  const mb = parseFloat(style.marginBottom || '0')
  return el.getBoundingClientRect().height + mt + mb
}

async function recalcPagination() {
  await nextTick()
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  const inner = measurePageInnerRef.value
  const headerBody = measureHeaderRef.value
  const itemsBody = measureItemsWrapRef.value

  if (!inner || !itemsBody) {
    pagedRows.value = rows.value.length ? [rows.value] : []
    return
  }

  const innerHeight = inner.clientHeight
  const headerHeight = headerBody ? outerHeightWithMargins(headerBody) : 0

  const SAFETY = 18
  const capacity = innerHeight - headerHeight - SAFETY

  const itemEls = Array.from(itemsBody.querySelectorAll('tr')) as HTMLElement[]
  const heights = itemEls.map(el => outerHeightWithMargins(el))

  const src = rows.value
  const pages: DekurzRow[][] = []

  let current: DekurzRow[] = []
  let used = 0

  for (let i = 0; i < src.length; i++) {
    const row = src[i]
    const h = heights[i] ?? 0
    if (!row) continue

    if (current.length && used + h > capacity) {
      pages.push(current)
      current = []
      used = 0
    }

    current.push(row)
    used += h
  }

  if (current.length) pages.push(current)
  pagedRows.value = pages
}

watch(
  () => rows.value,
  async () => {
    await recalcPagination()
  },
  { deep: true, immediate: true }
)

async function printPage() {
  isPrinting.value = true
  await nextTick()
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  window.print()
}

function handleAfterPrint() {
  isPrinting.value = false
}

onMounted(() => window.addEventListener('afterprint', handleAfterPrint))
onBeforeUnmount(() => window.removeEventListener('afterprint', handleAfterPrint))
</script>

<template>
  <div class="flex flex-col gap-4">
    <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
      <template #start>
        <span class="text-heading-accent">Dekurz ošetrovateľskej starostlivosti</span>
      </template>
      <template #end>
        <Button
          icon="bi bi-printer"
          class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          @click="printPage"
        />
      </template>
    </Toolbar>

    <div v-if="loading" class="flex justify-center items-center py-20">
      <ProgressSpinner />
    </div>

    <div v-else class="agreement-sheet-wrapper">
      <!-- PRINTED CONTENT -->
      <div id="print-root">
        <div v-for="(page, pageIdx) in pagedRows" :key="pageIdx" class="dekurz-page">
          <div class="page-inner">
            <div class="text-center font-bold text-lg mb-2">
              DEKURZ OŠETROVATEĽSKEJ STAROSTLIVOSTI
            </div>

            <table class="w-full border-collapse dekurz-table table-fixed">
              <!-- 4-column grid so w-*/colspan behaves consistently -->
              <colgroup>
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
              </colgroup>

              <tbody>
                <!-- Provider -->
                <tr>
                  <td class="border border-black p-2" colspan="4">
                    <div>{{ providerBlock.name }}</div>
                    <div>{{ providerBlock.line1 }}</div>
                    <div>{{ providerBlock.line2 }}</div>
                  </td>
                </tr>

                <!-- Patient row (2/4 + 1/4 + 1/4) -->
                <tr>
                  <td class="border border-black p-2 align-top w-2/4" colspan="2">
                    <div class="text-xs">Meno, priezvisko, titul pacienta/pacientky:</div>
                    <div class="font-bold">{{ dekurz.patient_name }}</div>
                    <div class="text-normal">{{ patientAddress }}</div>
                  </td>

                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-xs">Rodné číslo:</div>
                    <div class="font-bold">{{ patientPersonalNumber || '—' }}</div>
                  </td>

                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-xs">Poisťovňa:</div>
                    <div class="font-bold">{{ insurerCode || '—' }}</div>
                  </td>
                </tr>

                <!-- Column headers -->
                <tr>
                  <td class="border border-black p-2 align-top text-xs font-bold w-1/4">
                    Dátum a<br />
                    čas zápisu:
                  </td>

                  <td class="border border-black p-2 align-top text-xs font-bold w-3/4" colspan="3">
                    Rozsah poskytnutej ZS a služieb súvisiacich s poskytnutím ZS, identifikácia
                    ošetrujúceho zdravotného pracovníka (meno, priezvisko, odtlačok pečiatky a podpis)
                  </td>
                </tr>

                <!-- Entries -->
                <tr v-for="row in page" :key="row.date">
                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="whitespace-pre-line text-xs">{{ row.leftDateTime }}</div>
                  </td>

                  <td class="border border-black p-2 align-top w-3/4" colspan="3">
                    <div class="text-sm leading-snug">
                      <span class="font-normal">{{ row.rightTime }}: </span>
                      <span class="whitespace-pre-line">{{ row.text }}</span>
                    </div>

                    <div class="mt-2 flex items-end gap-4">
                      <div class="font-bold">{{ row.nurseName }}</div>
                      <div class="ml-auto whitespace-nowrap">Podpis:</div>
                    </div>
                  </td>
                </tr>

                <tr v-if="!page.length">
                  <td class="border border-black p-4 text-sm" colspan="4">Žiadne záznamy.</td>
                </tr>
              </tbody>
            </table>

            <!-- Optional: page number display (if you want it visible) -->
            <!--
            <div class="text-xs mt-2 text-right">
              Strana: <strong>{{ basePageNumber + pageIdx }}</strong>
            </div>
            -->
          </div>
        </div>
      </div>

      <!-- HIDDEN MEASURER (must mirror table layout for correct pagination) -->
      <div id="measure-root" aria-hidden="true">
        <div class="dekurz-page measure-page">
          <div ref="measurePageInnerRef" class="page-inner">
            <div class="text-center font-bold text-lg mb-2">
              DEKURZ OŠETROVATEĽSKEJ STAROSTLIVOSTI
            </div>

            <table class="w-full border-collapse dekurz-table table-fixed">
              <colgroup>
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
              </colgroup>

              <!-- Header rows measured together -->
              <tbody ref="measureHeaderRef">
                <tr>
                  <td class="border border-black p-2" colspan="4">
                    <div>{{ providerBlock.name }}</div>
                    <div>{{ providerBlock.line1 }}</div>
                    <div>{{ providerBlock.line2 }}</div>
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2 align-top w-2/4" colspan="2">
                    <div class="text-xs">Meno, priezvisko, titul pacienta/pacientky:</div>
                    <div class="font-bold">{{ dekurz.patient_name }}</div>
                    <div class="text-normal">{{ patientAddress }}</div>
                  </td>

                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-xs">Rodné číslo:</div>
                    <div class="font-bold">{{ patientPersonalNumber || '—' }}</div>
                  </td>

                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-xs">Poisťovňa:</div>
                    <div class="font-bold">{{ insurerCode || '—' }}</div>
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2 align-top text-xs font-bold w-1/4">
                    Dátum a<br />
                    čas zápisu:
                  </td>
                  <td class="border border-black p-2 align-top text-xs font-bold w-3/4" colspan="3">
                    Rozsah poskytnutej ZS a služieb súvisiacich s poskytnutím ZS, identifikácia
                    ošetrujúceho zdravotného pracovníka (meno, priezvisko, odtlačok pečiatky a podpis)
                  </td>
                </tr>
              </tbody>

              <!-- Items measured per <tr> -->
              <tbody ref="measureItemsWrapRef">
                <tr v-for="row in rows" :key="row.date">
                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="whitespace-pre-line text-xs">{{ row.leftDateTime }}</div>
                  </td>

                  <td class="border border-black p-2 align-top w-3/4" colspan="3">
                    <div class="text-sm leading-snug">
                      <span class="font-normal">{{ row.rightTime }}: </span>
                      <span class="whitespace-pre-line">{{ row.text }}</span>
                    </div>

                    <div class="mt-2 flex items-end gap-4">
                      <div class="font-bold">{{ row.nurseName }}</div>
                      <div class="ml-auto whitespace-nowrap">Podpis:</div>
                    </div>
                  </td>
                </tr>

                <tr v-if="!rows.length">
                  <td class="border border-black p-4 text-sm" colspan="4">Žiadne záznamy.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <!-- /measurer -->
    </div>
  </div>
</template>

<style scoped>
.dekurz-page {
  width: 210mm;
  height: 297mm;
  margin: 0 auto;
  background: white;
  box-sizing: border-box;
  padding: 12mm;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.page-inner {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.dekurz-table {
  table-layout: fixed;
  width: 100%;
}

.agreement-sheet-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
  gap: 2rem;
  flex-wrap: wrap;
}

#measure-root {
  position: absolute;
  left: -99999px;
  top: 0;
  width: 0;
  height: 0;
  overflow: hidden;
  pointer-events: none;
  opacity: 0;
}

.measure-page {
  opacity: 0;
}

@page {
  size: A4;
  margin: 0;
}

@media print {
  :global(html),
  :global(body) {
    margin: 0 !important;
    padding: 0 !important;
    background: white !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  :global(body) * {
    visibility: hidden !important;
  }

  :global(#print-root),
  :global(#print-root *) {
    visibility: visible !important;
  }

  :global(#print-root) {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
  }

  :global(.dekurz-page) {
    box-shadow: none !important;
    margin: 0 auto !important;
    break-after: page !important;
    page-break-after: always !important;
  }

  :global(.dekurz-page:last-child) {
    break-after: auto !important;
    page-break-after: auto !important;
  }

  :global(.no-print),
  :global(.p-toolbar),
  :global(#measure-root) {
    display: none !important;
  }
}
</style>
