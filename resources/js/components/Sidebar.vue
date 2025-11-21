<script setup lang="ts">
import Menu from 'primevue/menu';
import { useRouter } from 'vue-router';
const router = useRouter();

const items = [
    // Davka bodovanie
    // Davka Kilometre
    // Cestovny prikaz
    // Pacienti
    // Nastavenia
    {
        label: 'Dávka bodovanie',
        icon: 'pi pi-fw pi-check-square',
        command: () => {
            router.push('/dose-scoring');
        },
    },
    {
        label: 'Dávka Kilometre',
        icon: 'pi pi-fw pi-map-marker',
        command: () => {
            router.push('/dose-kilometers');
        },
    },
    {
        label: 'Cestovny príkaz',
        icon: 'pi pi-fw pi-briefcase',
        command: () => {
            router.push('/travel-order');
        },
    },
    {
        label: 'Pacienti',
        icon: 'pi pi-fw pi-users',
        command: () => {
            router.push('/patients');
        },
    },
    {
        label: 'Nastavenia',
        icon: 'pi pi-fw pi-cog',
        command: () => {
            router.push('/settings');
        },
    },
];

</script>

<template>
    <Menu :model="items" class="w-full md:w-60">
        <template #start>
        </template>
        <template #item="{ item, props }">
            <a v-ripple class="flex items-center" v-bind="props.action">
                <span :class="item.icon" />
                <span>{{ item.label }}</span>
                <Badge v-if="item.badge" class="ml-auto" :value="item.badge" />
                <span v-if="item.shortcut"
                    class="ml-auto border border-surface rounded bg-emphasis text-muted-color text-xs p-1">{{
                        item.shortcut }}</span>
            </a>
        </template>
        <template #end>

        </template>
    </Menu>

</template>
