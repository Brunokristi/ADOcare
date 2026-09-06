<script setup lang="ts">
import { computed } from 'vue'
import Button from 'primevue/button'
import { useRouter } from 'vue-router'

interface OnboardingStep {
    slug: string
    label: string
    complete: boolean
}

const props = defineProps<{
    steps: OnboardingStep[]
    complete: boolean
}>()

const router = useRouter()

const completedCount = computed(() => props.steps.filter((s) => s.complete).length)
const totalCount = computed(() => props.steps.length)
const percentage = computed(() => (totalCount.value === 0 ? 0 : Math.round((completedCount.value / totalCount.value) * 100)))
const remainingSteps = computed(() => props.steps.filter((s) => !s.complete))

const ctaLabel = computed(() => {
    if (props.complete) return 'Nastavenie dokončené'
    if (completedCount.value === 0) return 'Začať nastavenie'
    return 'Pokračovať v nastavení'
})

function goToSetup() {
    router.push({ name: 'onboarding-setup' })
}
</script>

<template>
    <div class="bg-tag3 rounded-md p-6 flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <div class="text-mini uppercase tracking-wide text-lightgrey">Nastavenie ADOcare</div>
            <span class="text-normal text-white">{{ percentage }} %</span>
        </div>

        <div class="w-full h-2 bg-darkgrey rounded-full overflow-hidden">
            <div class="h-full bg-accent" :style="{ width: percentage + '%' }" />
        </div>

        <ul v-if="remainingSteps.length" class="flex flex-col gap-1">
            <li v-for="step in remainingSteps" :key="step.slug" class="text-normal text-lightgrey flex items-center gap-2">
                <i class="bi bi-circle" />
                {{ step.label }}
            </li>
        </ul>

        <div class="flex justify-end">
            <Button :label="ctaLabel" :disabled="complete" class="bg-accent! border-0!" @click="goToSetup" />
        </div>
    </div>
</template>
