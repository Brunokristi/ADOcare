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
      <div v-if="billingError" class="rounded-md bg-danger p-4 text-normal text-white">
        {{ billingError }}
      </div>

      <!-- CURRENT BILLING STATE - the paid subscription always wins over the trial. -->
      <section class="bg-tag3 rounded-md p-5">
        <h3 class="text-sm text-accent mb-4">Aktuálne predplatné</h3>

        <div v-if="current?.type === 'subscription'" class="grid grid-cols-12 gap-4">
          <div class="col-span-12 md:col-span-3">
            <div class="text-mini uppercase tracking-wide text-lightgrey mb-1">Balík</div>
            <div class="text-heading text-white">{{ current.subscription?.plan?.name ?? '—' }}</div>
          </div>
          <div class="col-span-12 md:col-span-3">
            <div class="text-mini uppercase tracking-wide text-lightgrey mb-1">Stav</div>
            <div class="text-heading text-white">{{ formatSubscriptionStatus(current.subscription?.status) }}</div>
          </div>
          <div class="col-span-12 md:col-span-3">
            <div class="text-mini uppercase tracking-wide text-lightgrey mb-1">Cena</div>
            <div class="text-heading text-white">{{ formatPrice(current.subscription?.price) }}</div>
          </div>
          <div v-if="current.subscription?.current_period_start || current.subscription?.current_period_end" class="col-span-12 md:col-span-3">
            <div class="text-mini uppercase tracking-wide text-lightgrey mb-1">Aktuálne obdobie</div>
            <div class="text-heading text-white">
              {{ formatDate(current.subscription?.current_period_start ?? null) }} – {{ formatDate(current.subscription?.current_period_end ?? null) }}
            </div>
          </div>
          <div v-if="current.subscription?.cancel_at_period_end" class="col-span-12 text-normal text-lightgrey">
            Predplatné bude zrušené na konci aktuálneho obdobia.
          </div>
          <div v-else-if="current.subscription?.canceled_at" class="col-span-12 text-normal text-lightgrey">
            Zrušené {{ formatDate(current.subscription.canceled_at) }}.
          </div>
        </div>

        <div v-else-if="current?.type === 'trial'" class="text-normal text-lightgrey">
          Aktuálne využívate skúšobné obdobie - žiadne platené predplatné ešte nie je aktivované.
        </div>

        <div v-else-if="current?.type === 'expired_trial'" class="text-normal text-lightgrey">
          Skúšobné obdobie skončilo. Vyberte si platený balík nižšie a pokračujte v používaní ADOcare.
        </div>

        <div v-else-if="!billingProvisioned" class="text-normal text-lightgrey">
          Fakturačné údaje pre túto spoločnosť ešte neboli nastavené.
        </div>

        <div v-else class="text-normal text-lightgrey">
          Momentálne nemáte žiadne aktívne platené predplatné.
        </div>
      </section>

      <!-- Trial shown as separate historical/informational context only - never overrides the section above. -->
      <section v-if="trial?.active || trial?.expired" class="rounded-md bg-darkgrey p-4">
        <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Skúšobné obdobie</div>
        <div class="text-heading text-white">
          {{ trial.active ? 'Aktívne' : 'Skončilo' }}
          <span v-if="current?.type === 'subscription'" class="text-normal text-lightgrey font-normal">
            (nahradené platým predplatným)
          </span>
        </div>
      </section>

      <section class="bg-tag3 rounded-md p-5">
        <h3 class="text-sm text-accent mb-4">História platieb</h3>

        <div v-if="payments.length === 0" class="text-normal text-lightgrey">
          Zatiaľ nemáte žiadne platby.
        </div>

        <table v-else class="w-full text-normal text-white">
          <thead>
            <tr class="text-mini uppercase tracking-wide text-lightgrey text-left">
              <th class="pb-2">Dátum</th>
              <th class="pb-2">Suma</th>
              <th class="pb-2">Stav</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in payments" :key="payment.id" class="border-t border-darkgrey">
              <td class="py-2">{{ formatDate(payment.date) }}</td>
              <td class="py-2">{{ formatCurrency(payment.amount, payment.currency) }}</td>
              <td class="py-2">{{ formatPaymentStatus(payment.status) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="bg-tag3 rounded-md p-5">
        <h3 class="text-sm text-accent mb-4">Faktúry</h3>

        <div v-if="invoices.length === 0" class="text-normal text-lightgrey">
          Zatiaľ nemáte žiadne faktúry.
        </div>

        <table v-else class="w-full text-normal text-white">
          <thead>
            <tr class="text-mini uppercase tracking-wide text-lightgrey text-left">
              <th class="pb-2">Faktúra</th>
              <th class="pb-2">Dátum</th>
              <th class="pb-2">Suma</th>
              <th class="pb-2">Stav</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="invoice in invoices" :key="invoice.id" class="border-t border-darkgrey">
              <td class="py-2">{{ invoice.number ?? '—' }}</td>
              <td class="py-2">{{ formatDate(invoice.date) }}</td>
              <td class="py-2">{{ formatCurrency(invoice.amount_paid ?? invoice.amount_due, invoice.currency) }}</td>
              <td class="py-2">{{ formatPaymentStatus(invoice.status) }}</td>
              <td class="py-2 text-right whitespace-nowrap">
                <a v-if="invoice.view_url" :href="invoice.view_url" target="_blank" rel="noopener" class="text-accent underline mr-3">Zobraziť</a>
                <a v-if="invoice.pdf_url" :href="invoice.pdf_url" target="_blank" rel="noopener" class="text-accent underline">PDF</a>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="bg-tag3 rounded-md p-5">
        <h3 class="text-sm text-accent mb-4">Dostupné balíky</h3>

        <div v-if="plans.length === 0" class="text-normal text-lightgrey">
          Momentálne nie sú dostupné žiadne balíky.
        </div>

        <div v-else class="grid grid-cols-12 gap-4">
          <div
            v-for="plan in plans"
            :key="plan.id"
            class="col-span-12 md:col-span-6 xl:col-span-4 rounded-md bg-darkgrey p-4 flex flex-col gap-3"
          >
            <div>
              <div class="text-heading text-white">{{ plan.name }}</div>
              <div v-if="plan.description" class="text-normal text-lightgrey mt-1">{{ plan.description }}</div>
            </div>

            <ul v-if="plan.features?.length" class="text-normal text-lightgrey list-disc pl-4">
              <li v-for="feature in plan.features" :key="feature">{{ feature }}</li>
            </ul>

            <div class="flex flex-col gap-2 mt-auto">
              <div
                v-for="price in plan.prices"
                :key="price.id"
                class="flex items-center justify-between rounded-md bg-tag3 px-3 py-2"
              >
                <span class="text-normal text-white">{{ formatPrice(price) }}</span>
                <Button
                  label="Vybrať"
                  :loading="checkoutLoadingPriceId === price.id"
                  class="bg-accent! border-0!"
                  @click="startCheckout(price.id)"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="bg-tag3 rounded-md p-5">
        <div class="mb-4">
          <h3 class="text-sm text-accent">História platieb (legacy)</h3>
        </div>

        <UniversalDataTable :options="paymentTableOptions" />
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import api from '@/services/api'
import type { DataTableOptions } from '@/types/datatable'

interface PlanPrice {
  id: number
  amount: number
  currency: string
  interval: string
}

interface Plan {
  id: number
  name: string
  description?: string | null
  features?: string[]
  prices: PlanPrice[]
}

interface Subscription {
  id: number
  status: string
  plan?: { id: number; name: string } | null
  price?: PlanPrice | null
  current_period_start?: string | null
  current_period_end?: string | null
  canceled_at?: string | null
  ended_at?: string | null
  cancel_at_period_end?: boolean
}

interface Payment {
  id: number
  date: string | null
  amount: number
  currency: string
  status: string
  payment_method?: string | null
  invoice_id?: number | null
}

interface Invoice {
  id: number
  number?: string | null
  date: string | null
  amount_due: number
  amount_paid: number
  currency: string
  status: string
  period_start?: string | null
  period_end?: string | null
  view_url?: string | null
  pdf_url?: string | null
}

interface TrialState {
  active: boolean
  expired?: boolean
}

interface CurrentBillingState {
  type: 'subscription' | 'trial' | 'expired_trial' | 'none'
  subscription?: Subscription
  trial?: TrialState
}

const loading = ref(false)
const billingError = ref<string | null>(null)
const trial = ref<TrialState | null>(null)
const current = ref<CurrentBillingState | null>(null)
const billingProvisioned = ref(false)
const payments = ref<Payment[]>([])
const invoices = ref<Invoice[]>([])
const plans = ref<Plan[]>([])
const checkoutLoadingPriceId = ref<number | null>(null)

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
  await loadBillingData()
})

