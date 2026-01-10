<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
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

function printPage() {
  requestAnimationFrame(() => window.print())
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
 * Pagination (dynamic pages)
 * We estimate how much vertical space each daily block takes.
 * - baseLines: header rows + labels inside the block
 * - plus one line per address (long addresses will still wrap; we approximate by "chars per line")
 */
function estimateLinesForRecord(record: DailyRecord) {
  const baseLines = 11 // date row + branch row + labels/paddings
  const charsPerLine = 80 // tweak if you change font/width
  let addrLines = 0

  for (const a of record.addresses) {
    const len = (a.address || '').length
    addrLines += Math.max(1, Math.ceil(len / charsPerLine))
  }

  return baseLines + addrLines
}

const pagedRecords = computed(() => {
  const pages: DailyRecord[][] = []

  // Rough capacity for A4 with your padding + title + header.
  // Tweak if you change font sizes/padding.
  const pageCapacityLines = 58

  let current: DailyRecord[] = []
  let used = 0

  for (const rec of dailyRecords.value) {
    const need = estimateLinesForRecord(rec)

    // If a single record is huge, force it onto its own page
    if (need > pageCapacityLines) {
      if (current.length) pages.push(current)
      pages.push([rec])
      current = []
      used = 0
      continue
    }

    if (used + need > pageCapacityLines && current.length) {
      pages.push(current)
      current = [rec]
      used = need
    } else {
      current.push(rec)
      used += need
    }
  }

  if (current.length) pages.push(current)
  return pages
})
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
      <!-- Dynamically created pages -->
      <div
        v-for="(page, pageIdx) in pagedRecords"
        :key="pageIdx"
        class="travel-page"
      >
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

        <div v-for="record in page" :key="record.date" class="mb-4">
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
                  <strong>Východzia adresa</strong><br />
                  {{ cpData.branch_address }}
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

.agreement-sheet-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
  gap: 2rem;
  flex-wrap: wrap;
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

  .travel-page,
  .travel-page * {
    visibility: visible !important;
  }

  .travel-page {
    position: relative;
    margin: 0 auto;
    box-shadow: none;
    page-break-after: always;
    break-after: page;
  }

  .travel-page:last-child {
    page-break-after: auto;
    break-after: auto;
  }

  .no-print,
  .p-toolbar {
    display: none !important;
  }
}
</style>
