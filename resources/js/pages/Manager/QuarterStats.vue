<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import Chart from 'primevue/chart'
import DatePicker from 'primevue/datepicker'
import Toolbar from 'primevue/toolbar'
import api from '@/services/api'

/** =========================
 *  Types
 *  ========================= */
type UserStatisticsRow = {
  id: string
  branch_id: number
  branch_name: string
  user_id: number
  user_name: string
  patients_total?: number
  [key: string]: any
}

type UserTotalsAggRow = {
  user_id: number
  user_name: string
  total_points?: number
  total_kilometers?: number
  total_amount?: number
  [key: string]: any
}

type BranchTotalsRow = {
  branch_id: number
  branch_name: string
  total_points?: number
  total_kilometers?: number
  total_amount?: number
  [key: string]: any
}

/** =========================
 *  Month picker
 *  ========================= */
const dates = ref<Date>(new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1))

const toMonthParam = (d: Date) => {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  return `${y}-${m}`
}

const getLastFourMonths = (date: Date): string[] => {
  const out: string[] = []
  for (let i = 3; i >= 0; i--) {
    const d = new Date(date)
    d.setDate(1)
    d.setMonth(d.getMonth() - i)
    out.push(toMonthParam(d))
  }
  return out
}

const last4MonthsLabels = computed(() => getLastFourMonths(dates.value))

/** =========================
 *  PATIENTS charts (from /v1/manager/user-statistics)
 *  ========================= */
const last4MonthsPatientsTotalByMonth = ref<Record<string, number>>({})
const last4MonthsPatientsByBranch = ref<Record<string, { name: string; months: Record<string, number> }>>({})
const last4MonthsPatientsByUser = ref<Record<string, { name: string; months: Record<string, number> }>>({})

async function loadLast4MonthsPatientsCharts() {
  const monthKeys = last4MonthsLabels.value

  const results = await Promise.all(
    monthKeys.map((m) =>
      api
        .get('/v1/manager/user-statistics', { params: { month: m } })
        .then((r: any) => ({ month: m, rows: (r?.data?.data ?? []) as UserStatisticsRow[] }))
        .catch(() => ({ month: m, rows: [] as UserStatisticsRow[] }))
    )
  )

  const totals: Record<string, number> = {}
  monthKeys.forEach((m) => (totals[m] = 0))

  const branchMap: Record<string, { name: string; months: Record<string, number> }> = {}
  const userMap: Record<string, { name: string; months: Record<string, number> }> = {}

  results.forEach(({ month, rows }) => {
    rows.forEach((row) => {
      const patients = Number(row.patients_total ?? 0) || 0
      totals[month] = (totals[month] ?? 0) + patients

      const bid = String(row.branch_id)
      if (!branchMap[bid]) branchMap[bid] = { name: row.branch_name ?? `Branch ${bid}`, months: {} }
      branchMap[bid].months[month] = (branchMap[bid].months[month] ?? 0) + patients

      const uid = String(row.user_id)
      if (!userMap[uid]) userMap[uid] = { name: row.user_name ?? `User ${uid}`, months: {} }
      userMap[uid].months[month] = (userMap[uid].months[month] ?? 0) + patients
    })
  })

  last4MonthsPatientsTotalByMonth.value = totals
  last4MonthsPatientsByBranch.value = branchMap
  last4MonthsPatientsByUser.value = userMap
}

/** Patients chart data */
const chartPatientsTotal4M = computed(() => {
  const labels = last4MonthsLabels.value
  return {
    labels,
    datasets: [{ label: 'Pacienti celkom', data: labels.map((m) => last4MonthsPatientsTotalByMonth.value[m] ?? 0) }],
  }
})

const chartPatientsByBranch4M = computed(() => {
  const labels = last4MonthsLabels.value
  const branches = Object.values(last4MonthsPatientsByBranch.value).sort((a, b) => a.name.localeCompare(b.name))
  return {
    labels,
    datasets: branches.map((b) => ({ label: b.name, data: labels.map((m) => b.months[m] ?? 0) })),
  }
})

const chartPatientsByUser4M = computed(() => {
  const labels = last4MonthsLabels.value
  const users = Object.values(last4MonthsPatientsByUser.value).sort((a, b) => a.name.localeCompare(b.name))
  return {
    labels,
    datasets: users.map((u) => ({ label: u.name, data: labels.map((m) => u.months[m] ?? 0) })),
  }
})

/** =========================
 *  MONEY charts
 *  - general (total_amount per month)
 *  - per points + per kilometers (ratios €/point and €/km per month)
 *  - per user (total_amount split by user)
 *  - per branch (total_amount split by branch)
 *  ========================= */

/** General money totals by month (from branch-totals sum) */
const last4MonthsMoneyTotalByMonth = ref<Record<string, number>>({})

/** Ratios by month */
const last4MonthsMoneyPerPointByMonth = ref<Record<string, number>>({})
const last4MonthsMoneyPerKmByMonth = ref<Record<string, number>>({})

