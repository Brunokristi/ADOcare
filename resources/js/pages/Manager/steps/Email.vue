<script setup lang="ts">
import Button from 'primevue/button'
import DocumentsManager from '@/pages/Manager/TravelDocuments.vue'

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
    emit('update-state', { documentsDone: true })
    emit('next')
}
</script>

<template>
    <section class="bg-tag3 rounded-md p-5">
        <div class="mb-4">
            <h2 class="text-heading">Dokumenty manažéra</h2>
        </div>

        <div class="rounded-md bg-white p-4">
            <DocumentsManager />
        </div>

        <div class="mt-4 flex justify-end">
            <Button type="button" label="Dokumenty odoslané účtovníkovi"
                class="bg-accent! border-0! hover:bg-darkgrey! text-white! text-normal! px-5!" @click="handleDone" />
        </div>
    </section>
</template>
