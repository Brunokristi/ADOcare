<script setup lang="ts">
import { defineEmits, defineProps } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: '' },
    small: { type: Boolean, default: false },
    type: { type: String, default: 'text' },
});

const emit = defineEmits(['update:modelValue']);

function onInput(e: Event) {
    const v = (e.target as HTMLInputElement).value;
    emit('update:modelValue', v);
}
</script>

<template>
    <input :class="['rounded px-3 py-1 border bg-white text-black', small ? 'text-sm' : 'text-base']"
        :placeholder="placeholder" :type="type" :value="modelValue" @input="onInput" />
</template>

<style scoped></style>
