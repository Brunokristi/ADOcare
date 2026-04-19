<script setup lang="ts">
import Button from 'primevue/button'

defineEmits<{
    (e: 'next'): void
}>()
</script>

<template>
    <section class="bg-tag3 rounded-md p-5">
        <div class="">
            <h2 class="text-heading mb-3">Nasledujú faktúry</h2>

            <div class="flex flex-col gap-3 text-sm text-tag2 list-decimal p-5">
                <p>
                    V nasledujúcom kroku je potrebné vytvoriť faktúry za vykonané výkony a dopravu.
                </p>

                <p class="flex flex-wrap items-center gap-2">
                    Faktúry môžete vytvoriť manuálne pomocou tlačidla

                    <span class="inline-flex items-center justify-center h-6 w-10 rounded-md bg-accent text-white text-xs">
                        <i class="bi bi-plus-lg"></i>
                    </span>

                    alebo použiť

                    <span class="inline-flex items-center gap-2 px-2 py-1 rounded-md bg-darkgrey text-white text-xs">
                        <img src="/adonis.svg" alt="Adonis" class="w-4 h-4" />
                        Hej, Adonis!
                    </span>

                    ktorý vytvorí všetky faktúry za vás.
                </p>

                <p>
                    Faktúry dostanú automaticky pridelené číslo faktúry.
                </p>

                <p class="flex flex-wrap items-center gap-2">
                    Po vytvorení faktúr je potrebné ich stiahnuť do počítača.

                    Faktúry si stiahnite do počítača (otvorte náhľad faktúry 

                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-md text-darkgrey text-xs">
                        <i class="bi bi-eye"></i>
                    </span>

                    a stiahnite ju ako PDF).
                </p>
            </div>

            <div class="mt-5 flex justify-end">
                <Button
                    type="button"
                    label="Prejsť na faktúry"
                    class="bg-accent! border-0! hover:bg-darkgrey! text-white! text-normal! px-5!"
                    @click="$emit('next')"
                />
            </div>
        </div>
    </section>
</template>