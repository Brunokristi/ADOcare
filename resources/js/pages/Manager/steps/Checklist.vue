<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'

type WizardState = {
    checklistDone: boolean
    invoicesDone: boolean
    documentsDone: boolean
}

defineProps<{
    wizardState: WizardState
}>()

const emit = defineEmits<{
    (e: 'update-state', payload: Partial<WizardState>): void
    (e: 'next'): void
}>()

const checks = ref({
    procedures: false,
    routes: false,
    documents: false,
})

const allChecked = computed(() =>
    checks.value.procedures &&
    checks.value.routes &&
    checks.value.documents &&
    true)

watch(allChecked, (value) => {
    emit('update-state', { checklistDone: value })
})
</script>

<template>
    <section class="bg-tag3 rounded-md p-5">
        <div class="mb-4">
            <h2 class="text-heading">Skontrolujme si potrebné podklady na vykonanie uzávierky</h2>
        </div>

        <div class="rounded-md p-4 flex flex-col gap-4">
            <label class="flex items-center gap-3">
                <Checkbox v-model="checks.procedures" binary />
                <span class="text-normal">Všetky sestričky majú vygenerované výkonové dávky</span>
            </label>

            <label class="flex items-center gap-3">
                <Checkbox v-model="checks.routes" binary />
                <span class="text-normal">Všetky sestričky majú vygenerované dopravné dávky</span>
            </label>

            <label class="flex items-center gap-3">
                <Checkbox v-model="checks.documents" binary />
                <span class="text-normal">Dokumenty mám stiahnuté do počítača</span>
            </label>
        </div>

        <div class="mt-4 flex justify-end">
            <Button
                type="button"
                label="Môžeme začať"
                :disabled="!allChecked"
                class="bg-accent! border-0! hover:bg-darkgrey! text-white! text-normal! px-5!"
                @click="emit('next')"
            />
        </div>
    </section>
</template>