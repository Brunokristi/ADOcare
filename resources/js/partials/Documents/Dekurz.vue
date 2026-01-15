<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { usePatientStore } from '@/stores/patientStore'

const patientStore = usePatientStore()

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
  user_name?: string
  company_name?: string
  company_address?: string
  insurance_code?: string
  patient_personal_number?: string
  patient_address?: string
  patient_id: number
  patient_name: string
  dekurz_number: string
  month: string
  sections: { text: string; dates: string[] }[]
  days: DekurzDay[]
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

const dekurz = ref<DekurzData>({
  document_id: 0,
  created_at: '',
  user_id: 0,
  user_name: '',
  company_name: '',
  company_address: '',
  insurance_code: '',
  patient_personal_number: '',
  patient_address: '',
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
  const parts = String(v).split(' ')
  if (parts.length < 2) return ''
  const timePart = parts[1] ?? ''
  const [hh, mm] = timePart.split(':')
  if (!hh || !mm) return ''
  return `${hh}:${mm}`
}

function safeText(t?: string) {
  return (t ?? '').trim()
}

/**
 * We still "compute" rows only for DISPLAY formatting:
 * - date formatting
 * - time formatting
 * - newline normalization
 *
 * We DO NOT derive any business data from document/user/patient anymore.
 */
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
      nurseName: dekurz.value.user_name || '',
    }
  })
})

/** Pagination (measures <tr> heights in hidden table) */
const pagedRows = ref<DekurzRow[][]>([])

const measurePageInnerRef = ref<HTMLElement | null>(null)
const measureHeaderRef = ref<HTMLElement | null>(null) // header wrapper (tables + content header row)
const measureItemsWrapRef = ref<HTMLElement | null>(null) // tbody containing ONLY item <tr>s

const baseDekurzNumber = computed(() => {
  const n = Number(dekurz.value.dekurz_number ?? 1)
  return Number.isFinite(n) && n > 0 ? n : 1
})

function pageDekurzNumber(pageIdx: number) {
  return baseDekurzNumber.value + pageIdx
}

const lastDekurzNumber = computed(() => {
  const pages = pagedRows.value.length
  if (!pages) return baseDekurzNumber.value
  return baseDekurzNumber.value + (pages - 1)
})



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
  const headerWrap = measureHeaderRef.value
  const itemsBody = measureItemsWrapRef.value

  if (!inner || !itemsBody) {
    pagedRows.value = rows.value.length ? [rows.value] : []
    return
  }

  const innerHeight = inner.clientHeight
    const headerHeight = headerWrap ? outerHeightWithMargins(headerWrap) : 0

  // You can reduce this if you want more entries per page.
  const SAFETY = 8
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

