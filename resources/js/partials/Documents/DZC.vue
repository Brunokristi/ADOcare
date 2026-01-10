<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

type PatientAddress = {
  address: string
  latitude: number
  longitude: number
}

type PatientAddressesByDate = Record<string, PatientAddress[]>

interface CPData {
  user_id: number
  user_name: string
  start_date: string
  end_date: string
  month: string
  year: string
  car_model: string
  car_license_plate: string
  branch_address: string
  patient_addresses: PatientAddressesByDate
}

type DailyRecord = {
  date: string
  addresses: PatientAddress[]
}

const route = useRoute()
const loading = ref(false)
const isPrinting = ref(false)

const cpData = ref<CPData>({
  user_id: 0,
  user_name: '',
  start_date: '',
  end_date: '',
  month: '',
  year: '',
  car_model: '',
  car_license_plate: '',
  branch_address: '',
  patient_addresses: {},
})

onMounted(async () => {
  await loadCP(String(route.params.documentId))
})

async function loadCP(documentId: string) {
  loading.value = true
  try {
    const res = await api.get(`/v1/dzcs/${documentId}`)
    const cp = res.data?.dzc_data ?? {}

    cpData.value = {
      user_id: Number(cp.user_id ?? 0),
      user_name: cp.user_name ?? '',
      start_date: cp.start_date ?? '',
      end_date: cp.end_date ?? '',
      month: String(cp.month ?? ''),
      year: String(cp.year ?? ''),
      car_model: cp.car_model ?? '',
      car_license_plate: cp.car_license_plate ?? '',
      branch_address: cp.branch_address ?? '',
      patient_addresses: cp.patient_addresses ?? {},
    }
  } catch (error) {
    console.error('Failed to load DZC:', error)
  } finally {
    loading.value = false
  }
}

function formatDate(v?: string) {
  if (!v) return ''
  return new Date(v).toLocaleDateString('sk-SK')
}

function uniqueByAddress(list: PatientAddress[]) {
  const seen = new Set<string>()
  const out: PatientAddress[] = []
  for (const a of list || []) {
    const key = (a.address || '').trim()
    if (!key) continue
    if (seen.has(key)) continue
    seen.add(key)
    out.push(a)
  }
  return out
}

const dailyRecords = computed<DailyRecord[]>(() => {
  const dates = Object.keys(cpData.value.patient_addresses || {}).sort()
  return dates.map(date => ({
    date,
    addresses: uniqueByAddress(cpData.value.patient_addresses[date] || []),
  }))
})

/**
 * DOM-based pagination (tight, non-overflowing)
 * - measures real rendered heights
 * - accounts for margins (mb-4), header (only page 1), footer (every page), and browser rounding
 */
const pagedRecords = ref<DailyRecord[][]>([])

const measurePageInnerRef = ref<HTMLElement | null>(null)
const measureHeaderRef = ref<HTMLElement | null>(null)
const measureItemsWrapRef = ref<HTMLElement | null>(null)
const measureFooterRef = ref<HTMLElement | null>(null)

function outerHeightWithMargins(el: HTMLElement) {
  const style = window.getComputedStyle(el)
  const mt = parseFloat(style.marginTop || '0')
  const mb = parseFloat(style.marginBottom || '0')
  return el.getBoundingClientRect().height + mt + mb
}

async function recalcPagination() {
  await nextTick()
  // Ensure layout is final (fonts/wrapping/table layout)
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  const inner = measurePageInnerRef.value
  const headerEl = measureHeaderRef.value
  const itemsWrap = measureItemsWrapRef.value
  const footerEl = measureFooterRef.value

  if (!inner || !itemsWrap) {
    pagedRecords.value = dailyRecords.value.length ? [dailyRecords.value] : []
    return
  }

  // usable inner height (inside the page padding)
  const innerHeight = inner.clientHeight

  const footerHeight = footerEl ? outerHeightWithMargins(footerEl) : 0
  const headerHeight = headerEl ? outerHeightWithMargins(headerEl) : 0

  // safety buffer (prevents 1-2px overflow across browsers / scaling)
  const SAFETY = 18

  const firstPageCapacity = innerHeight - headerHeight - footerHeight - SAFETY
  const otherPageCapacity = innerHeight - footerHeight - SAFETY

  // Measure all record blocks (including their margins)
  const itemEls = Array.from(itemsWrap.children) as HTMLElement[]
  const heights = itemEls.map(el => outerHeightWithMargins(el))

  const src = dailyRecords.value
  const pages: DailyRecord[][] = []

  let current: DailyRecord[] = []
  let used = 0
  let capacity = firstPageCapacity

  for (let i = 0; i < src.length; i++) {
    const h = heights[i] ?? 0
    const record = src[i]

    if (!record) continue

    if (current.length && used + h > capacity) {
      pages.push(current)
      current = []
      used = 0
      capacity = otherPageCapacity
    }

    current.push(record)
    used += h
  }

  if (current.length) pages.push(current)
  pagedRecords.value = pages
}

watch(
  () => dailyRecords.value,
  async () => {
    await recalcPagination()
  },
  { deep: true, immediate: true }
)

