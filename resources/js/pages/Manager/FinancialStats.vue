<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import api from '@/services/api'
import { useUiOverlayStore } from '@/stores/uiOverlay'

type FinancialKpis = {
  invoices_count: number
  credit_notes_count: number
  debit_notes_count: number
  invoice_revenue: number
  credit_notes_total: number
  debit_notes_total: number
  notes_net: number
  notes_absolute: number
  error_percentage: number
  net_revenue: number
  procedures_revenue: number
  transport_revenue: number
  activity_total_revenue: number
}

type FinancialMonthlyRow = {
  month: string
  invoice_revenue: number
  credit_notes_total: number
  debit_notes_total: number
  notes_net: number
  net_revenue: number
  procedures_revenue: number
  transport_revenue: number
}

type FinancialUserRow = {
  user_id: number
  user_name: string
  points_revenue: number
  kilometers_revenue: number
  revenue_total: number
}

type FinancialBranchRow = {
  branch_id: number | null
  branch_name: string
  points_revenue: number
  kilometers_revenue: number
  revenue_total: number
}

type FinancialInsuranceRow = {
  insurance_company_id: number | null
  insurance_company_name: string
  invoice_revenue: number
  credit_notes_total: number
  debit_notes_total: number
  notes_net: number
  net_revenue: number
  documents_count: number
}

type FinancialActivityRow = {
  activity_type: 'points_batch' | 'kilometers_batch'
  activity_name: string
  documents_count: number
  revenue: number
}

type FinancialUserInsuranceCompany = {
  insurance_company_id: number
  insurance_company_name: string
}

type FinancialUserInsuranceItem = {
  insurance_company_id: number
  insurance_company_name: string
  points_revenue: number
  kilometers_revenue: number
  revenue_total: number
}

type FinancialUserInsuranceRow = {
  user_id: number
  user_name: string
  revenue_total: number
  insurances: FinancialUserInsuranceItem[]
}

const uiOverlayStore = useUiOverlayStore()

const getPreviousMonthRange = () => {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth() - 1, 1)
  const end = new Date(now.getFullYear(), now.getMonth() - 1, 1)
  return { start, end }
}

const initialRange = getPreviousMonthRange()
const startDate = ref<Date>(initialRange.start)
const endDate = ref<Date>(initialRange.end)
const submitted = ref(false)
const loading = ref(false)

const kpis = ref<FinancialKpis>({
  invoices_count: 0,
  credit_notes_count: 0,
  debit_notes_count: 0,
  invoice_revenue: 0,
  credit_notes_total: 0,
  debit_notes_total: 0,
  notes_net: 0,
  notes_absolute: 0,
  error_percentage: 0,
  net_revenue: 0,
  procedures_revenue: 0,
  transport_revenue: 0,
  activity_total_revenue: 0,
})

const monthly = ref<FinancialMonthlyRow[]>([])
const byUser = ref<FinancialUserRow[]>([])
const byBranch = ref<FinancialBranchRow[]>([])
const byInsurance = ref<FinancialInsuranceRow[]>([])
const byUserInsuranceCompanies = ref<FinancialUserInsuranceCompany[]>([])
const byUserInsuranceRows = ref<FinancialUserInsuranceRow[]>([])
const activity = ref<FinancialActivityRow[]>([])

