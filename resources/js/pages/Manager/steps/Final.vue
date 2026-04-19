<script setup lang="ts">
type WizardState = {
    checklistDone: boolean
    invoicesDone: boolean
    documentsDone: boolean
}

defineProps<{
    wizardState: WizardState
}>()
</script>

<template>
    <section class="bg-tag3 rounded-md p-5">
        <div class="rounded-md p-5">
            <h2 class="text-heading mb-4">Hotovo!</h2>

            <div class="mt-5 text-normal text-tag2">
                Proces je dokončený. Teraz môžete pokračovať v bežnej práci v aplikácii.
            </div>
        </div>
    </section>
</template>