<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import DatePicker from 'primevue/datepicker'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import api from '@/services/api'
import { useUiOverlayStore } from '@/stores/uiOverlay'
import Chart from 'primevue/chart'

type InsuranceCompany = {
    id: number
    name: string
}

type UserStatisticsRow = {
    id: string
    branch_id: number
    branch_name: string
    user_id: number
    user_name: string
    patients_total?: number
    chronic_patients_count?: number
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

const getPreviousMonthRange = () => {
    const now = new Date()
    const start = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    const end = new Date(now.getFullYear(), now.getMonth(), 0)

    return { start, end }
}

const initialRange = getPreviousMonthRange()
const startDate = ref<Date>(initialRange.start)
const endDate = ref<Date>(initialRange.end)
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
const insuranceCompanyTotalsData = ref<any[]>([])

const loading = ref(false)
const doctorLoading = ref(false)
const branchLoading = ref(false)
const branchTotalsLoading = ref(false)
const userTotalsLoading = ref(false)
const userTotalsAggregatedLoading = ref(false)
const insuranceCompanyTotalsLoading = ref(false)

const uiOverlayStore = useUiOverlayStore()

const threeMonthTrendData = ref<any[]>([])
const threeMonthPatientsData = ref<any[]>([])
const threeMonthBranchTotalsData = ref<any[]>([])

const sortedCompanies = computed(() => {
    return [...companies.value].sort((a, b) => a.name.localeCompare(b.name))
})

const sortedTableData = computed(() => {
    return [...tableData.value].sort((a, b) => (b.patients_total ?? 0) - (a.patients_total ?? 0))
})

const sortedDoctorTableData = computed(() => {
    return [...doctorTableData.value].sort((a, b) => (b.patients_count ?? 0) - (a.patients_count ?? 0))
})

const sortedBranchTableData = computed(() => {
    return [...branchTableData.value].sort((a, b) => (b.patients_count ?? 0) - (a.patients_count ?? 0))
})

const previousDataMap = computed(() => {
    const map = new Map()
    previousTableData.value.forEach((row) => {
        const key = `${row.user_id}:${row.branch_id}`
        map.set(key, row.patients_total ?? 0)
    })
    return map
})

const previousDoctorDataMap = computed(() => {
    const map = new Map()
    previousDoctorTableData.value.forEach((row) => {
        map.set(row.doctor_id, row.patients_count ?? 0)
    })
    return map
})

const previousBranchDataMap = computed(() => {
    const map = new Map()
    previousBranchTableData.value.forEach((row) => {
        map.set(row.branch_id, row.patients_count ?? 0)
    })
    return map
})

const tableDataWithTrend = computed(() => {
    return sortedTableData.value.map((row) => ({
        ...row,
        previousPatients: previousDataMap.value.get(`${row.user_id}:${row.branch_id}`) ?? 0,
        trendDifference: (row.patients_total ?? 0) - (previousDataMap.value.get(`${row.user_id}:${row.branch_id}`) ?? 0),
    }))
})

const termBreakdownTableData = computed(() => {
    return sortedTableData.value.map((row) => {
        const total = row.patients_total ?? 0
        const longTerm = row.chronic_patients_count ?? 0
        const shortTerm = Math.max(0, total - longTerm)

        return {
            ...row,
            longTerm,
            shortTerm,
            longTermPercentage: total > 0 ? (longTerm / total) * 100 : 0,
            shortTermPercentage: total > 0 ? (shortTerm / total) * 100 : 0,
        }
    })
})

const doctorTableDataWithTrend = computed(() => {
    return sortedDoctorTableData.value.map((row) => ({
        ...row,
        previousPatients: previousDoctorDataMap.value.get(row.doctor_id) ?? 0,
        trendDifference: (row.patients_count ?? 0) - (previousDoctorDataMap.value.get(row.doctor_id) ?? 0),
    }))
})

const branchTableDataWithTrend = computed(() => {
    return sortedBranchTableData.value.map((row) => ({
        ...row,
        previousPatients: previousBranchDataMap.value.get(row.branch_id) ?? 0,
        trendDifference: (row.patients_count ?? 0) - (previousBranchDataMap.value.get(row.branch_id) ?? 0),
    }))
})

const previousBranchTotalsMap = computed(() => {
    const map = new Map()
    previousBranchTotalsData.value.forEach((row) => {
        map.set(row.branch_id, row.total_amount ?? 0)
    })
    return map
})

const sortedBranchTotalsData = computed(() => {
    return [...branchTotalsData.value].sort((a, b) => (b.total_amount ?? 0) - (a.total_amount ?? 0))
})

const branchTotalsDataWithTrend = computed(() => {
    return sortedBranchTotalsData.value.map((row) => ({
        ...row,
        previousAmount: previousBranchTotalsMap.value.get(row.branch_id) ?? 0,
        trendDifference: (row.total_amount ?? 0) - (previousBranchTotalsMap.value.get(row.branch_id) ?? 0),
    }))
})

const previousUserTotalsAggregatedMap = computed(() => {
    const map = new Map()
    previousUserTotalsAggregatedData.value.forEach((row) => {
        map.set(row.user_id, row.total_amount ?? 0)
    })
    return map
})

const sortedUserTotalsAggregatedData = computed(() => {
    return [...userTotalsAggregatedData.value].sort((a, b) => (b.total_amount ?? 0) - (a.total_amount ?? 0))
})

const userTotalsAggregatedDataWithTrend = computed(() => {
    return sortedUserTotalsAggregatedData.value.map((row) => ({
        ...row,
        previousAmount: previousUserTotalsAggregatedMap.value.get(row.user_id) ?? 0,
        trendDifference: (row.total_amount ?? 0) - (previousUserTotalsAggregatedMap.value.get(row.user_id) ?? 0),
    }))
})

void branchTotalsDataWithTrend.value
void userTotalsAggregatedDataWithTrend.value

const totalPatients = computed(() => {
    return tableDataWithTrend.value.reduce((sum, row) => sum + (row.patients_total ?? 0), 0)
})

const totalChronicPatients = computed(() => {
    return tableDataWithTrend.value.reduce((sum, row) => sum + (row.chronic_patients_count ?? 0), 0)
})

const totalShortTermPatients = computed(() => {
    return Math.max(0, totalPatients.value - totalChronicPatients.value)
})

const topBranch = computed(() => branchTableDataWithTrend.value[0] ?? null)
const topUser = computed(() => tableDataWithTrend.value[0] ?? null)
const topDoctor = computed(() => doctorTableDataWithTrend.value[0] ?? null)

const toMonthParam = (d: Date) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    return `${y}-${m}`
}

