<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

/* -------------------------------------------------------------------------- */
/*  Types                                                                     */
/* -------------------------------------------------------------------------- */

type PatientAddress = {
  type?: 'branch_start' | 'patient' | 'branch_end'
  patient_id?: number | null

  address: string
  latitude: number
  longitude: number

  arrival_time?: string | null
  departure_time?: string | null

  // leg metrics (previous -> this stop)
  kilometers?: number | null
  distance_to_location_m?: number | null
  time_to_location_seconds?: number | null

  time_on_location_seconds?: number | null
}

type PatientAddressesByDate = Record<string, PatientAddress[]>

type DayTotal = {
  date: string
  stops: number
  travel_seconds: number
  distance_m: number
  distance_km: number
  total_time?: string
  first_arrival?: string | null
  last_arrival?: string | null
}

type MonthTotals = {
  from: string
  to: string
  stops: number
  travel_seconds: number
  distance_m: number
  distance_km: number
}

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

  // NEW: persisted totals
  day_totals?: Record<string, DayTotal>
  month_totals?: MonthTotals | null
}

type DailyRecord = { date: string; addresses: PatientAddress[] }

/* -------------------------------------------------------------------------- */
/*  State                                                                     */
/* -------------------------------------------------------------------------- */

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
  day_totals: {},
  month_totals: null,
})

/* -------------------------------------------------------------------------- */
/*  Load                                                                       */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
  await loadCP(String(route.params.documentId))
})

async function loadCP(documentId: string) {
  loading.value = true
  try {
    const res = await api.get(`/v1/dzcs/${documentId}`)
    const cp = res.data?.data?.dzc_data ?? {}

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
      day_totals: cp.day_totals ?? {},
      month_totals: cp.month_totals ?? null,
    }
  } catch (error) {
    console.error('Failed to load DZC:', error)
  } finally {
    loading.value = false
  }
}

/* -------------------------------------------------------------------------- */
/*  Formatting                                                                 */
/* -------------------------------------------------------------------------- */

function formatDate(v?: string) {
  if (!v) return ''
  return new Date(v).toLocaleDateString('sk-SK')
}

function formatTime(v?: string | null) {
  if (!v) return '-'
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
}

