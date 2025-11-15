<script setup lang="ts">
import { defineProps } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    small: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <button :class="[
        'inline-flex items-center justify-center rounded px-3 py-1.5 font-medium cursor-pointer',
        small ? 'text-sm' : 'text-base',
        variant === 'primary' ? 'bg-blue-600 text-white hover:bg-blue-700' : '',
        variant === 'ghost' ? 'bg-transparent text-white hover:bg-white/10' : ''
    ]" :disabled="disabled">
        <slot />
    </button>
</template>

<style scoped></style>
