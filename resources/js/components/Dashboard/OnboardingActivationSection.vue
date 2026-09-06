<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import TrialBillingCard from './TrialBillingCard.vue'
import SetupCard from './SetupCard.vue'

interface OnboardingStep {
    slug: string
    label: string
    complete: boolean
}

interface TrialState {
    active: boolean
    expired: boolean
    days_remaining: number | null
    ends_at: string | null
    credits: number | null
    credits_remaining: number | null
}

const loading = ref(true)
const trial = ref<TrialState | null>(null)
const subscriptions = ref<any[]>([])
const steps = ref<OnboardingStep[]>([])
const setupComplete = ref(false)
const billingProvisioned = ref(false)

// Once setup is done and there's no trial/billing state that still needs attention, the
// section disappears and the dashboard is just the normal dashboard again.
const visible = computed(() =>
    trial.value?.active || trial.value?.expired || !setupComplete.value
)

async function loadState() {
    try {
        const [billingRes, onboardingRes] = await Promise.all([
            api.get('v1/billing/subscription'),
            api.get('v1/onboarding/status'),
        ])

        trial.value = billingRes.data?.data?.trial ?? null
        subscriptions.value = billingRes.data?.data?.subscriptions ?? []
        billingProvisioned.value = Boolean(billingRes.data?.data?.billing_provisioned)
        steps.value = onboardingRes.data?.data?.steps ?? []
        setupComplete.value = Boolean(onboardingRes.data?.data?.complete)
    } catch {
        // Non-fatal - the dashboard itself still works without this section.
    } finally {
        loading.value = false
    }
}

onMounted(loadState)
</script>

<template>
    <div v-if="!loading && visible" class="grid grid-cols-12 gap-4 mb-4">
        <div class="col-span-12 md:col-span-6">
            <TrialBillingCard
                :trial="trial"
                :subscriptions="subscriptions"
                :billing-provisioned="billingProvisioned"
                @retried="loadState"
            />
        </div>
        <div class="col-span-12 md:col-span-6">
            <SetupCard :steps="steps" :complete="setupComplete" />
        </div>
    </div>
</template>