async function loadBillingData() {
  loading.value = true
  billingError.value = null

  try {
    const [subscriptionRes, plansRes] = await Promise.all([
      api.get('v1/billing/subscription'),
      api.get('v1/billing/plans'),
    ])

    trial.value = subscriptionRes.data?.data?.trial ?? null
    current.value = subscriptionRes.data?.data?.current ?? null
    billingProvisioned.value = Boolean(subscriptionRes.data?.data?.billing_provisioned)
    payments.value = subscriptionRes.data?.data?.payments ?? []
    invoices.value = subscriptionRes.data?.data?.invoices ?? []
    plans.value = plansRes.data?.data ?? []
  } catch (error: any) {
    console.error('Error loading billing data:', error)
    billingError.value = error?.response?.data?.message ?? 'Nepodarilo sa načítať fakturačné údaje.'
  } finally {
    loading.value = false
  }
}

async function startCheckout(planPriceId: number) {
  checkoutLoadingPriceId.value = planPriceId
  billingError.value = null

  try {
    const res = await api.post('v1/billing/checkout', {
      plan_price_id: planPriceId,
      success_url: `${window.location.origin}/billing/success`,
      cancel_url: `${window.location.origin}/billing/cancel`,
    })

    const checkoutUrl: string | undefined = res.data?.data?.checkout_url

    // Creating the Checkout Session is not the same as paying - this only lets us send
    // the user to Stripe. The actual subscription only activates via StudioKristian's
    // own webhook processing once Stripe confirms payment.
    if (!checkoutUrl || !checkoutUrl.startsWith('https://')) {
      billingError.value = 'Fakturačná služba nevrátila platnú platobnú URL. Skúste to prosím znova.'
      return
    }

    window.location.href = checkoutUrl
  } catch (error: any) {
    console.error('Error starting checkout:', error)
    billingError.value = error?.response?.data?.message ?? 'Nepodarilo sa spustiť platbu.'
  } finally {
    checkoutLoadingPriceId.value = null
  }
}