async function printPage() {
  // Ensure the pages shown on screen are the ones printed
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
    <!-- Toolbar -->
    <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
      <template #start>
        <span class="text-heading-accent">Denný záznam ciest</span>
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
        <div v-for="(page, pageIdx) in pagedRecords" :key="pageIdx" class="travel-page">
          <!-- Header only on first page -->
          <div v-if="pageIdx === 0">
            <div class="text-center font-bold text-lg mb-2">DENNÝ ZÁZNAM CIEST</div>

            <div class="grid grid-cols-2 gap-2 text-sm mb-2">
              <div class="border border-black p-2">
                <strong>Obdobie:</strong>
                {{ cpData.month }}/{{ cpData.year }}
              </div>
              <div class="border border-black p-2">
                <strong>Pracovník:</strong>
                {{ cpData.user_name }}
              </div>
            </div>
          </div>

          <div v-for="record in page" :key="record.date" class="mb-4 dzc-block">
            <table class="w-full border-collapse text-xs">
              <thead>
                <tr>
                  <td class="border border-black p-2 text-left w-[85px]">
                    <strong>Dátum</strong><br />
                    {{ formatDate(record.date) }}
                  </td>
                  <td class="border border-black p-2 text-left w-[150px]">
                    <strong>Účel cesty</strong><br />
                    Návšteva pacienta
                  </td>
                  <td class="border border-black p-2 text-left w-[95px]">
                    <strong>Počet km</strong><br />
                  </td>
                  <td class="border border-black p-2 text-left w-[120px]">
                    <strong>Odchod / Príchod</strong><br />
                  </td>
                  <td class="border border-black p-2 text-left w-[120px]">
                    <strong>Prostriedok</strong><br />
                    {{ cpData.car_model }}<br />
                    {{ cpData.car_license_plate }}
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2 text-left w-full" colspan="5">
                    <strong>Trasa</strong><br />
                    <div v-for="(addr, idx) in record.addresses" :key="idx" class="mb-1">
                      {{ addr.address }}
                    </div>
                  </td>
                </tr>
              </thead>
            </table>
          </div>

          <div class="text-xs opacity-70 mt-2">
            Strana {{ pageIdx + 1 }} / {{ pagedRecords.length }}
          </div>
        </div>
      </div>

      <!-- HIDDEN MEASURER (for accurate pagination) -->
      <div id="measure-root" aria-hidden="true">
        <div ref="measurePageRef" class="travel-page measure-page">
          <div ref="measurePageInnerRef" class="page-inner">
            <div ref="measureHeaderRef">
              <div class="text-center font-bold text-lg mb-2">DENNÝ ZÁZNAM CIEST</div>
              <div class="grid grid-cols-2 gap-2 text-sm mb-2">
                <div class="border border-black p-2">
                  <strong>Obdobie:</strong>
                  {{ cpData.month }}/{{ cpData.year }}
                </div>
                <div class="border border-black p-2">
                  <strong>Pracovník:</strong>
                  {{ cpData.user_name }}
                </div>
              </div>
            </div>

            <div ref="measureItemsWrapRef">
              <div v-for="record in dailyRecords" :key="record.date" class="mb-4 dzc-block">
                <table class="w-full border-collapse text-xs">
                  <thead>
                    <tr>
                      <td class="border border-black p-2 text-left w-[85px]">
                        <strong>Dátum</strong><br />
                        {{ formatDate(record.date) }}
                      </td>
                      <td class="border border-black p-2 text-left w-[150px]">
                        <strong>Účel cesty</strong><br />
                        Návšteva pacienta
                      </td>
                      <td class="border border-black p-2 text-left w-[95px]">
                        <strong>Najazdené km</strong><br />
                      </td>
                      <td class="border border-black p-2 text-left w-[120px]">
                        <strong>Odchod / Príchod</strong><br />
                      </td>
                      <td class="border border-black p-2 text-left w-[120px]">
                        <strong>Prostriedok</strong><br />
                        {{ cpData.car_model }}<br />
                        {{ cpData.car_license_plate }}
                      </td>
                    </tr>

                    <tr>
                      <td class="border border-black p-2 text-left w-full" colspan="5">
                        <strong>Trasa</strong><br />
                        <div v-for="(addr, idx) in record.addresses" :key="idx" class="mb-1">
                          {{ addr.address }}
                        </div>
                      </td>
                    </tr>
                  </thead>
                </table>
              </div>
            </div>

            <div ref="measureFooterRef" class="text-xs opacity-70 mt-2">
              Strana 1 / 1
            </div>
          </div>
        </div>
      </div>
      <!-- /measurer -->
    </div>
  </div>
</template>

<style scoped>
/* Each page is an A4 sheet */
.travel-page {
  width: 210mm;
  height: 297mm;
  margin: 0 auto;
  background: white;
  box-sizing: border-box;
  padding: 14mm;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.page-inner {
  height: 100%;
}

.agreement-sheet-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
  gap: 2rem;
  flex-wrap: wrap;
}

/* Hide measurer but keep renderable */
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

  :global(.travel-page) {
    box-shadow: none !important;
    margin: 0 auto !important;
    break-after: page !important;
    page-break-after: always !important;
  }

  :global(.travel-page:last-child) {
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
