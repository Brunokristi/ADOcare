<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import { useRouter } from 'vue-router'
import OnboardingLayout from './OnboardingLayout.vue'
import api from '@/services/api'

interface OnboardingStep {
    slug: string
    label: string
    complete: boolean
}

const router = useRouter()
const loading = ref(true)
const finishing = ref(false)
const error = ref<string | null>(null)
const steps = ref<OnboardingStep[]>([])

onMounted(loadStatus)

async function loadStatus() {
    loading.value = true
    try {
        const res = await api.get('v1/onboarding/status')
        steps.value = res.data?.data?.steps ?? []
    } finally {
        loading.value = false
    }
}

async function finishSetup() {
    finishing.value = true
    error.value = null

    try {
        await api.post('v1/onboarding/complete')
        router.push({ name: 'manager-dashboard' })
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Nastavenie ešte nie je kompletné.'
        await loadStatus()
    } finally {
        finishing.value = false
    }
}

function goToStep(slug: string) {
    if (slug === 'company') router.push({ name: 'onboarding-company' })
    else if (slug === 'billing') router.push({ name: 'onboarding-billing' })
    else router.push({ name: 'manager-settings-branches' })
}
</script>

<template>
    <OnboardingLayout current-step="setup">
        <div class="flex flex-col gap-5">
            <p class="text-normal text-lightgrey">Skontrolujte, či máte adocare pripravené na používanie.</p>

            <div v-if="error" class="rounded-md bg-danger p-4 text-normal text-white">{{ error }}</div>

            <div v-if="loading" class="text-normal text-lightgrey">Načítavam...</div>

            <div v-else class="flex flex-col gap-2">
                <button
                    v-for="step in steps"
                    :key="step.slug"
                    type="button"
                    class="flex items-center justify-between rounded-md bg-tag3 p-4 text-left"
                    @click="goToStep(step.slug)"
                >
                    <span class="text-normal text-white">{{ step.label }}</span>
                    <i :class="step.complete ? 'bi bi-check-circle-fill text-accent' : 'bi bi-circle text-lightgrey'" />
                </button>
            </div>

            <div class="flex justify-end">
                <Button label="Dokončiť nastavenie" :loading="finishing" class="bg-accent! border-0!" @click="finishSetup" />
            </div>
        </div>
    </OnboardingLayout>
</template>
