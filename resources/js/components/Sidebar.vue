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
    <Menu :model="items" class="w-full md:w-60 !bg-tag3 !p-lg" >
        <template #start>
        </template>
        <template #item="{ item, props }">
            <a v-ripple class="flex items-center" v-bind="props.action">
                <span>{{ item.label }}</span>
            </a>
        </template>
        <template #end>

        </template>
    </Menu>

</template>
