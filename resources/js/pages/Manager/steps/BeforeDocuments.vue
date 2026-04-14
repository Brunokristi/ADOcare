<script setup lang="ts">
import Button from 'primevue/button'

defineEmits<{
    (e: 'next'): void
}>()
</script>

<template>
    <section class="bg-tag3 rounded-md p-5">
        <div class="">
            <h2 class="text-heading mb-3">Dopravné dokumenty manažéra</h2>

            <div class="flex flex-col gap-3 text-sm text-tag2 list-decimal p-5">
                <p>
                    V nasledujúcom kroku je potrebné vytvoriť denný záznam ciest a cestovný príkaz manažéra.
                </p>

                <p class="flex flex-wrap items-center gap-2">
                    Dokumenty vytvoríte pomocou tlačidla

                    <span class="inline-flex items-center justify-center h-6 w-10 rounded-md bg-accent text-white text-xs">
                        <i class="bi bi-plus-lg"></i>
                    </span>

                    a zvolíte si typ dokumentu.
                </p>

                <p>
                    Je potrebné vytvoriť oba dokumenty (denný záznam ciest aj cestovný príkaz).
                </p>

            </div>

            <div class="mt-5 flex justify-end">
                <Button
                    type="button"
                    label="Vytvoriť dokumenty manažéra"
                    class="bg-accent! border-0! hover:bg-darkgrey! text-white! text-normal! px-5!"
                    @click="$emit('next')"
                />
            </div>
        </div>
    </section>
</template>