const toApiDateParam = (d: Date) => {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const toMonthStart = (date: Date) => new Date(date.getFullYear(), date.getMonth(), 1)
const toNextMonthStart = (date: Date) => new Date(date.getFullYear(), date.getMonth() + 1, 1)

const getRangeParams = () => {
  if (!startDate.value || !endDate.value) return null

  const from = toMonthStart(startDate.value)
  const toExclusive = toNextMonthStart(endDate.value)

  return {
    date_from: toApiDateParam(from),
    date_to: toApiDateParam(toExclusive),
  }
}

function getCssVar(name: string, fallback = '') {
  if (typeof window === 'undefined') return fallback
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback
}

const chartColors = computed(() => {
  return {
    accent: getCssVar('--c-accent', '#5C9EAD'),
    darkgrey: getCssVar('--c-darkgrey', '#333333'),
    lightgrey: getCssVar('--c-light-grey', '#e5e7eb'),
    white: getCssVar('--c-white', '#ffffff'),
    success: getCssVar('--c-success', '#16a34a'),
    danger: getCssVar('--c-danger', '#dc2626'),
    text: getCssVar('--c-darkgrey', '#374151'),
    grid: getCssVar('--c-light-grey', '#e5e7eb'),
  }
})

const topUser = computed(() => byUser.value[0] ?? null)
const topBranch = computed(() => byBranch.value[0] ?? null)
const topInsurance = computed(() => byInsurance.value[0] ?? null)

const topUsersRevenueChartData = computed(() => {
  const rows = byUser.value.slice(0, 8)
  return {
    labels: rows.map((r) => r.user_name),
    datasets: [
      {
        label: 'Aktivita (€)',
        data: rows.map((r) => r.revenue_total ?? 0),
        backgroundColor: chartColors.value.accent,
        borderColor: chartColors.value.accent,
        borderWidth: 1,
        borderRadius: 6,
      },
    ],
  }
})

const topBranchesRevenueChartData = computed(() => {
  const rows = byBranch.value.slice(0, 8)
  return {
    labels: rows.map((r) => r.branch_name),
    datasets: [
      {
        label: 'Tržby (€)',
        data: rows.map((r) => r.revenue_total ?? 0),
        backgroundColor: chartColors.value.accent,
        borderColor: chartColors.value.accent,
        borderWidth: 1,
        borderRadius: 6,
      },
    ],
  }
})

const activitySplitChartData = computed(() => ({
  labels: activity.value.map((a) => a.activity_name),
  datasets: [
    {
      data: activity.value.map((a) => a.revenue ?? 0),
      backgroundColor: [chartColors.value.accent, chartColors.value.darkgrey],
      borderColor: chartColors.value.white,
      borderWidth: 2,
      hoverOffset: 8,
    },
  ],
}))

const horizontalBarOptions = computed(() => ({
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false,
      labels: {
        color: chartColors.value.text,
      },
    },
    tooltip: {
      callbacks: {
        label: (context: { dataset: { label?: string }; parsed?: { x?: number } }) => {
          const label = context.dataset?.label ? `${context.dataset.label}: ` : ''
          return `${label}${formatCurrency(Number(context.parsed?.x ?? 0))}`
        },
      },
    },
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: {
        color: chartColors.value.text,
        callback: (value: number | string) => formatCurrency(Number(value)),
      },
      grid: {
        color: chartColors.value.grid,
      },
    },
    y: {
      ticks: {
        color: chartColors.value.text,
      },
      grid: {
        display: false,
      },
    },
  },
}))

const doughnutOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        color: chartColors.value.text,
      },
    },
    tooltip: {
      callbacks: {
        label: (context: { label?: string; parsed?: number }) => {
          const label = context.label ? `${context.label}: ` : ''
          return `${label}${formatCurrency(Number(context.parsed ?? 0))}`
        },
      },
    },
  },
}))

