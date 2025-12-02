<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <label class="block text-normal text-accent">Rýchle skratky</label>

        <div class="flex gap-4">
        <RouterLink to="/patients" class="block w-70">
            <Button
                class="w-full h-12 !bg-darkgrey !border-0 text-white rounded-md flex !justify-between items-center !px-4 hover:!bg-accent"
            >
                <span>Pacienti</span>
                <i class="bi bi-arrow-right text-lg"></i>
            </Button>
        </RouterLink>
        </div>
    </section>

    <Button
        type="submit"
        class="relative w-100 flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey"
    >
        Prih
        <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
    </Button>
</template>
