<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

type PatientAddress = { address: string; latitude: number; longitude: number }
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

type DailyRecord = { date: string; addresses: PatientAddress[] }

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


const dailyRecords = computed<DailyRecord[]>(() => {
  const dates = Object.keys(cpData.value.patient_addresses || {}).sort()
  return dates.map(date => ({
    date,
    addresses: cpData.value.patient_addresses[date] || [],
  }))
})

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
                    <strong>xx</strong>
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
                  </td>
                  <td class="border border-black p-2 text-left w-1/4">
                    <strong>Odchod / Príchod</strong><br />
                  </td>
                </tr>

                <tr>
                  <td class="border border-black p-2 text-left w-full" colspan="4">
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
                      <strong>xx</strong>
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
                      </td>
                      <td class="border border-black p-2 text-left w-1/4">
                        <strong>Odchod / Príchod</strong><br />
                      </td>
                    </tr>

                    <tr>
                      <td class="border border-black p-2 text-left w-full" colspan="4">
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
