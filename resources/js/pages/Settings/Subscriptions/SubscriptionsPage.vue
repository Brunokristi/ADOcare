<template>
  <div class="flex flex-col gap-5 py-4">
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin">
        <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          />
        </svg>
      </div>
    </div>

    <div v-else class="space-y-5">
      <section>
        <div class="grid grid-cols-12 gap-4">
          <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <div class="rounded-md bg-darkgrey p-4 h-full">
              <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Balík</div>
              <div class="text-heading text-white">{{ displayTierName }}</div>
            </div>
          </div>

          <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <div class="rounded-md bg-darkgrey p-4 h-full">
              <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Maximálny počet používateľov</div>
              <div class="text-heading text-white">{{ effectiveUsersLimit ?? 'Neobmedzene' }}</div>
            </div>
          </div>

          <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <div class="rounded-md bg-darkgrey p-4 h-full">
              <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Cena / mesiac</div>
              <div class="text-heading text-white">{{ formatCurrency(effectiveMonthlyPrice) }}</div>
            </div>
          </div>

          <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <div class="rounded-md bg-darkgrey p-4 h-full">
              <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Stav</div>
              <div class="text-heading text-white">{{ getStatusLabel(subscription?.subscription_status) }}</div>
            </div>
          </div>
        </div>
      </section>

      <section class="bg-tag3 rounded-md p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h3 class="text-sm text-accent">Predplatené mesiace</h3>

          <div class="w-40">
            <Select
              v-model="selectedYear"
              :options="yearOptions"
              optionLabel="label"
              optionValue="value"
              fluid
              class="border-0!"
              @change="onYearChange"
            />
          </div>
        </div>

        <div class="grid grid-cols-12 gap-3">
          <div
            v-for="month in monthOptions"
            :key="month.value"
            class="col-span-6 md:col-span-4 xl:col-span-2"
          >
            <div
              class="w-full rounded-md p-3 text-left"
              :class="activeMonths.includes(month.value)
                ? 'bg-accent text-white border-accent'
                : 'bg-white text-darkgrey border-darkgrey'"
            >
              <div class="text-normal">{{ month.label }}</div>
            </div>
          </div>
        </div>
      </section>

        <section class="bg-tag3 rounded-md p-5">
        <div class="mb-4">
          <h3 class="text-sm text-accent">História platieb</h3>
        </div>

        <UniversalDataTable :options="paymentTableOptions" />
        </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import api from '@/services/api'
import type { DataTableOptions } from '@/types/datatable'

interface SubscriptionTier {
  id: number
  name: string
  price_monthly: number | null
  users_limit: number | null
}

interface CompanySubscription {
  id: number
  name: string
  subscription_tier_id: number | null
  subscription_price_monthly: number | null
  subscription_users_limit_override: number | null
  subscription_status: 'active' | 'trial' | 'paused' | 'cancelled'
  subscription_tier?: SubscriptionTier | null
}

interface CompanySubscriptionDetails extends CompanySubscription {
  payments: Array<{
    received_at: string | null
    amount: number | null
    notes: string | null
  }>
  paid_months_by_year: Array<{
    year: number
    months: number[]
  }>
}

const loading = ref(false)
const subscription = ref<CompanySubscription | null>(null)
const selectedYear = ref<number>(new Date().getFullYear())
const activeMonths = ref<number[]>([])
const paidMonthsByYear = ref<Array<{ year: number; months: number[] }>>([])

const monthOptions = [
  { label: 'Január', value: 1 },
  { label: 'Február', value: 2 },
  { label: 'Marec', value: 3 },
  { label: 'Apríl', value: 4 },
  { label: 'Máj', value: 5 },
  { label: 'Jún', value: 6 },
  { label: 'Júl', value: 7 },
  { label: 'August', value: 8 },
  { label: 'September', value: 9 },
  { label: 'Október', value: 10 },
  { label: 'November', value: 11 },
  { label: 'December', value: 12 },
]

const yearOptions = computed(() => {
  const years = new Set<number>([new Date().getFullYear()])
  paidMonthsByYear.value.forEach((item) => years.add(item.year))

  return Array.from(years)
    .sort((a, b) => b - a)
    .map((year) => ({ label: String(year), value: year }))
})

const selectedTier = computed(() => subscription.value?.subscription_tier ?? null)

const displayTierName = computed(() => {
  if (selectedTier.value?.name) return selectedTier.value.name
  if (subscription.value?.subscription_tier_id) return `Balik #${subscription.value.subscription_tier_id}`
  return 'Bez balika'
})

const effectiveMonthlyPrice = computed(() => {
  if (!subscription.value) return null
  if (subscription.value.subscription_price_monthly !== null && subscription.value.subscription_price_monthly !== undefined) {
    return subscription.value.subscription_price_monthly
  }
  return selectedTier.value?.price_monthly ?? null
})

const effectiveUsersLimit = computed(() => {
  if (!subscription.value) return null
  if (
    subscription.value.subscription_users_limit_override !== null &&
    subscription.value.subscription_users_limit_override !== undefined
  ) {
    return subscription.value.subscription_users_limit_override
  }
  return selectedTier.value?.users_limit ?? null
})

const paymentTableOptions = computed<DataTableOptions<any>>(() => ({
  endpointUrl: 'v1/my-company/subscription-payments',
  defaultPageSize: 10,
  columns: [
    {
      field: 'received_at',
      header: 'Dátum prijatia',
      sortable: true,
      render: (value) => formatDate((value as string | null) ?? null),
    },
    {
      field: 'amount',
      header: 'Suma (EUR)',
      sortable: true,
      render: (value) => formatCurrency((value as number | null) ?? null),
    },
    {
      field: 'notes',
      header: 'Poznámka',
      render: (value) => ((value as string | null) || '—'),
    },
  ],
}))

onMounted(async () => {
  await loadSubscriptionData()
})

function onYearChange() {
  const selected = paidMonthsByYear.value.find((x) => Number(x.year) === Number(selectedYear.value))
  activeMonths.value = selected ? [...selected.months] : []
}

async function loadSubscriptionData() {
  loading.value = true

  try {
    const details = await api.fetchEntity<CompanySubscriptionDetails>('v1/my-company/subscription-details')

    subscription.value = {
      id: details.id,
      name: details.name,
      subscription_tier_id: details.subscription_tier_id,
      subscription_price_monthly: details.subscription_price_monthly,
      subscription_users_limit_override: details.subscription_users_limit_override,
      subscription_status: details.subscription_status,
      subscription_tier: details.subscription_tier ?? null,
    }

    paidMonthsByYear.value = details.paid_months_by_year ?? []

    if (paidMonthsByYear.value.length > 0) {
      const latestYear = paidMonthsByYear.value
        .map((item) => item.year)
        .sort((a, b) => b - a)[0]

      if (latestYear !== undefined) {
        selectedYear.value = latestYear
      }
    }

    onYearChange()
  } catch (error) {
    console.error('Error loading subscription data:', error)
    subscription.value = null
    activeMonths.value = []
  } finally {
    loading.value = false
  }
}

function formatCurrency(value: number | null): string {
  if (value === null || value === undefined) return '—'
  return `${Number(value).toFixed(2)} €`
}

function formatDate(value: string | null): string {
  if (!value) return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'

  return date.toLocaleDateString('sk-SK', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function getStatusLabel(status: CompanySubscription['subscription_status'] | undefined): string {
  const labels: Record<string, string> = {
    active: 'Aktívne',
    trial: 'Trial',
    paused: 'Pozastavené',
    cancelled: 'Zrušené',
  }

  return labels[status || ''] || '—'
}
</script>