function formatSecondsToHm(seconds?: number | null) {
  if (seconds === null || seconds === undefined) return '-'
  const s = Math.max(0, Math.floor(seconds))
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function sumLegKm(addresses: PatientAddress[]) {
  const km = addresses.reduce((sum, a) => sum + (a.kilometers ?? 0), 0)
  return Math.round(km * 100) / 100
}

/* -------------------------------------------------------------------------- */
/*  Derived records                                                            */
/* -------------------------------------------------------------------------- */

const dailyRecords = computed<DailyRecord[]>(() => {
  const dates = Object.keys(cpData.value.patient_addresses || {}).sort()
  return dates.map(date => ({
    date,
    addresses: cpData.value.patient_addresses[date] || [],
  }))
})

const monthTotalKm = computed(() => {
  const persisted = cpData.value.month_totals?.distance_km
  if (typeof persisted === 'number') return persisted
  return dailyRecords.value.reduce((sum, r) => sum + sumLegKm(r.addresses), 0)
})

/* -------------------------------------------------------------------------- */
/*  Pagination (print pages)                                                   */
/* -------------------------------------------------------------------------- */

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

  const innerHeight = inner.clientHeight
  const footerHeight = footerEl ? outerHeightWithMargins(footerEl) : 0
  const headerHeight = headerEl ? outerHeightWithMargins(headerEl) : 0

  const SAFETY = 18
  const firstPageCapacity = innerHeight - headerHeight - footerHeight - SAFETY
  const otherPageCapacity = innerHeight - footerHeight - SAFETY

  const itemEls = Array.from(itemsWrap.children) as HTMLElement[]
  const heights = itemEls.map(el => outerHeightWithMargins(el))

  const src = dailyRecords.value
  const pages: DailyRecord[][] = []

  let current: DailyRecord[] = []
  let used = 0
  let capacity = firstPageCapacity

  for (let i = 0; i < src.length; i++) {
    const record = src[i]
    const h = heights[i] ?? 0
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

/* -------------------------------------------------------------------------- */
/*  Printing                                                                   */
/* -------------------------------------------------------------------------- */

async function printPage() {
  isPrinting.value = true
  await nextTick()
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  try {
    const src = document.getElementById('print-root')
    if (!src) return

    // Create hidden iframe
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

    // Clone ALL styles (Tailwind/PrimeVue/app CSS) into iframe
    const headPieces: string[] = []

    document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
      const href = (link as HTMLLinkElement).href
      if (href) headPieces.push(`<link rel="stylesheet" href="${href}">`)
    })

    document.querySelectorAll('style').forEach(style => {
      headPieces.push(`<style>${style.innerHTML}</style>`)
    })

    // Print CSS for this page type (.travel-page)
    headPieces.push(`
      <style>
        @page { size: A4; margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* avoid flex pagination issues */
        #print-root { position: static !important; }
        .agreement-sheet-wrapper { display: block !important; padding: 0 !important; }
        
        /* force page breaks */
        .travel-page { break-after: page; page-break-after: always; box-shadow: none !important; margin: 0 !important; }
        .travel-page:last-child { break-after: auto; page-break-after: auto; }
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

    // Wait for linked CSS to load, then print
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
    win.print()

    setTimeout(() => {
      try {
        document.body.removeChild(iframe)
      } catch {}
    }, 500)
  } finally {
    isPrinting.value = false
  }
}

function handleAfterPrint() {
  isPrinting.value = false
}

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

async function downloadCSV() {
  try {
    const documentId = String(route.params.documentId)
    const res = await api.get(`/v1/dzcs/${documentId}/csv`, {
      responseType: 'blob',
      headers: { Accept: 'text/csv' },
    })

    const filename = `dzc_${cpData.value.month}_${cpData.value.year}.csv`
    const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' })
    triggerDownload(blob, filename)
  } catch (error) {
    console.error('Failed to download CSV:', error)
  }
}

onMounted(() => window.addEventListener('afterprint', handleAfterPrint))
onBeforeUnmount(() => window.removeEventListener('afterprint', handleAfterPrint))
</script>

<template>
  <LoadingOverlay :show="loading" text="" />

  <div class="flex flex-col gap-4 cover-sheet-page">
      <div class="bg-tag3 justify-between flex items-center p-3! rounded-md">

      <div class="flex items-center gap-2">
        <i class="bi bi-file-earmark" />
        {{ `dzc_${cpData.month}_${cpData.year}.csv` }}
      </div>

      <Button
        icon="bi bi-download"
        class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
        @click="downloadCSV"
      />
    </div>

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

    <div v-if="!loading" class="agreement-sheet-wrapper">
      <!-- PRINTED CONTENT -->
      <div id="print-root">
        <div v-for="(page, pageIdx) in pagedRecords" :key="pageIdx" class="travel-page">
          <!-- Header only on first page -->
          <div v-if="pageIdx === 0">
            <div class="text-center font-bold text-lg mb-2">DENNÝ ZÁZNAM CIEST</div>

            <table class="w-full border-collapse text-sm mb-4">
              <tbody>
                <tr>
                  <td class="border border-black p-2 w-1/2">
                    Obdobie:<br />
                    <strong>{{ cpData.month }}/{{ cpData.year }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/2">
                    Pracovník:<br />
                    <strong>{{ cpData.user_name }}</strong>
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-2 w-1/2">
                    Celkový počet km:<br />
                    <strong>{{ monthTotalKm ?? '-' }}</strong>
                  </td>
                  <td class="border border-black p-2 w-1/2">
                    Dopravný prostriedok:<br />
                    <strong>{{ cpData.car_model }} - {{ cpData.car_license_plate }}</strong>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Records (ALL pages) -->
          <div v-for="record in page" :key="record.date" class="mb-4 dzc-block">
            <table class="w-full border-collapse text-xs">
              <thead>
                <tr>
                  <td class="border border-black p-2 text-left w-1/4">
                    <strong>Dátum</strong><br />
                    {{ formatDate(record.date) }}
                  </td>
                  <td class="border border-black p-2 text-left w-1/4">
                    <strong>Účel cesty</strong><br />
                    Návšteva pacienta
                  </td>
                  <td class="border border-black p-2 text-left w-1/4">
                    <strong>Počet km</strong><br />
                    {{
                      cpData.day_totals?.[record.date]?.distance_km ??
                      sumLegKm(record.addresses)
                    }}
                  </td>
                  <td class="border border-black p-2 text-left w-1/4">
                    <strong>Trvanie</strong><br />
                      {{ cpData.day_totals?.[record.date]?.total_time ?? '-' }}
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2 text-left w-full" colspan="4">
                    <table class="w-full text-xs mt-2">
                      <tbody>
                        <tr class="border-b border-gray-300">
                          <td class="p-1 w-40 text-left"><strong>Poradové číslo</strong></td>
                          <td class="p-1 flex-1"><strong>Adresa</strong></td>
                          <td class="p-1 w-20 text-center"><strong>Príchod</strong></td>
                          <td class="p-1 w-16 text-right"><strong>KM</strong></td>
                        </tr>

                        <tr v-for="(addr, idx) in record.addresses" :key="idx" class="border-b border-gray-300">
                          <td class="p-1 w-40 text-left">
                            <strong>{{ idx + 1 }}.</strong>
                          </td>

                          <td class="p-1 flex-1">
                            <span v-if="addr.type === 'branch_start'"> </span>
                            <span v-else-if="addr.type === 'branch_end'"> </span>
                            {{ addr.address }}
                          </td>

                          <td class="p-1 w-20 text-center">{{ formatTime(addr.arrival_time ?? null) }}</td>
                          <td class="p-1 w-16 text-right">{{ addr.kilometers ?? '-' }} km</td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      <!-- HIDDEN MEASURER -->
      <div id="measure-root" aria-hidden="true">
        <div class="travel-page measure-page">
          <div ref="measurePageInnerRef" class="page-inner">
            <div ref="measureHeaderRef">
              <div class="text-center font-bold text-lg mb-2">DENNÝ ZÁZNAM CIEST</div>

              <table class="w-full border-collapse text-sm mb-4">
                <tbody>
                  <tr>
                    <td class="border border-black p-2 w-1/2">
                      Obdobie:<br />
                      <strong>{{ cpData.month }}/{{ cpData.year }}</strong>
                    </td>
                    <td class="border border-black p-2 w-1/2">
                      Pracovník:<br />
                      <strong>{{ cpData.user_name }}</strong>
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-black p-2 w-1/2">
                      Celkový počet km:<br />
                      <strong>{{ monthTotalKm ?? '-' }}</strong>
                    </td>
                    <td class="border border-black p-2 w-1/2">
                      Dopravný prostriedok:<br />
                      <strong>{{ cpData.car_model }} - {{ cpData.car_license_plate }}</strong>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div ref="measureItemsWrapRef">
              <div v-for="record in dailyRecords" :key="record.date" class="mb-4 dzc-block">
                <table class="w-full border-collapse text-xs">
                  <thead>
                    <tr>
                      <td class="border border-black p-2 text-left w-1/4">
                        <strong>Dátum</strong><br />
                        {{ formatDate(record.date) }}
                      </td>
                      <td class="border border-black p-2 text-left w-1/4">
                        <strong>Účel cesty</strong><br />
                        Návšteva pacienta
                      </td>
                      <td class="border border-black p-2 text-left w-1/4">
                        <strong>Počet km</strong><br />
                        {{
                          cpData.day_totals?.[record.date]?.distance_km ??
                          sumLegKm(record.addresses)
                        }}
                      </td>
                      <td class="border border-black p-2 text-left w-1/4">
                        <strong>Trvanie</strong><br />
                        <span>
                          
                        </span>
                      </td>
                    </tr>

                    <tr>
                      <td class="border border-black p-2 text-left w-full" colspan="4">
                        <strong>Trasa</strong><br />

                        <table class="w-full text-xs mt-2">
                          <tbody>
                            <tr class="border-b border-gray-300">
                              <td class="p-1 w-20 text-left"><strong>Poradové číslo</strong></td>
                              <td class="p-1 flex-1"><strong>Adresa</strong></td>
                              <td class="p-1 w-20 text-center"><strong>Príchod</strong></td>
                              <td class="p-1 w-16 text-right"><strong>KM</strong></td>
                            </tr>

                            <tr v-for="(addr, idx) in record.addresses" :key="idx" class="border-b border-gray-300">
                              <td class="p-1 w-20 text-left"><strong>{{ idx + 1 }}.</strong></td>
                              <td class="p-1 flex-1">
                                <span v-if="addr.type === 'branch_start'"><strong>Štart:</strong> </span>
                                <span v-else-if="addr.type === 'branch_end'"><strong>Konец:</strong> </span>
                                {{ addr.address }}
                              </td>
                              <td class="p-1 w-20 text-center">{{ formatTime(addr.arrival_time ?? null) }}</td>
                              <td class="p-1 w-20 text-center">{{ formatTime(addr.departure_time ?? null) }}</td>
                              <td class="p-1 w-16 text-center">{{ formatSecondsToHm(addr.time_to_location_seconds ?? null) }}</td>
                              <td class="p-1 w-16 text-right">{{ addr.kilometers ?? '-' }} km</td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                  </thead>
                </table>
              </div>
            </div>

            <div ref="measureFooterRef"></div>
          </div>
        </div>
      </div>
      <!-- /measurer -->
    </div>
  </div>
</template>

<style scoped>
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
</style>
