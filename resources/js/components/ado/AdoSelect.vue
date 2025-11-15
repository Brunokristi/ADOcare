<script setup lang="ts">
import { defineProps, defineEmits } from 'vue';

const {
    modelValue,
    options,
    size = 'md',
    placeholder = '',
    disabled = false,
} = defineProps<{
    modelValue: string | number | null;
    options: Array<string | { value: string | number; label: string }>;
    size?: 'sm' | 'md' | 'lg';
    placeholder?: string;
    disabled?: boolean;
}>();

const emit = defineEmits(['update:modelValue', 'change']);

function onChange(e: Event) {
    const v = (e.target as HTMLSelectElement).value;
    emit('update:modelValue', v);
    emit('change', v);
}
</script>

<template>
    <select :value="modelValue" @change="onChange" :disabled="disabled" :class="['rounded px-2 py-0.5 text-xs',
        size === 'sm' ? 'text-xs' : size === 'lg' ? 'text-base' : 'text-sm',
        disabled ? 'opacity-50 cursor-not-allowed' : '']">
        <option v-if="placeholder" value="">{{ placeholder }}</option>
        <template v-for="opt in options">
            <option v-if="typeof opt === 'string'" :key="opt" :value="opt">
                {{ opt }}
            </option>
            <option v-else :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </template>
    </select>
</template>

<style scoped>
/* lightweight defaults; letting tailwind classes handle most styling */
</style>
