<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import DatePicker from 'primevue/datepicker'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import api from '@/services/api'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

type InsuranceCompany = { id: number; name: string }

type UserStatisticsRow = {
  id: string
  branch_id: number
  branch_name: string
  user_id: number
  user_name: string
  patients_total?: number
  points_total?: number
  [key: string]: any
}

type DoctorStatisticsRow = {
  id: number
  doctor_id: number
  doctor_name: string
  patients_count: number
}

type UserTotalsRow = {
  id?: string
  user_id: number
  user_name: string
  branch_id?: number
  branch_name?: string
  month: string
  type: 'Výkony' | 'Doprava'
  [key: string]: any
}

const dates = ref<Date>(new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1))
const submitted = ref(false)

const companies = ref<InsuranceCompany[]>([])
const tableData = ref<UserStatisticsRow[]>([])
const previousTableData = ref<UserStatisticsRow[]>([])
const doctorTableData = ref<DoctorStatisticsRow[]>([])
const previousDoctorTableData = ref<DoctorStatisticsRow[]>([])
const branchTableData = ref<any[]>([])
const previousBranchTableData = ref<any[]>([])
const branchTotalsData = ref<any[]>([])
const previousBranchTotalsData = ref<any[]>([])
const userTotalsAggregatedData = ref<any[]>([])
const previousUserTotalsAggregatedData = ref<any[]>([])
const userTotalsData = ref<UserTotalsRow[]>([])
const initialLoading = ref(true)
const loading = ref(false)
const doctorLoading = ref(false)
const branchLoading = ref(false)
const branchTotalsLoading = ref(false)
const userTotalsLoading = ref(false)
const userTotalsAggregatedLoading = ref(false)

// 3-month trend data
const threeMonthTrendData = ref<any[]>([])
const threeMonthPatientsData = ref<any[]>([])
const threeMonthBranchTotalsData = ref<any[]>([])

// Sort table data by patients_total descending
const sortedTableData = computed(() => {
  return [...tableData.value].sort((a, b) => (b.patients_total ?? 0) - (a.patients_total ?? 0))
})

// Sort doctor data by patients_count descending
const sortedDoctorTableData = computed(() => {
  return [...doctorTableData.value].sort((a, b) => (b.patients_count ?? 0) - (a.patients_count ?? 0))
})

// Sort branch data by patients_count descending
const sortedBranchTableData = computed(() => {
  return [...branchTableData.value].sort((a, b) => (b.patients_count ?? 0) - (a.patients_count ?? 0))
})

// Create a map of previous month data by user for comparison
const previousDataMap = computed(() => {
  const map = new Map()
  previousTableData.value.forEach(row => {
    const key = row.user_id + ':' + row.branch_id
    map.set(key, row.patients_total ?? 0)
  })
  return map
})

// Create a map of previous month doctor data for comparison
const previousDoctorDataMap = computed(() => {
  const map = new Map()
  previousDoctorTableData.value.forEach(row => {
    map.set(row.doctor_id, row.patients_count ?? 0)
  })
  return map
})

// Create a map of previous month branch data for comparison
const previousBranchDataMap = computed(() => {
  const map = new Map()
  previousBranchTableData.value.forEach(row => {
    map.set(row.branch_id, row.patients_count ?? 0)
  })
  return map
})

// Add trend info to sorted table data
const tableDataWithTrend = computed(() => {
  return sortedTableData.value.map(row => ({
    ...row,
    previousPatients: previousDataMap.value.get(row.user_id + ':' + row.branch_id) ?? 0,
    trendDifference: (row.patients_total ?? 0) - (previousDataMap.value.get(row.user_id + ':' + row.branch_id) ?? 0),
  }))
})

// Add trend info to sorted doctor table data
const doctorTableDataWithTrend = computed(() => {
  return sortedDoctorTableData.value.map(row => ({
    ...row,
    previousPatients: previousDoctorDataMap.value.get(row.doctor_id) ?? 0,
    trendDifference: (row.patients_count ?? 0) - (previousDoctorDataMap.value.get(row.doctor_id) ?? 0),
  }))
})

// Add trend info to sorted branch table data
const branchTableDataWithTrend = computed(() => {
  return sortedBranchTableData.value.map(row => ({
    ...row,
    previousPatients: previousBranchDataMap.value.get(row.branch_id) ?? 0,
    trendDifference: (row.patients_count ?? 0) - (previousBranchDataMap.value.get(row.branch_id) ?? 0),
  }))
})