async function persistLastDekurzNumber() {
  const patientId = dekurz.value.patient_id
  if (!patientId) return

  const valueToStore = String(lastDekurzNumber.value)

  try {
    // adjust URL to match your API routing
    await api.put(`/v1/patients/${patientId}`, {
      dekurz_number: valueToStore,
    })
    await patientStore.fetchPatient(patientId)

  } catch (e) {
    console.error('Failed to update patient dekurz_number:', e)
  }
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

  await persistLastDekurzNumber()

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
            <div class="text-center font-bold text-lg">
              DEKURZ OŠETROVATEĽSKEJ STAROSTLIVOSTI
            </div>

            <!-- HEADER TABLE -->
            <table class="w-full border-collapse dekurz-table table-fixed mb-2">
              <colgroup>
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
              </colgroup>

              <tbody>
                <tr>
                  <td class="border border-black p-2" colspan="4">
                    <div class="text-normal"><strong>{{ dekurz.company_name }}</strong></div>
                    <div class="text-normal">{{ dekurz.company_address }}</div>
                    <div class="text-normal">Agentúra domácej ošetrovateľskej starostlivosti</div>
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2 align-top w-2/4" colspan="2">
                    <div class="text-normal">Meno, priezvisko, titul pacienta/pacientky:</div>
                    <div class="font-normal"><strong>{{ dekurz.patient_name }}</strong></div>
                  </td>

                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-normal">Rodné číslo:</div>
                    <div class="font-normal"><strong>{{ dekurz.patient_personal_number || '—' }}</strong></div>
                  </td>

                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-normal">Poisťovňa:</div>
                    <div class="font-normal"><strong>{{ dekurz.insurance_code || '—' }}</strong></div>
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2 align-top w-3/4" colspan="3">
                    <div class="text-normal">Adresa pacienta/pacientky:</div>
                    <div class="font-normal"><strong>{{ dekurz.patient_address }}</strong></div>
                  </td>

                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-normal">Poradové číslo dekurzu:</div>
                    <div class="font-normal"><strong>{{ pageDekurzNumber(pageIdx) }}</strong></div>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- CONTENT TABLE -->
            <table class="w-full border-collapse dekurz-table table-fixed">
              <colgroup>
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
              </colgroup>

              <tbody>
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

                <tr v-for="row in page" :key="row.date">
                  <td class="border border-black p-2 align-top w-1/4">
                    <div class="whitespace-pre-line text-normal">{{ row.leftDateTime }}</div>
                  </td>

                  <td class="border border-black p-2 align-top w-3/4" colspan="3">
                    <div class="text-normal leading-snug">
                      <span class="font-normal">{{ row.rightTime }}: </span>
                      <span class="whitespace-pre-line">{{ row.text }}</span>
                    </div>

                    <div class="mt-2 flex items-end gap-4">
                      <div class="font-normal"><strong>{{ row.nurseName }}</strong></div>
                    </div>
                  </td>
                </tr>

                <tr v-if="!page.length">
                  <td class="border border-black p-4 text-sm" colspan="4">Žiadne záznamy.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- HIDDEN MEASURER -->
        <div id="measure-root" aria-hidden="true">
        <div class="dekurz-page measure-page">
            <div ref="measurePageInnerRef" class="page-inner">
            <div class="text-center font-bold text-lg">
                DEKURZ OŠETROVATEĽSKEJ STAROSTLIVOSTI
            </div>

            <!-- IMPORTANT: one table, header tbody + items tbody -->
            <table class="w-full border-collapse dekurz-table table-fixed">
                <colgroup>
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
                <col class="w-1/4" />
                </colgroup>

                <!-- HEADER (provider/patient/address + column header row) -->
                <tbody ref="measureHeaderRef">
                <tr>
                    <td class="border border-black p-2" colspan="4">
                    <div class="text-normal">{{ dekurz.company_name }}</div>
                    <div class="text-normal">{{ dekurz.company_address }}</div>
                    <div class="text-normal">Agentúra domácej ošetrovateľskej starostlivosti</div>
                    </td>
                </tr>

                <tr>
                    <td class="border border-black p-2 align-top w-2/4" colspan="2">
                    <div class="text-normal">Meno, priezvisko, titul pacienta/pacientky:</div>
                    <div class="font-bold">{{ dekurz.patient_name }}</div>
                    </td>

                    <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-normal">Rodné číslo:</div>
                    <div class="font-bold">{{ dekurz.patient_personal_number || '—' }}</div>
                    </td>

                    <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-normal">Poisťovňa:</div>
                    <div class="font-bold">{{ dekurz.insurance_code || '—' }}</div>
                    </td>
                </tr>

                <tr>
                    <td class="border border-black p-2 align-top w-3/4" colspan="3">
                    <div class="text-normal">Adresa pacienta/pacientky:</div>
                    <div class="font-bold">{{ dekurz.patient_address }}</div>
                    </td>

                    <td class="border border-black p-2 align-top w-1/4">
                    <div class="text-normal">Poradové číslo dekurzu:</div>
                    <div class="font-bold">{{ dekurz.dekurz_number }}</div>
                    </td>
                </tr>

                <!-- Column headers (must be part of header measurement) -->
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

                <!-- ITEMS (only these rows are measured individually) -->
                <tbody ref="measureItemsWrapRef">
                <tr v-for="row in rows" :key="row.date">
                    <td class="border border-black p-2 align-top w-1/4">
                    <div class="whitespace-pre-line text-normal">{{ row.leftDateTime }}</div>
                    </td>

                    <td class="border border-black p-2 align-top w-3/4" colspan="3">
                    <div class="text-normal leading-snug">
                        <span class="font-normal">{{ row.rightTime }}: </span>
                        <span class="whitespace-pre-line">{{ row.text }}</span>
                    </div>

                    <div class="mt-2 flex items-end gap-4">
                        <div class="font-normal"><strong>{{ row.nurseName }}</strong></div>
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
