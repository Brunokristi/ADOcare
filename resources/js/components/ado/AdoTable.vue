<script setup lang="ts">
import { defineProps } from 'vue';

const props = defineProps({
    text: { type: String, required: true },
    color: { type: String, default: 'gray' },
});
</script>

<template>
    <span :class="['inline-block text-xs px-2 py-0.5 rounded', tagClass]">{{ text }}</span>
</template>

<script lang="ts">
export default {
    computed: {
        tagClass(): string {
            switch ((this as any).color) {
                case 'accent':
                    return 'bg-blue-600 text-white';
                case 'gray':
                default:
                    return 'bg-gray-200 text-gray-800';
            }
        },
    },
};
</script>

<style scoped></style>