// Create a map of previous month branch totals for comparison
const previousBranchTotalsMap = computed(() => {
  const map = new Map()
  previousBranchTotalsData.value.forEach(row => {
    map.set(row.branch_id, row.total_amount ?? 0)
  })
  return map
})

// Sort branch totals by total_amount descending
const sortedBranchTotalsData = computed(() => {
  return [...branchTotalsData.value].sort((a, b) => (b.total_amount ?? 0) - (a.total_amount ?? 0))
})

// Add trend info to sorted branch totals data
const branchTotalsDataWithTrend = computed(() => {
  return sortedBranchTotalsData.value.map(row => ({
    ...row,
    previousAmount: previousBranchTotalsMap.value.get(row.branch_id) ?? 0,
    trendDifference: (row.total_amount ?? 0) - (previousBranchTotalsMap.value.get(row.branch_id) ?? 0),
  }))
})

// Create a map of previous month user totals for comparison
const previousUserTotalsAggregatedMap = computed(() => {
  const map = new Map()
  previousUserTotalsAggregatedData.value.forEach(row => {
    map.set(row.user_id, row.total_amount ?? 0)
  })
  return map
})

// Sort user totals aggregated by total_amount descending
const sortedUserTotalsAggregatedData = computed(() => {
  return [...userTotalsAggregatedData.value].sort((a, b) => (b.total_amount ?? 0) - (a.total_amount ?? 0))
})

// Add trend info to sorted user totals aggregated data
const userTotalsAggregatedDataWithTrend = computed(() => {
  return sortedUserTotalsAggregatedData.value.map(row => ({
    ...row,
    previousAmount: previousUserTotalsAggregatedMap.value.get(row.user_id) ?? 0,
    trendDifference: (row.total_amount ?? 0) - (previousUserTotalsAggregatedMap.value.get(row.user_id) ?? 0),
  }))
})

const toMonthParam = (d: Date) => {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  return `${y}-${m}`
}

async function loadAllStatistics() {
  initialLoading.value = true
  try {
    await Promise.all([
      loadStatistics(),
      loadDoctorStatistics(),
      loadUserTotals(),
      loadBranchStatistics(),
      loadBranchTotals(),
      loadUserTotalsAggregated(),
      load3MonthTrends(),
    ])
  } finally {
    initialLoading.value = false
  }
}

async function loadStatistics() {
  if (!dates.value) return

  loading.value = true
  try {
    const month = toMonthParam(dates.value)
    
    // Get previous month
    const prevDate = new Date(dates.value)
    prevDate.setMonth(prevDate.getMonth() - 1)
    const previousMonth = toMonthParam(prevDate)

    const res = await api.get('/v1/manager/user-statistics', {
      params: { month },
    })

    const prevRes = await api.get('/v1/manager/user-statistics', {
      params: { month: previousMonth },
    })

    const data = res.data?.data ?? []
    companies.value = res.data?.meta?.companies ?? []
    tableData.value = data
    previousTableData.value = prevRes.data?.data ?? []

  } catch (e) {
    console.error('Failed to load user statistics', e)
    tableData.value = []
    previousTableData.value = []
    companies.value = []
  } finally {
    loading.value = false
  }
}

async function loadDoctorStatistics() {
  if (!dates.value) return

  doctorLoading.value = true
  try {
    const month = toMonthParam(dates.value)
    
    // Get previous month
    const prevDate = new Date(dates.value)
    prevDate.setMonth(prevDate.getMonth() - 1)
    const previousMonth = toMonthParam(prevDate)

    const res = await api.get('/v1/manager/doctor-statistics', {
      params: { month },
    })

    const prevRes = await api.get('/v1/manager/doctor-statistics', {
      params: { month: previousMonth },
    })

    const data = res.data?.data ?? []
    doctorTableData.value = data
    previousDoctorTableData.value = prevRes.data?.data ?? []

  } catch (e) {
    console.error('Failed to load doctor statistics', e)
    doctorTableData.value = []
    previousDoctorTableData.value = []
  } finally {
    doctorLoading.value = false
  }
}

async function loadUserTotals() {
  if (!dates.value) return

  userTotalsLoading.value = true
  try {
    const month = toMonthParam(dates.value)

    const res = await api.get('/v1/manager/user-totals', {
      params: { month },
    })

    const data = res.data?.data ?? []
    userTotalsData.value = data

  } catch (e) {
    console.error('Failed to load user totals', e)
    userTotalsData.value = []
  } finally {
    userTotalsLoading.value = false
  }
}