const toApiDateParam = (d: Date) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

const addDays = (date: Date, days: number) => {
    const next = new Date(date)
    next.setDate(next.getDate() + days)
    return next
}

const getRangeParams = () => {
    if (!startDate.value || !endDate.value) return null

    return {
        date_from: toApiDateParam(startDate.value),
        date_to: toApiDateParam(addDays(endDate.value, 1)),
    }
}

const getPreviousRangeParams = () => {
    if (!startDate.value || !endDate.value) return null

    const msInDay = 24 * 60 * 60 * 1000
    const selectedLengthInDays = Math.floor((endDate.value.getTime() - startDate.value.getTime()) / msInDay) + 1

    const previousEnd = addDays(startDate.value, -1)
    const previousStart = addDays(previousEnd, -(selectedLengthInDays - 1))

    return {
        date_from: toApiDateParam(previousStart),
        date_to: toApiDateParam(addDays(previousEnd, 1)),
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
        tag3: getCssVar('--c-tag3', '#f3f4f6'),
        text: getCssVar('--c-darkgrey', '#374151'),
        grid: getCssVar('--c-light-grey', '#e5e7eb'),
    }
})

const topBranchesChartData = computed(() => {
    const topBranches = branchTableDataWithTrend.value.slice(0, 5)

    return {
        labels: topBranches.map(item => item.branch_name),
        datasets: [
            {
                label: 'Počet pacientov',
                data: topBranches.map(item => item.patients_count ?? 0),
                backgroundColor: chartColors.value.accent,
                borderColor: chartColors.value.accent,
                borderWidth: 1,
                borderRadius: 6,
            },
        ],
    }
})