function formatCurrency(value: number) {
  return Number(value ?? 0).toLocaleString('sk-SK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ' €'
}

function formatPercent(value: number) {
  return Number(value ?? 0).toLocaleString('sk-SK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ' %'
}

function getUserActivityRevenue(row: FinancialUserRow, type: 'points' | 'kilometers') {
  return type === 'points'
    ? Number(row.points_revenue ?? 0)
    : Number(row.kilometers_revenue ?? 0)
}

function getUserActivitySharePercent(row: FinancialUserRow, type: 'points' | 'kilometers') {
  const total = Number(row.revenue_total ?? 0)
  if (total <= 0) return 0

  return Math.min(100, (getUserActivityRevenue(row, type) / total) * 100)
}

function getBranchActivityRevenue(row: FinancialBranchRow, type: 'points' | 'kilometers') {
  return type === 'points'
    ? Number(row.points_revenue ?? 0)
    : Number(row.kilometers_revenue ?? 0)
}

function getBranchActivitySharePercent(row: FinancialBranchRow, type: 'points' | 'kilometers') {
  const total = Number(row.revenue_total ?? 0)
  if (total <= 0) return 0

  return Math.min(100, (getBranchActivityRevenue(row, type) / total) * 100)
}

function getUserInsuranceAmount(row: FinancialUserInsuranceRow, companyId: number) {
  const found = row.insurances?.find((x) => Number(x.insurance_company_id) === Number(companyId))
  return Number(found?.revenue_total ?? 0)
}

function getUserInsuranceSharePercent(row: FinancialUserInsuranceRow, companyId: number) {
  const total = Number(row.revenue_total ?? 0)
  if (total <= 0) return 0

  return Math.min(100, (getUserInsuranceAmount(row, companyId) / total) * 100)
}

async function loadFinancialStatistics() {
  const rangeParams = getRangeParams()
  if (!rangeParams) return

  loading.value = true
  uiOverlayStore.setContentLoading(true)

  try {
    const response = await api.get('/v1/manager/financial-statistics', { params: rangeParams })
    const payload = response.data?.data ?? {}

    kpis.value = {
      ...kpis.value,
      ...(payload.kpis ?? {}),
    }

    monthly.value = (payload.monthly ?? []) as FinancialMonthlyRow[]
    byUser.value = (payload.by_user ?? []) as FinancialUserRow[]
    byBranch.value = (payload.by_branch ?? []) as FinancialBranchRow[]
    byInsurance.value = (payload.by_insurance ?? []) as FinancialInsuranceRow[]
    byUserInsuranceCompanies.value = (payload.by_user_insurance?.companies ?? []) as FinancialUserInsuranceCompany[]
    byUserInsuranceRows.value = (payload.by_user_insurance?.rows ?? []) as FinancialUserInsuranceRow[]
    activity.value = (payload.activity ?? []) as FinancialActivityRow[]
  } catch (error) {
    console.error('Failed to load financial statistics', error)
    kpis.value = {
      invoices_count: 0,
      credit_notes_count: 0,
      debit_notes_count: 0,
      invoice_revenue: 0,
      credit_notes_total: 0,
      debit_notes_total: 0,
      notes_net: 0,
      notes_absolute: 0,
      error_percentage: 0,
      net_revenue: 0,
      procedures_revenue: 0,
      transport_revenue: 0,
      activity_total_revenue: 0,
    }
    monthly.value = []
    byUser.value = []
    byBranch.value = []
    byInsurance.value = []
    byUserInsuranceCompanies.value = []
    byUserInsuranceRows.value = []
    activity.value = []
  } finally {
    loading.value = false
    uiOverlayStore.setContentLoading(false)
  }
}

async function onSubmitFilters() {
  submitted.value = true

  if (!startDate.value || !endDate.value) {
    return
  }

  await loadFinancialStatistics()
}

onMounted(async () => {
  await loadFinancialStatistics()
})
</script>

<template>
  <div class="statistics-page flex flex-col gap-5">
    <div class="mb-8 flex flex-col gap-4">
      <div class="bg-tag3 no-print p-4 rounded-md">
        <div class="grid grid-cols-12 gap-4 w-full md:w-auto">
          <div class="col-span-6">
            <label class="block text-normal mb-1">Obdobie od</label>
            <DatePicker
              v-model="startDate"
              view="month"
              dateFormat="mm.yy"
              :manualInput="false"
              inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
              fluid
            />
            <small v-if="submitted && !startDate" class="text-danger">Obdobie od je povinné.</small>
          </div>

          <div class="col-span-6">
            <label class="block text-normal mb-1">Obdobie do</label>
            <DatePicker
              v-model="endDate"
              view="month"
              dateFormat="mm.yy"
              :manualInput="false"
              inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
              fluid
            />
            <small v-if="submitted && !endDate" class="text-danger">Obdobie do je povinné.</small>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <Button 
            @click="onSubmitFilters"
            class="bg-accent! border-0! hover:bg-darkgrey! px-4! rounded-md! text-white! text-normal! h-7!">
            Načítať dáta
          </Button>
      </div>
    </div>

    <div class="summary-grid">
      <div class="bg-darkgrey text-white rounded-md p-4">
        <div class="text-tag1">Čisté tržby</div>
        <div class="text-2xl font-bold">{{ formatCurrency(kpis.net_revenue) }}</div>
      </div>

      <div class="bg-darkgrey text-white rounded-md p-4">
        <div class="text-tag1">Fakturovaná suma</div>
        <div class="text-2xl font-bold">{{ formatCurrency(kpis.invoice_revenue) }}</div>
      </div>

      <div class="bg-darkgrey text-white rounded-md p-4">
        <div class="text-tag1">Chybovosť</div>
        <div class="text-2xl font-bold">{{ formatPercent(kpis.error_percentage) }}</div>
      </div>

      <div class="bg-darkgrey text-white rounded-md p-4">
        <div class="text-tag1">Najvýkonnejšia sestra</div>
        <div class="text-2xl font-bold">{{ topUser?.user_name ?? '-' }}</div>
        <div class="text-mini">{{ formatCurrency(topUser?.revenue_total ?? 0) }}</div>
      </div>

      <div class="bg-darkgrey text-white rounded-md p-4">
        <div class="text-tag1">Najvýkonnejšia pobočka</div>
        <div class="text-2xl font-bold">{{ topBranch?.branch_name ?? '-' }}</div>
        <div class="text-mini">{{ formatCurrency(topBranch?.revenue_total ?? 0) }}</div>
      </div>

      <div class="bg-darkgrey text-white rounded-md p-4">
        <div class="text-tag1">Najsilnejšia poisťovňa</div>
        <div class="text-2xl font-bold">
            {{ (topInsurance?.insurance_company_name ?? '-').split(' ')[0] }}
        </div>        
        <div class="text-mini">{{ formatCurrency(topInsurance?.net_revenue ?? 0) }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 no-print mb-8">
        <div class="border-darkgrey border rounded-md p-4">
            <div class="section-header">
            <div class="section-title-wrap">
                <span class="section-title">Aktivity podľa typu</span>
            </div>
            </div>
            <div class="">
            <Chart type="doughnut" :data="activitySplitChartData" :options="doughnutOptions" />
            </div>
        </div>

        <div class="border-darkgrey border rounded-md p-4">
            <div class="section-header">
                <div class="section-title-wrap">
                <span class="section-title">Top sestry</span>
                </div>
            </div>
            <div class="">
                <Chart type="bar" :data="topUsersRevenueChartData" :options="horizontalBarOptions" />
            </div>
        </div>

        <div class="border-darkgrey border rounded-md p-4">
            <div class="section-header">
            <div class="section-title-wrap">
                <span class="section-title">Top pobočky podľa tržieb</span>
            </div>
            </div>
            <div class="">
            <Chart type="bar" :data="topBranchesRevenueChartData" :options="horizontalBarOptions" />
            </div>
        </div>
    </div>

    <div class="no-print mb-8">
      <div class="section-header">
        <span class="section-title">Aktivita podľa pobočky</span>
      </div>

      <div class="overflow-x-auto">
        <DataTable :value="byBranch" stripedRows class="text-sm">
          <Column header="#" style="width: 3rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column field="branch_name" header="Pobočka" />
          <Column field="points_revenue" header="Výkony" align="right">
            <template #body="{ data }">
              <div class="activity-cell">
                <div class="flex items-center gap-2 w-full justify-between">
                  <span class="text-mini">{{ formatCurrency(getBranchActivityRevenue(data, 'points')) }}</span>
                  <span class="text-mini text-accent">{{ getBranchActivitySharePercent(data, 'points').toFixed(1) }}%</span>
                </div>
                <div class="activity-cell-track">
                  <div
                    class="activity-cell-fill"
                    :style="{ width: `${getBranchActivitySharePercent(data, 'points')}%` }"
                  ></div>
                </div>
              </div>
            </template>
          </Column>
          <Column field="kilometers_revenue" header="Doprava" align="right">
            <template #body="{ data }">
              <div class="activity-cell">
                <div class="flex items-center gap-2 w-full justify-between">
                  <span class="text-mini">{{ formatCurrency(getBranchActivityRevenue(data, 'kilometers')) }}</span>
                  <span class="text-mini text-accent">{{ getBranchActivitySharePercent(data, 'kilometers').toFixed(1) }}%</span>
                </div>
                <div class="activity-cell-track">
                  <div
                    class="activity-cell-fill"
                    :style="{ width: `${getBranchActivitySharePercent(data, 'kilometers')}%` }"
                  ></div>
                </div>
              </div>
            </template>
          </Column>
          <Column field="revenue_total" header="Spolu" align="right">
            <template #body="{ data }">{{ formatCurrency(data.revenue_total ?? 0) }}</template>
          </Column>
        </DataTable>
      </div>
    </div>

    <div class="no-print mb-8">
      <div class="section-header">
        <span class="section-title">Aktivita podľa používateľa</span>
      </div>

      <div class="overflow-x-auto">
        <DataTable :value="byUser" stripedRows class="text-sm">
          <Column header="#" style="width: 3rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column field="user_name" header="Používateľ" />
          <Column field="points_revenue" header="Výkony" align="right">
            <template #body="{ data }">
              <div class="activity-cell">
                <div class="flex items-center gap-2 w-full justify-between">
                  <span class="text-mini">{{ formatCurrency(getUserActivityRevenue(data, 'points')) }}</span>
                  <span class="text-mini text-accent">{{ getUserActivitySharePercent(data, 'points').toFixed(1) }}%</span>
                </div>
                <div class="activity-cell-track">
                  <div
                    class="activity-cell-fill"
                    :style="{ width: `${getUserActivitySharePercent(data, 'points')}%` }"
                  ></div>
                </div>
              </div>
            </template>
          </Column>
          <Column field="kilometers_revenue" header="Doprava" align="right">
            <template #body="{ data }">
              <div class="activity-cell">
                <div class="flex items-center gap-2 w-full justify-between">
                  <span class="text-mini">{{ formatCurrency(getUserActivityRevenue(data, 'kilometers')) }}</span>
                  <span class="text-mini text-accent">{{ getUserActivitySharePercent(data, 'kilometers').toFixed(1) }}%</span>
                </div>
                <div class="activity-cell-track">
                  <div
                    class="activity-cell-fill"
                    :style="{ width: `${getUserActivitySharePercent(data, 'kilometers')}%` }"
                  ></div>
                </div>
              </div>
            </template>
          </Column>
          <Column field="revenue_total" header="Spolu" align="right">
            <template #body="{ data }">{{ formatCurrency(data.revenue_total ?? 0) }}</template>
          </Column>
        </DataTable>
      </div>
    </div>

    <div class="no-print mb-8">
      <div class="section-header">
        <span class="section-title">Rozdelenie podľa poisťovne</span>
      </div>

      <div class="overflow-x-auto">
        <DataTable :value="byUserInsuranceRows" stripedRows class="text-sm">
          <Column header="#" style="width: 3rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>

          <Column field="user_name" header="Používateľ" />

          <Column
            v-for="company in byUserInsuranceCompanies"
            :key="company.insurance_company_id"
            :header="company.insurance_company_name.split(' ')[0]"
            style="min-width: 180px"
          >
            <template #body="{ data }">
              <div class="activity-cell">
                <div class="flex items-center gap-2 w-full justify-between">
                  <span class="text-mini">{{ formatCurrency(getUserInsuranceAmount(data, company.insurance_company_id)) }}</span>
                  <span class="text-mini text-accent">{{ getUserInsuranceSharePercent(data, company.insurance_company_id).toFixed(1) }}%</span>
                </div>
                <div class="activity-cell-track">
                  <div
                    class="activity-cell-fill"
                    :style="{ width: `${getUserInsuranceSharePercent(data, company.insurance_company_id)}%` }"
                  ></div>
                </div>
              </div>
            </template>
          </Column>

          <Column field="revenue_total" header="Spolu" align="right">
            <template #body="{ data }">{{ formatCurrency(data.revenue_total ?? 0) }}</template>
          </Column>
        </DataTable>
      </div>
    </div>

    <div class="no-print mb-8">
      <div class="section-header">
        <span class="section-title">Fakturácia na poisťovne</span>
      </div>

      <div class="overflow-x-auto">
        <DataTable :value="byInsurance" stripedRows class="text-sm">
          <Column header="#" style="width: 3rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column field="insurance_company_name" header="Poisťovňa" />
          <Column field="invoice_revenue" header="Faktúry" align="right">
            <template #body="{ data }">{{ formatCurrency(data.invoice_revenue ?? 0) }}</template>
          </Column>
          <Column field="credit_notes_total" header="Dobropisy" align="right">
            <template #body="{ data }">{{ formatCurrency(data.credit_notes_total ?? 0) }}</template>
          </Column>
          <Column field="debit_notes_total" header="Ťarchopisy" align="right">
            <template #body="{ data }">{{ formatCurrency(data.debit_notes_total ?? 0) }}</template>
          </Column>
          <Column field="net_revenue" header="Spolu" align="right">
            <template #body="{ data }">{{ formatCurrency(data.net_revenue ?? 0) }}</template>
          </Column>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<style>
.statistics-page {
  padding-bottom: 2rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 1rem;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.section-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.section-title {
  font-size: 1.05rem;
  font-weight: 600;
}

.chart-box {
  height: 280px;
}

.activity-cell {
  min-width: 180px;
}

.activity-cell-track {
  width: 100%;
  height: 0.36rem;
  background: #e5e7eb;
  border-radius: 9999px;
  overflow: hidden;
  margin-top: 0.3rem;
}

.activity-cell-fill {
  height: 100%;
  background: var(--c-accent);
  border-radius: 9999px;
}

@media (min-width: 768px) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1280px) {
  .summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
