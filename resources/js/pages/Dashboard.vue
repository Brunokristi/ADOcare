<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { computed } from 'vue';
import type { User } from '@/types/models';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

const user = computed<User | null>(() => authStore.user as User | null);

const fullName = computed(() =>
  user.value
    ? `${user.value.first_name ?? ''}`.trim()
    : ''
);
</script>

<template>
    <div class="space-y-10 p-4">

        <!-- Greeting -->
        <div class="text-heading-accent">
            Dobrý deň {{ fullName }} 😊,<br />
            všetko je pre Vás pripravené.
        </div>

        <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
            <label class="block text-normal text-accent">Rýchle skratky</label>

            <div class="flex gap-4">
            <RouterLink to="/patients" class="block w-70">
                <Button
                    class="w-full h-12 bg-darkgrey! border-0! text-white rounded-md flex justify-between! items-center px-4! hover:bg-accent!"
                >
                    <span>Pacienti</span>
                    <i class="bi bi-arrow-right text-lg"></i>
                </Button>
            </RouterLink>
            </div>
        </section>

        <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
            <label class="block text-normal text-accent">Mesačné úkony</label>

            <div class="flex gap-4">
            <RouterLink to="/data/points" class="block w-70">
                <Button
                    class="w-full h-12 bg-darkgrey! border-0! text-white rounded-md flex justify-between! items-center px-4! hover:bg-accent!"
                >
                    <span>Dávka bodovanie</span>
                    <i class="bi bi-arrow-right text-lg"></i>
                </Button>
            </RouterLink>

            <RouterLink to="/data/kilometers" class="block w-70">
                <Button
                    class="w-full h-12 bg-darkgrey! border-0! text-white rounded-md flex justify-between! items-center px-4! hover:bg-accent!"
                >
                    <span>Dávka kilometre</span>
                    <i class="bi bi-arrow-right text-lg"></i>
                </Button>
            </RouterLink>

            <RouterLink to="/accounting/routes" class="block w-70">
                <Button
                    class="w-full h-12 bg-darkgrey! border-0! text-white rounded-md flex justify-between! items-center px-4! hover:bg-accent!"
                >
                    <span>Cestovný príkaz</span>
                    <i class="bi bi-arrow-right text-lg"></i>
                </Button>
            </RouterLink>

            </div>
        </section>
    </div>
</template>
