<script setup lang="ts">
import Button from 'primevue/button'
import InvoicesManager from '@/pages/Manager/Invoices.vue'

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

function handleDone() {
    emit('update-state', { invoicesDone: true })
    emit('next')
}
</script>

<template>
    <section class="bg-tag3 rounded-md p-5">
        <div class="mb-4">
            <h2 class="text-heading">Faktúry</h2>
        </div>

        <div>
            <InvoicesManager />
        </div>

        <div class="mt-4 flex justify-end">
            <Button
                type="button"
                label="Faktúry mám hotové"
                class="bg-accent! border-0! hover:bg-darkgrey! text-white! text-normal! px-5!"
                @click="handleDone"
            />
        </div>
    </section>
</template>