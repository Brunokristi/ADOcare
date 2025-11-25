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
    <Menu :model="items" class="!bg-tag3 !p-lg !h-full !flex !flex-col !gap-2">
        <template #item="{ item, props }">
            <a
                v-ripple
                v-bind="props.action"
                class="
                    block w-full overflow-hidden
                    !py-1
                    !rounded-md
                    !text-darkgrey
                    hover:!bg-almostwhite
                    hover:!text-accent
                    focus:!bg-almostwhite
                    focus:!text-accent
                    focus:!rounded-md
                    !mb-2"
                    
            >
                <span>{{ item.label }}</span>
            </a>
        </template>
    </Menu>
</template>