/** Split by user (from user-totals-aggregated) */
const last4MonthsMoneyByUser = ref<Record<string, { name: string; months: Record<string, number> }>>({})

/** Split by branch (from branch-totals) */
const last4MonthsMoneyByBranch = ref<Record<string, { name: string; months: Record<string, number> }>>({})

async function loadLast4MonthsMoneyCharts() {
  const monthKeys = last4MonthsLabels.value

  // Fetch both endpoints for 4 months
  const [userAggResults, branchTotalsResults] = await Promise.all([
    Promise.all(
      monthKeys.map((m) =>
        api
          .get('/v1/manager/user-totals-aggregated', { params: { month: m } })
          .then((r: any) => ({ month: m, rows: (r?.data?.data ?? []) as UserTotalsAggRow[] }))
          .catch(() => ({ month: m, rows: [] as UserTotalsAggRow[] }))
      )
    ),
    Promise.all(
      monthKeys.map((m) =>
        api
          .get('/v1/manager/branch-totals', { params: { month: m } })
          .then((r: any) => ({ month: m, rows: (r?.data?.data ?? []) as BranchTotalsRow[] }))
          .catch(() => ({ month: m, rows: [] as BranchTotalsRow[] }))
      )
    ),
  ])

  // Init per-month
  const moneyTotal: Record<string, number> = {}
  const eurPerPoint: Record<string, number> = {}
  const eurPerKm: Record<string, number> = {}
  monthKeys.forEach((m) => {
    moneyTotal[m] = 0
    eurPerPoint[m] = 0
    eurPerKm[m] = 0
  })

  // Split maps
  const userMap: Record<string, { name: string; months: Record<string, number> }> = {}
  const branchMap: Record<string, { name: string; months: Record<string, number> }> = {}

  // Build user split + also monthly sums (amount, points, km) from userAgg
  const monthSumsFromUsers: Record<string, { amount: number; points: number; km: number }> = {}
  monthKeys.forEach((m) => (monthSumsFromUsers[m] = { amount: 0, points: 0, km: 0 }))

  userAggResults.forEach(({ month, rows }) => {
    rows.forEach((row) => {
      const amount = Number(row.total_amount ?? 0) || 0
      const points = Number(row.total_points ?? 0) || 0
      const km = Number(row.total_kilometers ?? 0) || 0

      if (monthSumsFromUsers[month]) {
        monthSumsFromUsers[month].amount += amount
        monthSumsFromUsers[month].points += points
        monthSumsFromUsers[month].km += km
      }

      const uid = String(row.user_id)
      if (!userMap[uid]) userMap[uid] = { name: row.user_name ?? `User ${uid}`, months: {} }
      userMap[uid].months[month] = (userMap[uid].months[month] ?? 0) + amount
    })
  })

  // Build branch split + general money total from branch-totals (less risk of missing users)
  const monthSumsFromBranches: Record<string, { amount: number }> = {}
  monthKeys.forEach((m) => (monthSumsFromBranches[m] = { amount: 0 }))

  branchTotalsResults.forEach(({ month, rows }) => {
    rows.forEach((row) => {
      const amount = Number(row.total_amount ?? 0) || 0
      if (monthSumsFromBranches[month]) {
        monthSumsFromBranches[month].amount += amount
      }

      const bid = String(row.branch_id)
      if (!branchMap[bid]) branchMap[bid] = { name: row.branch_name ?? `Branch ${bid}`, months: {} }
      branchMap[bid].months[month] = (branchMap[bid].months[month] ?? 0) + amount
    })
  })

  // Finalize per-month money totals (use branch sums)
  monthKeys.forEach((m) => {
    moneyTotal[m] = monthSumsFromBranches[m]?.amount ?? 0

    // ratios use sums from userAgg (needs points/km)
    const amt = monthSumsFromUsers[m]?.amount ?? 0
    const pts = monthSumsFromUsers[m]?.points ?? 0
    const kms = monthSumsFromUsers[m]?.km ?? 0

    eurPerPoint[m] = pts > 0 ? amt / pts : 0
    eurPerKm[m] = kms > 0 ? amt / kms : 0
  })

  last4MonthsMoneyTotalByMonth.value = moneyTotal
  last4MonthsMoneyPerPointByMonth.value = eurPerPoint
  last4MonthsMoneyPerKmByMonth.value = eurPerKm
  last4MonthsMoneyByUser.value = userMap
  last4MonthsMoneyByBranch.value = branchMap
}

/** Money chart data */
const chartMoneyTotal4M = computed(() => {
  const labels = last4MonthsLabels.value
  return {
    labels,
    datasets: [{ label: 'Tržby celkom (€)', data: labels.map((m) => last4MonthsMoneyTotalByMonth.value[m] ?? 0) }],
  }
})

