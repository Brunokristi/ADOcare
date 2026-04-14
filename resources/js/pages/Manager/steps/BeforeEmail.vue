<script setup lang="ts">
import Button from 'primevue/button'

defineEmits<{
    (e: 'next'): void
}>()
</script>

<template>
    <section class="bg-tag3 rounded-md p-5">
        <div class="">
            <h2 class="text-heading mb-3">Odoslanie dokumentov účtovníkovi</h2>

            <div class="flex flex-col gap-3 text-sm text-tag2 list-decimal p-5">
                <p>
                    Po vytvorení dokumentov je potrebné ich odoslať účtovníkovi.
                </p>

                <p class="flex flex-wrap items-center gap-2">
                    Označte všetky dokumenty, ktoré si prajete odoslať tlačidlom

                    <span class="inline-flex items-center justify-center h-6 text-darkgrey text-xs">
                        <i class="bi bi-square"></i>
                    </span>

                    v tabuľke. 
                </p>

                <p>
                    Následne kliknite na tlačidlo <span class="inline-flex items-center justify-center h-6 w-10 rounded-md bg-accent text-white text-xs">
                        <i class="bi bi-send"></i>
                    </span>
                    a zadajte emailovú adresu účtovníka, ktorému chcete dokumenty odoslať.
                </p>

            </div>

            <div class="mt-5 flex justify-end">
                <Button
                    type="button"
                    label="Odoslať dokumenty účtovníkovi"
                    class="bg-accent! border-0! hover:bg-darkgrey! text-white! text-normal! px-5!"
                    @click="$emit('next')"
                />
            </div>
        </div>
    </section>
</template>