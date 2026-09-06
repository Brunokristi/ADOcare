<script setup lang="ts">
defineProps<{
    currentStep: 'company' | 'billing' | 'setup'
}>()

const steps: Array<{ key: 'company' | 'billing' | 'setup'; label: string }> = [
    { key: 'company', label: 'Spoločnosť' },
    { key: 'billing', label: 'Fakturácia' },
    { key: 'setup', label: 'Nastavenie' },
]
</script>

<template>
    <div class="min-h-full flex flex-col items-center px-4 py-10 gap-8">
        <div class="text-heading-accent">adocare</div>

        <div class="flex items-center gap-4">
            <template v-for="(step, index) in steps" :key="step.key">
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center text-normal"
                        :class="step.key === currentStep
                            ? 'bg-accent text-white'
                            : (steps.findIndex(s => s.key === currentStep) > index
                                ? 'bg-darkgrey text-white'
                                : 'bg-white text-darkgrey border border-darkgrey')"
                    >
                        <i v-if="steps.findIndex(s => s.key === currentStep) > index" class="bi bi-check-lg" />
                        <span v-else>{{ index + 1 }}</span>
                    </div>
                    <span class="text-normal" :class="step.key === currentStep ? 'text-accent' : 'text-lightgrey'">
                        {{ step.label }}
                    </span>
                </div>
                <div v-if="index < steps.length - 1" class="w-8 h-px bg-lightgrey" />
            </template>
        </div>

        <div class="w-full max-w-3xl">
            <slot />
        </div>
    </div>
</template>