const chartMoneyRatios4M = computed(() => {
  const labels = last4MonthsLabels.value
  return {
    labels,
    datasets: [
      { label: '€ / výkony', data: labels.map((m) => last4MonthsMoneyPerPointByMonth.value[m] ?? 0) },
      { label: '€ / km', data: labels.map((m) => last4MonthsMoneyPerKmByMonth.value[m] ?? 0) },
    ],
  }
})

const chartMoneyByUser4M = computed(() => {
  const labels = last4MonthsLabels.value
  const users = Object.values(last4MonthsMoneyByUser.value).sort((a, b) => a.name.localeCompare(b.name))
  return {
    labels,
    datasets: users.map((u) => ({ label: u.name, data: labels.map((m) => u.months[m] ?? 0) })),
  }
})

const chartMoneyByBranch4M = computed(() => {
  const labels = last4MonthsLabels.value
  const branches = Object.values(last4MonthsMoneyByBranch.value).sort((a, b) => a.name.localeCompare(b.name))
  return {
    labels,
    datasets: branches.map((b) => ({ label: b.name, data: labels.map((m) => b.months[m] ?? 0) })),
  }
})

/** =========================
 *  Options
 *  ========================= */
const chartOptions = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: { legend: { position: 'top' as const } },
  scales: { y: { beginAtZero: true } },
}

/** =========================
 *  Load
 *  ========================= */
async function loadAllCharts() {
  await Promise.all([loadLast4MonthsPatientsCharts(), loadLast4MonthsMoneyCharts()])
}

watch(
  () => dates.value,
  async () => {
    await loadAllCharts()
  },
  { deep: true }
)

onMounted(async () => {
  await loadAllCharts()
})
</script>

<template>

  <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-12">
        <label class="block text-normal mb-1">Obdobie</label>
        <DatePicker
          v-model="dates"
          view="month"
          dateFormat="MM yy"
          :manualInput="false"
          inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
          fluid
        />
      </div>
    </div>
  </section>

  <!-- PATIENTS -->
  <Toolbar class="bg-transparent! border-0! p-0! py-3! mt-5! shadow-none! flex items-center justify-between no-print">
    <template #start><span class="text-heading">Výkonnosť v počte pacientov</span></template>
  </Toolbar>

  <div class="flex gap-4 overflow-x-auto bg-tag3 p-6 rounded-md">
    <div class="flex-shrink-0 w-96 bg-white p-6 rounded-lg">
      <h3 class="text-heading mb-3 text-darkgrey text-center">Celkový počet pacientov</h3>
      <Chart type="bar" :data="chartPatientsTotal4M" :options="chartOptions" />
    </div>
    <div class="flex-shrink-0 w-96 bg-white p-6 rounded-lg">
      <h3 class="text-heading mb-3 text-darkgrey text-center">Počet pacientov podľa pobočky</h3>
      <Chart type="bar" :data="chartPatientsByBranch4M" :options="chartOptions" />
    </div>
    <div class="flex-shrink-0 w-96 bg-white p-6 rounded-lg">
      <h3 class="text-heading mb-3 text-darkgrey text-center">Počet pacientov podľa používateľa</h3>
      <Chart type="bar" :data="chartPatientsByUser4M" :options="chartOptions" />
    </div>
  </div>

  <!-- MONEY -->
  <Toolbar class="bg-transparent! border-0! p-0! py-3! mt-8! shadow-none! flex items-center justify-between no-print">
    <template #start><span class="text-heading">Finančná výkonnosť</span></template>
  </Toolbar>

  <div class="flex gap-4 overflow-x-auto bg-tag3 p-6 rounded-md">
    <!-- Money general -->
    <div class="flex-shrink-0 w-96 bg-white p-6 rounded-md">
      <h3 class="text-heading mb-3 text-darkgrey text-center">Celkové tržby (€)</h3>
      <Chart type="bar" :data="chartMoneyTotal4M" :options="chartOptions" />
    </div>

    <!-- Money ratios -->
    <div class="flex-shrink-0 w-96 bg-white p-6 rounded-md">
      <h3 class="text-heading mb-3 text-darkgrey text-center">Tržby podľa typu</h3>
      <Chart type="bar" :data="chartMoneyRatios4M" :options="chartOptions" />
    </div>

    <!-- Money split by branch -->
    <div class="flex-shrink-0 w-96 bg-white p-6 rounded-md">
      <h3 class="text-heading mb-3 text-darkgrey text-center">Tržby podľa pobočky (€)</h3>
      <Chart type="bar" :data="chartMoneyByBranch4M" :options="chartOptions" />
    </div>

    <!-- Money split by user -->
    <div class="flex-shrink-0 w-96 bg-white p-6 rounded-md">
      <h3 class="text-heading mb-3 text-darkgrey text-center">Tržby podľa používateľa (€)</h3>
      <Chart type="bar" :data="chartMoneyByUser4M" :options="chartOptions" />
    </div>
  </div>
</template>