const topBranchesChartOptions = computed(() => {
    return {
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
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    color: chartColors.value.text,
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
    }
})

const nursePatientsChartData = computed(() => {
    const topNurses = tableDataWithTrend.value.slice(0, 8)

    return {
        labels: topNurses.map(item => item.user_name),
        datasets: [
            {
                label: 'Počet pacientov',
                data: topNurses.map(item => item.patients_total ?? 0),
                backgroundColor: chartColors.value.accent,
                borderColor: chartColors.value.accent,
                borderWidth: 1,
                borderRadius: 6,
            },
        ],
    }
})

const nursePatientsChartOptions = computed(() => {
    return {
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
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    color: chartColors.value.text,
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
    }
})

const patientSplitChartData = computed(() => {
    return {
        labels: ['Dlhodobí', 'Krátkodobí'],
        datasets: [
            {
                data: [totalChronicPatients.value, totalShortTermPatients.value],
                backgroundColor: [
                    chartColors.value.accent,
                    chartColors.value.darkgrey,
                ],
                borderColor: chartColors.value.white,
                borderWidth: 2,
                hoverOffset: 8,
            },
        ],
    }
})

const patientSplitChartOptions = computed(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: chartColors.value.text,
                },
            },
        },
    }
})


function getTrendBadgeClass(diff: number) {
    if (diff > 0) return 'trend-badge trend-up'
    if (diff < 0) return 'trend-badge trend-down'
    return 'trend-badge trend-flat'
}

function getInsurancePatientsCount(row: UserStatisticsRow, companyId: number) {
    return Number(row[`insurance_${companyId}`] ?? 0)
}

function getInsuranceSharePercent(row: UserStatisticsRow, companyId: number) {
    const total = Number(row.patients_total ?? 0)
    if (total <= 0) return 0
    return Math.min(100, (getInsurancePatientsCount(row, companyId) / total) * 100)
}

async function onSubmitFilters() {
    submitted.value = true

    if (!startDate.value || !endDate.value) {
        return
    }

    await loadAllStatistics()
}

async function loadAllStatistics() {
    uiOverlayStore.setContentLoading(true)

    try {
        await Promise.all([
            loadStatistics(),
            loadDoctorStatistics(),
            loadUserTotals(),
            loadBranchStatistics(),
            loadBranchTotals(),
            loadUserTotalsAggregated(),
            loadInsuranceCompanyTotals(),
            load3MonthTrends(),
        ])
    } finally {
        uiOverlayStore.setContentLoading(false)
    }
}