async function loadBranchStatistics() {
  if (!dates.value) return

  branchLoading.value = true
  try {
    const month = toMonthParam(dates.value)
    
    // Get previous month
    const prevDate = new Date(dates.value)
    prevDate.setMonth(prevDate.getMonth() - 1)
    const previousMonth = toMonthParam(prevDate)

    const res = await api.get('/v1/manager/branch-statistics', {
      params: { month },
    })

    const prevRes = await api.get('/v1/manager/branch-statistics', {
      params: { month: previousMonth },
    })

    const data = res.data?.data ?? []
    branchTableData.value = data
    previousBranchTableData.value = prevRes.data?.data ?? []

  } catch (e) {
    console.error('Failed to load branch statistics', e)
    branchTableData.value = []
    previousBranchTableData.value = []
  } finally {
    branchLoading.value = false
  }
}

async function loadBranchTotals() {
  if (!dates.value) return

  branchTotalsLoading.value = true
  try {
    const month = toMonthParam(dates.value)
    
    // Get previous month
    const prevDate = new Date(dates.value)
    prevDate.setMonth(prevDate.getMonth() - 1)
    const previousMonth = toMonthParam(prevDate)

    const res = await api.get('/v1/manager/branch-totals', {
      params: { month },
    })

    const prevRes = await api.get('/v1/manager/branch-totals', {
      params: { month: previousMonth },
    })

    const data = res.data?.data ?? []
    branchTotalsData.value = data
    previousBranchTotalsData.value = prevRes.data?.data ?? []

  } catch (e) {
    console.error('Failed to load branch totals', e)
    branchTotalsData.value = []
    previousBranchTotalsData.value = []
  } finally {
    branchTotalsLoading.value = false
  }
}

async function loadUserTotalsAggregated() {
  if (!dates.value) return

  userTotalsAggregatedLoading.value = true
  try {
    const month = toMonthParam(dates.value)
    
    // Get previous month
    const prevDate = new Date(dates.value)
    prevDate.setMonth(prevDate.getMonth() - 1)
    const previousMonth = toMonthParam(prevDate)

    const res = await api.get('/v1/manager/user-totals-aggregated', {
      params: { month },
    })

    const prevRes = await api.get('/v1/manager/user-totals-aggregated', {
      params: { month: previousMonth },
    })

    const data = res.data?.data ?? []
    userTotalsAggregatedData.value = data
    previousUserTotalsAggregatedData.value = prevRes.data?.data ?? []

  } catch (e) {
    console.error('Failed to load user totals aggregated', e)
    userTotalsAggregatedData.value = []
    previousUserTotalsAggregatedData.value = []
  } finally {
    userTotalsAggregatedLoading.value = false
  }
}

async function load3MonthTrends() {
  if (!dates.value) return

  try {
    const currentMonth = toMonthParam(dates.value)
    const months = [currentMonth]

    for (let i = 1; i < 3; i++) {
      const d = new Date(dates.value)
      d.setMonth(d.getMonth() - i)
      months.unshift(toMonthParam(d))
    }

    // Load user totals for last 3 months
    const userTotalsPromises = months.map(m => 
      api.get('/v1/manager/user-totals-aggregated', { params: { month: m } })
    )
    const userTotalsResults = await Promise.all(userTotalsPromises)
    threeMonthTrendData.value = userTotalsResults.map((res, i) => ({
      month: months[i],
      total_points: res.data?.data?.[0]?.total_points ?? 0,
      total_kilometers: res.data?.data?.[0]?.total_kilometers ?? 0,
    }))

    // Load user statistics for last 3 months
    const patientsPromises = months.map(m => 
      api.get('/v1/manager/user-statistics', { params: { month: m } })
    )
    const patientsResults = await Promise.all(patientsPromises)
    threeMonthPatientsData.value = patientsResults.map((res, i) => ({
      month: months[i],
      patients_total: res.data?.data?.reduce((sum: number, row: any) => sum + (row.patients_total ?? 0), 0) ?? 0,
    }))

    // Load branch totals for last 3 months
    const branchTotalsPromises = months.map(m => 
      api.get('/v1/manager/branch-totals', { params: { month: m } })
    )
    const branchTotalsResults = await Promise.all(branchTotalsPromises)
    threeMonthBranchTotalsData.value = branchTotalsResults.map((res, i) => ({
      month: months[i],
      total_amount: res.data?.data?.reduce((sum: number, row: any) => sum + (row.total_amount ?? 0), 0) ?? 0,
    }))

  } catch (e) {
    console.error('Failed to load 3-month trends', e)
  }
}

watch(
  () => dates.value,
  async () => {
    await loadAllStatistics()
  },
  { deep: true }
)

