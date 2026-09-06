<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import { useRouter } from 'vue-router'
import OnboardingLayout from './OnboardingLayout.vue'
import api from '@/services/api'

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

const router = useRouter()

const loading = ref(true)
const error = ref<string | null>(null)
const plans = ref<Plan[]>([])
const trialActive = ref(false)
const billingProvisioned = ref(false)
const selectedPriceId = ref<number | null>(null)
const startingTrial = ref(false)

const canContinue = computed(() => trialActive.value)

onMounted(async () => {
    await ensureBillingProvisioned()
    await loadPlans()
    await loadStatus()
})

async function ensureBillingProvisioned() {
    try {
        await api.post('v1/onboarding/billing/provision')
        billingProvisioned.value = true
    } catch {
        // Not fatal - the customer can retry, and the trial can still start once provisioned.
        billingProvisioned.value = false
    }
}

async function loadPlans() {
    loading.value = true
    try {
        const res = await api.get('v1/billing/plans')
        plans.value = res.data?.data ?? []
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Nepodarilo sa načítať dostupné balíky.'
    } finally {
        loading.value = false
    }
}

async function loadStatus() {
    try {
        const res = await api.get('v1/onboarding/status')
        trialActive.value = Boolean(res.data?.data?.trial?.active)
        billingProvisioned.value = Boolean(res.data?.data?.billing_provisioned)
    } catch {
        // Keep whatever we already know.
    }
}

async function retryProvisioning() {
    error.value = null
    await ensureBillingProvisioned()
}

async function startTrial() {
    startingTrial.value = true
    error.value = null

    try {
        await api.post('v1/onboarding/billing/start-trial', {
            plan_price_id: selectedPriceId.value ?? undefined,
        })
        trialActive.value = true
        router.push({ name: 'onboarding-setup' })
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Nepodarilo sa spustiť skúšobné obdobie.'
    } finally {
        startingTrial.value = false
    }
}

function continueToSetup() {
    router.push({ name: 'onboarding-setup' })
}

function formatPrice(price: PlanPrice): string {
    const intervalLabels: Record<string, string> = { month: 'mesiac', monthly: 'mesiac', year: 'rok', yearly: 'rok' }
    // StudioKristian/Stripe amounts are always in the smallest currency unit (cents for EUR).
    return `${(Number(price.amount) / 100).toFixed(2)} ${price.currency} / ${intervalLabels[price.interval] ?? price.interval}`
}
</script>

<template>
    <OnboardingLayout current-step="billing">
        <div class="flex flex-col gap-5">
            <p class="text-normal text-lightgrey">
                Vyberte si balík a spustite bezplatné skúšobné obdobie - platobné údaje nie sú potrebné.
            </p>

            <div v-if="!billingProvisioned" class="rounded-md bg-danger p-4 text-normal text-white flex items-center justify-between gap-3">
                <span>Fakturačné údaje sa nepodarilo automaticky nastaviť.</span>
                <Button label="Skúsiť znova" class="bg-white! border-0! text-danger!" @click="retryProvisioning" />
            </div>

            <div v-if="error" class="rounded-md bg-danger p-4 text-normal text-white">{{ error }}</div>

            <div v-if="trialActive" class="rounded-md bg-darkgrey p-4 text-normal text-white">
                Skúšobné obdobie je už aktívne.
            </div>

            <div v-if="loading" class="text-normal text-lightgrey">Načítavam balíky...</div>

            <div v-else class="grid grid-cols-12 gap-4">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="col-span-12 md:col-span-6 rounded-md bg-tag3 p-4 flex flex-col gap-3"
                >
                    <div class="text-heading text-white">{{ plan.name }}</div>
                    <div v-if="plan.description" class="text-normal text-lightgrey">{{ plan.description }}</div>

                    <div class="flex flex-col gap-2">
                        <label
                            v-for="price in plan.prices"
                            :key="price.id"
                            class="flex items-center gap-2 text-normal text-white cursor-pointer"
                        >
                            <input type="radio" :value="price.id" v-model="selectedPriceId" name="plan_price" />
                            {{ formatPrice(price) }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <Button
                    v-if="!trialActive"
                    label="Pokračovať bez výberu"
                    class="bg-transparent! border-0! text-lightgrey!"
                    @click="continueToSetup"
                />
                <Button
                    v-if="!trialActive"
                    label="Spustiť skúšobné obdobie"
                    :loading="startingTrial"
                    class="bg-accent! border-0!"
                    @click="startTrial"
                />
                <Button v-else label="Pokračovať" class="bg-accent! border-0!" @click="continueToSetup" :disabled="!canContinue" />
            </div>
        </div>
    </OnboardingLayout>
</template>
