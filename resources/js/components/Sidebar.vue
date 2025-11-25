<script setup lang="ts">
import Menu from 'primevue/menu';
import { useRouter } from 'vue-router';
const router = useRouter();

const items = [
    {
        label: 'Dávka bodovanie',
        command: () => {
            router.push('/dose-scoring');
        },
    },
    {
        label: 'Dávka Kilometre',
        command: () => {
            router.push('/dose-kilometers');
        },
    },
    {
        label: 'Cestovny príkaz',
        command: () => {
            router.push('/travel-order');
        },
    },
    {
        label: 'Pacienti',
        command: () => {
            router.push('/patients');
        },
    },
    {
        label: 'Nastavenia',
        command: () => {
            router.push('/settings');
        },
    },
];

</script>

<template>
    <Menu :model="items" class="!bg-tag3 !p-lg">
        <template #item="{ item, props }">
            <a
                v-ripple
                v-bind="props.action"
                class="
                    flex items-center
                    !px-3 py-2
                    !rounded-md

                    bg-tag3 text-white
                    hover:bg-accent hover:text-white
                    focus:bg-accent focus:text-white
                    transition-colors
                "
            >
                <span>{{ item.label }}</span>
            </a>
        </template>
    </Menu>
</template>