async function loadStatistics() {
    if (!startDate.value || !endDate.value) return

    const rangeParams = getRangeParams()
    const previousRangeParams = getPreviousRangeParams()
    if (!rangeParams || !previousRangeParams) return

    loading.value = true
    try {
        const res = await api.get('/v1/manager/user-statistics', {
            params: rangeParams,
        })

        const prevRes = await api.get('/v1/manager/user-statistics', {
            params: previousRangeParams,
        })

        tableData.value = res.data?.data ?? []
        previousTableData.value = prevRes.data?.data ?? []
        companies.value = res.data?.meta?.companies ?? []
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
    if (!startDate.value || !endDate.value) return

    const rangeParams = getRangeParams()
    const previousRangeParams = getPreviousRangeParams()
    if (!rangeParams || !previousRangeParams) return

    doctorLoading.value = true
    try {
        const res = await api.get('/v1/manager/doctor-statistics', {
            params: rangeParams,
        })

        const prevRes = await api.get('/v1/manager/doctor-statistics', {
            params: previousRangeParams,
        })

        doctorTableData.value = res.data?.data ?? []
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
    if (!startDate.value) return

    userTotalsLoading.value = true
    try {
        const month = toMonthParam(startDate.value)

        const res = await api.get('/v1/manager/user-totals', {
            params: { month },
        })

        userTotalsData.value = res.data?.data ?? []
    } catch (e) {
        console.error('Failed to load user totals', e)
        userTotalsData.value = []
    } finally {
        userTotalsLoading.value = false
    }
}

async function loadBranchStatistics() {
    if (!startDate.value || !endDate.value) return

    const rangeParams = getRangeParams()
    const previousRangeParams = getPreviousRangeParams()
    if (!rangeParams || !previousRangeParams) return

    branchLoading.value = true
    try {
        const res = await api.get('/v1/manager/branch-statistics', {
            params: rangeParams,
        })

        const prevRes = await api.get('/v1/manager/branch-statistics', {
            params: previousRangeParams,
        })

        branchTableData.value = res.data?.data ?? []
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
    if (!startDate.value) return

    branchTotalsLoading.value = true
    try {
        const month = toMonthParam(startDate.value)

        const prevDate = new Date(startDate.value)
        prevDate.setMonth(prevDate.getMonth() - 1)
        const previousMonth = toMonthParam(prevDate)

        const [res, prevRes] = await Promise.all([
            api.get('/v1/batch-documents/company/aggregated-branch', {
                params: { month },
            }),
            api.get('/v1/batch-documents/company/aggregated-branch', {
                params: { month: previousMonth },
            }),
        ])

        branchTotalsData.value = res.data?.data?.items ?? []
        previousBranchTotalsData.value = prevRes.data?.data?.items ?? []
    } catch (e) {
        console.error('Failed to load branch totals', e)
        branchTotalsData.value = []
        previousBranchTotalsData.value = []
    } finally {
        branchTotalsLoading.value = false
    }
}

async function loadUserTotalsAggregated() {
    if (!startDate.value) return

    userTotalsAggregatedLoading.value = true
    try {
        const month = toMonthParam(startDate.value)

        const prevDate = new Date(startDate.value)
        prevDate.setMonth(prevDate.getMonth() - 1)
        const previousMonth = toMonthParam(prevDate)

        const [res, prevRes] = await Promise.all([
            api.get('/v1/batch-documents/company/aggregated-user', {
                params: { month },
            }),
            api.get('/v1/batch-documents/company/aggregated-user', {
                params: { month: previousMonth },
            }),
        ])

        userTotalsAggregatedData.value = res.data?.data?.items ?? []
        previousUserTotalsAggregatedData.value = prevRes.data?.data?.items ?? []
    } catch (e) {
        console.error('Failed to load user totals aggregated', e)
        userTotalsAggregatedData.value = []
        previousUserTotalsAggregatedData.value = []
    } finally {
        userTotalsAggregatedLoading.value = false
    }
}

async function loadInsuranceCompanyTotals() {
    if (!startDate.value) return

    insuranceCompanyTotalsLoading.value = true
    try {
        const month = toMonthParam(startDate.value)

        const res = await api.get('/v1/totals', {
            params: {
                per_page: 100,
                with: 'user,branch,insurance_company',
            },
        })

        const data = res.data?.data?.data ?? []
        const filteredData = data.filter((row: any) => row.month === month)

        const groupedData = filteredData.reduce((acc: any, row: any) => {
            const icId = row.insurance_company_id
            const icName = row.insurance_company?.name || 'Neznáma'

            if (!acc[icId]) {
                acc[icId] = {
                    insurance_company_id: icId,
                    insurance_company_name: icName,
                    points_generated: 0,
                    kilometers_generated: 0,
                    price_paid: 0,
                }
            }

            acc[icId].points_generated += parseFloat(row.points_generated || 0)
            acc[icId].kilometers_generated += parseFloat(row.kilometers_generated || 0)
            acc[icId].price_paid += parseFloat(row.price_paid || 0)

            return acc
        }, {})

        insuranceCompanyTotalsData.value = Object.values(groupedData)
    } catch (e) {
        console.error('Failed to load insurance company totals', e)
        insuranceCompanyTotalsData.value = []
    } finally {
        insuranceCompanyTotalsLoading.value = false
    }
}

async function load3MonthTrends() {
    if (!startDate.value) return

    try {
        const currentMonth = toMonthParam(startDate.value)
        const months = [currentMonth]

        for (let i = 1; i < 3; i++) {
            const d = new Date(startDate.value)
            d.setMonth(d.getMonth() - i)
            months.unshift(toMonthParam(d))
        }

        const userTotalsPromises = months.map((m) =>
            api.get('/v1/manager/user-totals-aggregated', { params: { month: m } })
        )
        const userTotalsResults = await Promise.all(userTotalsPromises)
        threeMonthTrendData.value = userTotalsResults.map((res, i) => ({
            month: months[i],
            total_points: res.data?.data?.[0]?.total_points ?? 0,
            total_kilometers: res.data?.data?.[0]?.total_kilometers ?? 0,
        }))

        const patientsPromises = months.map((m) =>
            api.get('/v1/manager/user-statistics', { params: { month: m } })
        )
        const patientsResults = await Promise.all(patientsPromises)
        threeMonthPatientsData.value = patientsResults.map((res, i) => ({
            month: months[i],
            patients_total: res.data?.data?.reduce((sum: number, row: any) => sum + (row.patients_total ?? 0), 0) ?? 0,
        }))

        const branchTotalsPromises = months.map((m) =>
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

onMounted(async () => {
    await loadAllStatistics()
})
</script>

<template>
    <div class="statistics-page flex flex-col gap-5">
        <div class="mb-8 flex flex-col gap-4">
            <div class="bg-tag3 no-print p-4 rounded-md">
                <div class="grid grid-cols-12 gap-4 w-full md:w-auto">
                  <div class="col-span-6">
                      <label class="block text-normal mb-1">Dátum od</label>
                      <DatePicker
                          v-model="startDate"
                          dateFormat="dd.mm.yy"
                          :manualInput="false"
                          inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                          fluid
                      />
                      <small v-if="submitted && !startDate" class="text-danger">
                          Dátum od je povinný.
                      </small>
                  </div>

                  <div class="col-span-6">
                      <label class="block text-normal mb-1">Dátum do</label>
                      <DatePicker
                          v-model="endDate"
                          dateFormat="dd.mm.yy"
                          :manualInput="false"
                          inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                          fluid
                      />
                      <small v-if="submitted && !endDate" class="text-danger">
                          Dátum do je povinný.
                      </small>
                  </div>
                </div>
            </div>

            <div class="flex justify-end">
            <Button
              @click="onSubmitFilters"
              class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white w-100">
              Načítať
              <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
            </Button>
          </div>
        </div>

        <div class="summary-grid">
            <div class="bg-darkgrey text-white rounded-md p-4">
                <div class="text-tag1">Pacienti spolu</div>
                <div class="text-2xl font-bold">{{ totalPatients }}</div>
            </div>

            <div class="bg-darkgrey text-white rounded-md p-4">
                <div class="text-tag1">Dlhodobí pacienti</div>
                <div class="text-2xl font-bold">{{ totalChronicPatients }}</div>
            </div>

            <div class="bg-darkgrey text-white rounded-md p-4">
                <div class="text-tag1">Krátkodobí pacienti</div>
                <div class="text-2xl font-bold">{{ totalShortTermPatients }}</div>
            </div>

            <div class="bg-darkgrey text-white rounded-md p-4">
                <div class="text-tag1">Najvýkonnejšia pobočka</div>
                <div class="text-2xl font-bold">{{ topBranch?.branch_name ?? '-' }}</div>
                <div class="text-mini">{{ topBranch?.patients_count ?? 0 }} pacientov</div>
            </div>

            <div class="bg-darkgrey text-white rounded-md p-4">
                <div class="text-tag1">Najvýkonnejší užívateľ</div>
                <div class="text-2xl font-bold">{{ topUser?.user_name ?? '-' }}</div>
                <div class="text-mini">{{ topUser?.patients_total ?? 0 }} pacientov</div>
            </div>

            <div class="bg-darkgrey text-white rounded-md p-4">
                <div class="text-tag1">Najvýkonnejší lekár</div>
                <div class="text-2xl font-bold">{{ topDoctor?.doctor_name ?? '-' }}</div>
                <div class="text-mini">{{ topDoctor?.patients_count ?? 0 }} pacientov</div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 no-print mb-8">
            <div class="border-darkgrey border rounded-md p-4">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <span class="section-title">Pacienti podľa zdravotných sestier</span>
                    </div>
                </div>

                <div class="">
                    <Chart type="bar" :data="nursePatientsChartData" :options="nursePatientsChartOptions" />
                </div>
            </div>

            <div class="border-darkgrey border rounded-md p-4">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <span class="section-title">Top pobočky</span>
                    </div>
                </div>

                <div class="">
                    <Chart type="bar" :data="topBranchesChartData" :options="topBranchesChartOptions" />
                </div>
            </div>

            <div class="border-darkgrey border rounded-md p-4">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <span class="section-title">Dlhodobí vs. krátkodobí pacienti</span>
                    </div>
                </div>

                <div class="">
                    <Chart type="doughnut" :data="patientSplitChartData" :options="patientSplitChartOptions" />
                </div>
            </div>
        </div>


        <div class="no-print mb-8">
            <div class="section-header">
              <span class="section-title">Najvýkonnejšie pobočky podľa počtu pacientov</span>
            </div>

            <div class="overflow-x-auto">
                <DataTable :value="branchTableDataWithTrend" stripedRows class="text-sm">
                    <Column header="#" style="width: 3rem">
                        <template #body="{ index }">
                            {{ index + 1 }}
                        </template>
                    </Column>

                    <Column field="branch_name" header="Pobočka" />

                    <Column field="patients_count" header="Počet pacientov" align="center">
                        <template #body="{ data }">
                            {{ data.patients_count ?? 0 }}
                        </template>
                    </Column>

                    <Column header="Trend" align="center" style="width: 80px">
                        <template #body="{ data }">
                            <span :class="getTrendBadgeClass(data.trendDifference)">
                                <i v-if="data.trendDifference > 0" class="bi bi-arrow-up"></i>
                                <i v-else-if="data.trendDifference < 0" class="bi bi-arrow-down"></i>
                                <i v-else class="bi bi-dash"></i>
                                {{ data.trendDifference > 0 ? '+' : '' }}{{ data.trendDifference }}
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <div class="no-print mb-6">
            <div class="section-header">
                <span class="section-title">Najvýkonnejší užívatelia podľa počtu pacientov</span>
            </div>

            <div class="overflow-x-auto">
                <DataTable :value="tableDataWithTrend" stripedRows class="text-sm">
                    <Column header="#" style="width: 3rem">
                        <template #body="{ index }">
                            {{ index + 1 }}
                        </template>
                    </Column>

                    <Column field="user_name" header="Užívateľ" />
                    <Column field="branch_name" header="Pobočka" />

                    <Column
                        v-for="company in sortedCompanies"
                        :key="company.id"
                        :header="company.name.split(' ')[0]"
                        align="center"
                    >
                        <template #body="{ data }">
                            <div class="insurance-cell">
                                <div class="flex items-center gap-2 w-full justify-between">
                                  <span class="text-mini">{{ getInsurancePatientsCount(data, company.id) }}</span>
                                  <span class="text-mini text-accent">{{ getInsuranceSharePercent(data, company.id).toFixed(1) }}%</span>
                                </div>
                                <div class="insurance-cell-track">
                                    <div
                                        class="insurance-cell-fill"
                                        :style="{ width: `${getInsuranceSharePercent(data, company.id)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="patients_total" header="Spolu" align="center">
                        <template #body="{ data }">
                            {{ data.patients_total ?? 0 }}
                        </template>
                    </Column>

                    <Column header="Trend" align="center" style="width: 80px">
                        <template #body="{ data }">
                            <span :class="getTrendBadgeClass(data.trendDifference)">
                                <i v-if="data.trendDifference > 0" class="bi bi-arrow-up"></i>
                                <i v-else-if="data.trendDifference < 0" class="bi bi-arrow-down"></i>
                                <i v-else class="bi bi-dash"></i>
                                {{ data.trendDifference > 0 ? '+' : '' }}{{ data.trendDifference }}
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <div class="no-print mb-8">
            <div class="section-header">
              <span class="section-title">Dlhodobí vs. krátkodobí pacienti</span>
            </div>

            <div class="overflow-x-auto">
                <DataTable :value="termBreakdownTableData" stripedRows class="text-sm">
                    <Column header="#" style="width: 3rem">
                        <template #body="{ index }">
                            {{ index + 1 }}
                        </template>
                    </Column>

                    <Column field="user_name" header="Užívateľ" />
                    <Column field="branch_name" header="Pobočka" />

                    <Column field="longTermPercentage" header="Dlhodobí" style="min-width: 180px">
                        <template #body="{ data }">
                            <div class="progress-cell">
                                <div class="flex items-center gap-2 w-full justify-between">
                                    <span class="text-mini">{{ data.longTerm ?? 0 }}</span>
                                    <span class="text-mini text-accent">{{ (data.longTermPercentage ?? 0).toFixed(1) }}%</span>
                                </div>
                                <div class="insurance-cell-track">
                                    <div
                                        class="insurance-cell-fill"
                                        :style="{ width: `${data.longTermPercentage ?? 0}%` }"
                                    ></div>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="shortTerm" header="Krátkodobí" align="center">
                        <template #body="{ data }">
                            {{ data.shortTerm ?? 0 }}
                        </template>
                    </Column>

                    <Column field="patients_total" header="Spolu" align="center">
                        <template #body="{ data }">
                            {{ data.patients_total ?? 0 }}
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <div class="no-print mb-8">
            <div class="section-header">
              <span class="section-title">Najvýkonnejší lekári podľa počtu pacientov</span>
            </div>

            <div class="overflow-x-auto">
                <DataTable :value="doctorTableDataWithTrend" stripedRows class="text-sm">
                    <Column header="#" style="width: 3rem">
                        <template #body="{ index }">
                            {{ index + 1 }}
                        </template>
                    </Column>

                    <Column field="doctor_name" header="Lekár" />

                    <Column field="patients_count" header="Počet pacientov" align="center">
                        <template #body="{ data }">
                            {{ data.patients_count ?? 0 }}
                        </template>
                    </Column>

                    <Column header="Trend" align="center" style="width: 80px">
                        <template #body="{ data }">
                            <span :class="getTrendBadgeClass(data.trendDifference)">
                                <i v-if="data.trendDifference > 0" class="bi bi-arrow-up"></i>
                                <i v-else-if="data.trendDifference < 0" class="bi bi-arrow-down"></i>
                                <i v-else class="bi bi-dash"></i>
                                {{ data.trendDifference > 0 ? '+' : '' }}{{ data.trendDifference }}
                            </span>
                        </template>
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

.summary-label {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.35rem;
}

.summary-value {
    font-size: 1.75rem;
    line-height: 1.2;
    font-weight: 700;
    color: #111827;
}

.summary-value-sm {
    font-size: 1.1rem;
}

.summary-meta {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.section-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
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

.section-icon {
    font-size: 1.1rem;
    color: var(--accent, #2563eb);
}

.section-title {
    font-size: 1.05rem;
    font-weight: 600;
}

.section-count {
    font-size: 0.875rem;
    color: #6b7280;
}

.trend-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 9999px;
    padding: 0.25rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 72px;
}

.trend-up {
    background: var(--c-success);
    color: var(--c-white);
}

.trend-down {
    background: var(--c-danger);
    color: var(--c-white);
}

.trend-flat {
    background: var(--c-light-grey);
    color: var(--c--darkgray);
}

.progress-cell {
    min-width: 160px;
}

.progress-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.75rem;
    margin-bottom: 0.35rem;
}

.progress-track {
    width: 100%;
    height: 0.45rem;
    background: #e5e7eb;
    border-radius: 9999px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: var(--accent, #2563eb);
    border-radius: 9999px;
}

.insurance-cell {
    min-width: 68px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.insurance-cell-value {
    font-weight: 600;
    line-height: 1;
}

.insurance-cell-percent {
    font-size: 0.72rem;
    color: #6b7280;
    line-height: 1;
}

.insurance-cell-track {
    width: 100%;
    height: 0.32rem;
    background: #e5e7eb;
    border-radius: 9999px;
    overflow: hidden;
}

.insurance-cell-fill {
    height: 100%;
    background: var(--c-accent);
    border-radius: 9999px;
}

.finance-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1rem;
}

.mini-stat {
    background: #fafafa;
    border: 1px solid #ececec;
    border-radius: 0.5rem;
    padding: 1rem;
}

.mini-stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.35rem;
}

.mini-stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
}


@media (min-width: 768px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .finance-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 1280px) {
    .summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

</style>