onMounted(async () => {
  await loadAllStatistics()
})
</script>

<template>
    <LoadingOverlay :show="initialLoading" text="" />

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

          <small v-if="submitted && !dates" class="text-warning">
            Obdobie je povinné.
          </small>
        </div>
      </div>
    </section>


    <Toolbar class="bg-transparent! border-0! p-0! py-3! mt-5! shadow-none! flex items-center justify-between no-print">
        <template #start>
            <span class="text-heading">
                Najvýkonnejšie pobočky podľa počtu pacientov
            </span>
        </template>
    </Toolbar>

    <div class="overflow-x-auto">
      <DataTable :value="branchTableDataWithTrend" striped-rows class="text-sm">
        <Column header="" style="width: 3rem">
          <template #body="{ index }">
            {{ index + 1 }}.
          </template>
        </Column>
        <Column field="branch_name" header="Pobočka" />
        <Column field="patients_count" header="Počet pacientov" align="center">
          <template #body="{ data }">
            {{ data.patients_count ?? 0 }}
          </template>
        </Column>
        <Column header="Trend" align="center" style="width: 150px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-2" style="min-height: 24px">
              <div style="width: 24px; display: flex; justify-content: center">
                <i v-if="data.trendDifference > 0" class="bi bi-arrow-up text-success"></i>
                <i v-else-if="data.trendDifference < 0" class="bi bi-arrow-down text-warning"></i>
                <span v-else class="text-lightgrey">—</span>
              </div>
              <div style="width: 50px; text-align: center">
                <span :class="data.trendDifference > 0 ? 'text-success' : data.trendDifference < 0 ? 'text-warning' : 'text-lightgrey'">
                  {{ data.trendDifference > 0 ? '+' : '' }}{{ data.trendDifference }}
                </span>
              </div>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Toolbar class="bg-transparent! border-0! p-0! py-3! mt-5! shadow-none! flex items-center justify-between no-print">
        <template #start>
            <span class="text-heading">
                Najvýkonnejší užívatelia podľa počtu pacientov
            </span>
        </template>
    </Toolbar>

    <div class="overflow-x-auto">
      <DataTable :value="tableDataWithTrend" striped-rows class="text-sm">
        <Column header="" style="width: 3rem">
          <template #body="{ index }">
            {{ index + 1 }}.
          </template>
        </Column>
        <Column field="user_name" header="Užívateľ" />
        <Column field="branch_name" header="Pobočka" />
        
        <Column 
          v-for="company in companies.sort((a, b) => a.name.localeCompare(b.name))" 
          :key="company.id"
          :header="company.name.split(' ')[0]"
          align="center"
        >
          <template #body="{ data }">
            {{ data[`insurance_${company.id}`] ?? 0 }}
          </template>
        </Column>

        <Column field="patients_total" header="Spolu" align="center">
          <template #body="{ data }">
            {{ data.patients_total ?? 0 }}
          </template>
        </Column>

        <Column header="Trend" align="center" style="width: 150px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-2" style="min-height: 24px">
              <div style="width: 24px; display: flex; justify-content: center">
                <i v-if="data.trendDifference > 0" class="bi bi-arrow-up text-success"></i>
                <i v-else-if="data.trendDifference < 0" class="bi bi-arrow-down text-warning"></i>
                <span v-else class="text-lightgrey">—</span>
              </div>
              <div style="width: 50px; text-align: center">
                <span :class="data.trendDifference > 0 ? 'text-success' : data.trendDifference < 0 ? 'text-warning' : 'text-lightgrey'">
                  {{ data.trendDifference > 0 ? '+' : '' }}{{ data.trendDifference }}
                </span>
              </div>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Toolbar class="bg-transparent! border-0! p-0! py-3! mt-5! shadow-none! flex items-center justify-between no-print">
        <template #start>
            <span class="text-heading">
                Najvýkonnejšie pobočky podľa tržieb
            </span>
        </template>
    </Toolbar>

    <div class="overflow-x-auto">
      <DataTable :value="branchTotalsDataWithTrend" striped-rows class="text-sm">
        <Column header="" style="width: 3rem">
          <template #body="{ index }">
            {{ index + 1 }}.
          </template>
        </Column>
        <Column field="branch_name" header="Pobočka" />
        <Column field="total_points" header="Body" align="center">
          <template #body="{ data }">
            {{ data.total_points?.toFixed(2) ?? '0.00' }}
          </template>
        </Column>
        <Column field="total_kilometers" header="Kilometre" align="center">
          <template #body="{ data }">
            {{ data.total_kilometers?.toFixed(2) ?? '0.00' }}
          </template>
        </Column>
        <Column field="total_amount" header="Spolu" align="center">
          <template #body="{ data }">
            {{ data.total_amount?.toFixed(2) ?? '0.00' }}
          </template>
        </Column>
        <Column header="Trend" align="center" style="width: 150px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-2" style="min-height: 24px">
              <div style="width: 24px; display: flex; justify-content: center">
                <i v-if="data.trendDifference > 0" class="bi bi-arrow-up text-success"></i>
                <i v-else-if="data.trendDifference < 0" class="bi bi-arrow-down text-warning"></i>
                <span v-else class="text-lightgrey">—</span>
              </div>
              <div style="width: 50px; text-align: center">
                <span :class="data.trendDifference > 0 ? 'text-success' : data.trendDifference < 0 ? 'text-warning' : 'text-lightgrey'">
                  {{ data.trendDifference > 0 ? '+' : '' }}{{ data.trendDifference.toFixed(2) }}
                </span>
              </div>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>


    <Toolbar class="bg-transparent! border-0! p-0! py-3! mt-5! shadow-none! flex items-center justify-between no-print">
        <template #start>
            <span class="text-heading">
                Najvýkonnejší užívatelia podľa tržieb
            </span>
        </template>
    </Toolbar>

    <div class="overflow-x-auto">
      <DataTable :value="userTotalsAggregatedDataWithTrend" striped-rows class="text-sm">
        <Column header="" style="width: 3rem">
          <template #body="{ index }">
            {{ index + 1 }}.
          </template>
        </Column>
        <Column field="user_name" header="Užívateľ" />
        <Column field="total_points" header="Body" align="center">
          <template #body="{ data }">
            {{ data.total_points?.toFixed(2) ?? '0.00' }}
          </template>
        </Column>
        <Column field="total_kilometers" header="Kilometre" align="center">
          <template #body="{ data }">
            {{ data.total_kilometers?.toFixed(2) ?? '0.00' }}
          </template>
        </Column>
        <Column field="total_amount" header="Spolu" align="center">
          <template #body="{ data }">
            {{ data.total_amount?.toFixed(2) ?? '0.00' }}
          </template>
        </Column>
        <Column header="Trend" align="center" style="width: 150px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-2" style="min-height: 24px">
              <div style="width: 24px; display: flex; justify-content: center">
                <i v-if="data.trendDifference > 0" class="bi bi-arrow-up text-success"></i>
                <i v-else-if="data.trendDifference < 0" class="bi bi-arrow-down text-warning"></i>
                <span v-else class="text-lightgrey">—</span>
              </div>
              <div style="width: 50px; text-align: center">
                <span :class="data.trendDifference > 0 ? 'text-success' : data.trendDifference < 0 ? 'text-warning' : 'text-lightgrey'">
                  {{ data.trendDifference > 0 ? '+' : '' }}{{ data.trendDifference.toFixed(2) }}
                </span>
              </div>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Toolbar class="bg-transparent! border-0! p-0! py-3! mt-5! shadow-none! flex items-center justify-between no-print">
        <template #start>
            <span class="text-heading">
                Najvýkonnejší lekári podľa počtu pacientov
            </span>
        </template>
    </Toolbar>

    <div class="overflow-x-auto">
      <DataTable :value="doctorTableDataWithTrend" striped-rows class="text-sm">
        <Column header="" style="width: 3rem">
          <template #body="{ index }">
            {{ index + 1 }}.
          </template>
        </Column>
        <Column field="doctor_name" header="Lekár" />
        <Column field="patients_count" header="Počet pacientov" align="center">
          <template #body="{ data }">
            {{ data.patients_count ?? 0 }}
          </template>
        </Column>
        <Column header="Trend" align="center" style="width: 150px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-2" style="min-height: 24px">
              <div style="width: 24px; display: flex; justify-content: center">
                <i v-if="data.trendDifference > 0" class="bi bi-arrow-up text-success"></i>
                <i v-else-if="data.trendDifference < 0" class="bi bi-arrow-down text-warning"></i>
                <span v-else class="text-lightgrey">—</span>
              </div>
              <div style="width: 50px; text-align: center">
                <span :class="data.trendDifference > 0 ? 'text-success' : data.trendDifference < 0 ? 'text-warning' : 'text-lightgrey'">
                  {{ data.trendDifference > 0 ? '+' : '' }}{{ data.trendDifference }}
                </span>
              </div>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
</template>

