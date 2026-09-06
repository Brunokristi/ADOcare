<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import { useRouter } from 'vue-router'
import api from '@/services/api'

interface TrialState {
    active: boolean
    expired: boolean
    days_remaining: number | null
    ends_at: string | null
    credits: number | null
    credits_remaining: number | null
}

interface Subscription {
    status: string
    plan?: { name: string } | null
}

const props = defineProps<{
    trial: TrialState | null
    subscriptions: Subscription[]
    billingProvisioned: boolean
}>()

const emit = defineEmits<{ retried: [] }>()

const router = useRouter()
const retrying = ref(false)

const activeSubscription = computed(() =>
    props.subscriptions.find((s) => ['active', 'trialing'].includes(s.status)) ?? null
)

// Neither a paid subscription nor a trial exists yet - billing/trial setup did not
// complete during onboarding (e.g. a temporary StudioKristian outage) and needs a retry.
const needsSetup = computed(() =>
    !activeSubscription.value && !props.trial?.active && !props.trial?.expired
)

function goToBilling() {
    router.push({ name: 'billing' })
}

async function retrySetup() {
    retrying.value = true

    try {
        if (!props.billingProvisioned) {
            await api.post('v1/onboarding/billing/provision')
        }
        await api.post('v1/onboarding/billing/start-trial')
    } catch {
        // Non-fatal - the card stays in the "needs setup" state and can be retried again.
    } finally {
        retrying.value = false
        emit('retried')
    }
}

function formatDate(value: string | null): string {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return '—'
    return date.toLocaleDateString('sk-SK', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
    <!-- Paid subscription: billing card, not a trial card. -->
    <div v-if="activeSubscription" class="bg-tag3 rounded-md p-6 flex items-center justify-between gap-4">
        <div>
            <div class="text-mini uppercase tracking-wide text-lightgrey mb-1">Predplatné</div>
            <div class="text-heading text-white">{{ activeSubscription.plan?.name ?? 'Aktívny balík' }}</div>
        </div>
        <Button label="Spravovať fakturáciu" class="bg-accent! border-0!" @click="goToBilling" />
    </div>

    <!-- Active trial -->
    <div v-else-if="trial?.active" class="bg-darkgrey rounded-md p-6 flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <div class="text-mini uppercase tracking-wide text-lightgrey">Skúšobné obdobie</div>
            <Button label="Zobraziť fakturáciu" class="bg-accent! border-0!" @click="goToBilling" />
        </div>

        <div class="flex gap-10">
            <div>
                <div class="text-heading-accent text-white">{{ trial.days_remaining ?? '—' }}</div>
                <div class="text-normal text-lightgrey">dní zostáva</div>
            </div>
            <div v-if="trial.credits_remaining !== null">
                <div class="text-heading-accent text-white">{{ trial.credits_remaining }}</div>
                <div class="text-normal text-lightgrey">kreditov zostáva</div>
            </div>
        </div>

        <div class="text-mini text-lightgrey">Platné do {{ formatDate(trial.ends_at) }}</div>
    </div>

    <!-- Expired trial -->
    <div v-else-if="trial?.expired" class="bg-danger rounded-md p-6 flex items-center justify-between gap-4">
        <div>
            <div class="text-mini uppercase tracking-wide text-white mb-1">Skúšobné obdobie skončilo</div>
            <div class="text-normal text-white">Vyberte si platený balík a pokračujte v používaní ADOcare.</div>
        </div>
        <Button label="Vybrať balík" class="bg-white! border-0! text-danger!" @click="goToBilling" />
    </div>

    <!-- Billing/trial setup did not complete yet (e.g. StudioKristian was briefly unavailable). -->
    <div v-else-if="needsSetup" class="bg-tag3 rounded-md p-6 flex items-center justify-between gap-4">
        <div>
            <div class="text-mini uppercase tracking-wide text-lightgrey mb-1">Fakturácia sa ešte nenastavila</div>
            <div class="text-normal text-lightgrey">Skúste to prosím znova.</div>
        </div>
        <Button label="Skúsiť znova" :loading="retrying" class="bg-accent! border-0!" @click="retrySetup" />
    </div>
</template>