function formatPrice(price: PlanPrice | null | undefined): string {
  if (!price) return '—'
  return `${formatCurrency(price.amount, price.currency)} / ${formatInterval(price.interval)}`
}

function formatInterval(interval: string): string {
  const labels: Record<string, string> = {
    month: 'mesiac',
    monthly: 'mesiac',
    year: 'rok',
    yearly: 'rok',
  }
  return labels[interval] ?? interval
}

// StudioKristian/Stripe amounts are always in the smallest currency unit (cents for EUR).
function formatCurrency(value: number | null | undefined, currency = 'EUR'): string {
  if (value === null || value === undefined) return '—'
  return `${(Number(value) / 100).toFixed(2)} ${currency}`
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

function formatSubscriptionStatus(status: string | undefined): string {
  const labels: Record<string, string> = {
    active: 'Aktívne',
    trialing: 'Trial',
    past_due: 'Po splatnosti',
    canceled: 'Zrušené',
    cancelled: 'Zrušené',
    unpaid: 'Nezaplatené',
  }

  return labels[(status ?? '').toLowerCase()] || (status || '—')
}

function formatPaymentStatus(status: string | undefined | null): string {
  const labels: Record<string, string> = {
    paid: 'Zaplatené',
    open: 'Otvorená',
    pending: 'Čaká sa',
    failed: 'Zlyhala',
    void: 'Zrušená',
    uncollectible: 'Nevymožiteľná',
  }

  return labels[(status ?? '').toLowerCase()] || (status || '—')
}
</script>

