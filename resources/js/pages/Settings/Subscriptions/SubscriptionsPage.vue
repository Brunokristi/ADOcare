<template>
  <div class="flex flex-col gap-5 p-2">
    <h1 class="text-heading-accent">Predplatné spoločnosti</h1>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin">
        <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-5">

    <!-- Summary Cards -->
    <section class="">
      <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
          <div class="rounded-md bg-darkgrey p-4 h-full">
            <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Balík</div>
            <div class="text-heading text-white">{{ selectedTier?.name || 'Bez balíka' }}</div>
          </div>
        </div>

        <div class="col-span-12 md:col-span-6 xl:col-span-3">
          <div class="rounded-md bg-darkgrey p-4 h-full">
            <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Používatelia</div>
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
            <div class="text-heading text-white">{{ getStatusLabel(subscription?.status) }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Active Months Section -->
    <section class="bg-tag3 rounded-md p-5">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm text-accent">Aktívne mesiace</h3>
        <div class="text-mini text-lightgrey">Rok: {{ selectedYear }}</div>
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

    <!-- Payment History Section -->
    <section class="bg-tag3 rounded-md p-5">
      <div class="mb-4">
        <h3 class="text-sm text-accent">História platieb</h3>
      </div>

      <div class="hidden md:grid grid-cols-12 gap-2 mb-2 text-mini text-darkgrey tracking-wide">
        <div class="md:col-span-3">Dátum prijatia</div>
        <div class="md:col-span-3">Suma (€)</div>
        <div class="md:col-span-6">Poznámka</div>
      </div>

      <div
        v-for="(payment, index) in payments"
        :key="index"
        class="grid grid-cols-12 gap-2 mb-3 rounded-md bg-white p-3 md:bg-transparent md:p-0"
      >
        <div class="col-span-12 md:col-span-3">
          <label class="block md:hidden text-normal mb-1">Dátum prijatia</label>
          <div class="text-normal">{{ formatDate(payment.received_at) }}</div>
        </div>

        <div class="col-span-12 md:col-span-3">
          <label class="block md:hidden text-normal mb-1">Suma (€)</label>
          <div class="text-normal font-semibold">{{ formatCurrency(payment.amount) }}</div>
        </div>

        <div class="col-span-12 md:col-span-6">
          <label class="block md:hidden text-normal mb-1">Poznámka</label>
          <div class="text-normal">{{ payment.notes || '—' }}</div>
        </div>
      </div>

      <div v-if="!payments.length" class="text-center py-8 text-lightgrey">
        Zatiaľ žiadne platby
      </div>
    </section>
  </div>
</template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import useAuthStore from '@/stores/auth'

interface Subscription {
  id: number
  name: string
  subscription_tier_id: number | null
  subscription_price_monthly: number | null
  subscription_users_limit_override: number | null
  status: 'active' | 'trial' | 'paused' | 'cancelled'
  subscription_status: 'active' | 'trial' | 'paused' | 'cancelled'
}

interface Payment {
  received_at: string | null
  amount: number | null
  notes: string | null
}

interface SubscriptionTier {
  id: number
  name: string
  price_monthly: number | null
  users_limit: number | null
}

interface CompanySubscriptionDetails extends Subscription {
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

const authStore = useAuthStore()
const loading = ref(true)
const subscription = ref<Subscription | null>(null)
const payments = ref<Payment[]>([])
const activeMonths = ref<number[]>([])
const selectedYear = ref<number>(new Date().getFullYear())
const paidMonthsByYear = ref<Array<{ year: number; months: number[] }>>([])
const tiers = ref<SubscriptionTier[]>([])

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
  const current = new Date().getFullYear()
  const years = new Set<number>([current, current - 1, current - 2, current + 1])

  paidMonthsByYear.value.forEach((item) => years.add(item.year))

  return Array.from(years)
    .sort((a, b) => b - a)
    .map((year) => ({ label: String(year), value: year }))
})

const selectedTier = computed(() => {
  return tiers.value.find((tier) => tier.id === subscription.value?.subscription_tier_id) || null
})

const effectiveMonthlyPrice = computed(() => {
  if (subscription.value?.subscription_price_monthly !== null && subscription.value?.subscription_price_monthly !== undefined) {
    return subscription.value.subscription_price_monthly
  }

  return selectedTier.value?.price_monthly ?? null
})

const effectiveUsersLimit = computed(() => {
  if (
    subscription.value?.subscription_users_limit_override !== null &&
    subscription.value?.subscription_users_limit_override !== undefined
  ) {
    return subscription.value.subscription_users_limit_override
  }

  return selectedTier.value?.users_limit ?? null
})

onMounted(async () => {
  await loadSubscriptionData()
})

const loadSubscriptionData = async () => {
  try {
    loading.value = true
    
    // Get company ID from auth store
    const companyId = authStore.user?.company_id
    
    if (!companyId) {
      console.error('No company ID found in auth store')
      return
    }

    // Fetch tiers and subscription details in parallel
    const [tiersData, details] = await Promise.all([
      api.fetchEntities<SubscriptionTier>('v1/subscription-tiers', {
        filter: { is_active: 1 },
        sort: 'sort_order,name',
      }),
      api.fetchEntity<CompanySubscriptionDetails>(`v1/companies/${companyId}/subscription-details`),
    ])

    tiers.value = tiersData

    subscription.value = {
      id: details.id,
      name: details.name,
      subscription_tier_id: details.subscription_tier_id,
      subscription_price_monthly: details.subscription_price_monthly,
      subscription_users_limit_override: details.subscription_users_limit_override,
      status: details.subscription_status,
      subscription_status: details.subscription_status,
    }

    paidMonthsByYear.value = details.paid_months_by_year ?? []

    // Load months for selected year
    const selected = paidMonthsByYear.value.find((x) => Number(x.year) === Number(selectedYear.value))
    activeMonths.value = selected ? [...selected.months] : []

    // Load payments
    payments.value = (details.payments ?? []).map((payment) => ({
      received_at: payment.received_at,
      amount: payment.amount,
      notes: payment.notes,
    }))
  } catch (error) {
    console.error('Error loading subscription data:', error)
  } finally {
    loading.value = false
  }
}

const formatCurrency = (value: number | null): string => {
  if (value === null || value === undefined) return '—'
  return `${Number(value).toFixed(2)} €`
}

const formatDate = (date: string | null): string => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('sk-SK', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const getStatusLabel = (status: string | undefined): string => {
  const labels: Record<string, string> = {
    active: 'Aktívne',
    trial: 'Trial',
    paused: 'Pozastavené',
    cancelled: 'Zrušené',
  }
  return labels[status || ''] || '—'
}

const getMonthDisplay = (month: number, year: number): string => {
  const monthName = monthOptions.find(m => m.value === month)?.label || '—'
  return `${monthName} ${year}`
}
</script>
