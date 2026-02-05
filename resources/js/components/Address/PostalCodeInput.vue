<script setup lang="ts">
const props = defineProps<{ modelValue: string | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string | null): void }>();

function onInput(e: Event) {
  const input = e.target as HTMLInputElement
  emit('update:modelValue', input.value || null)
}
</script>

<template>
  <InputText :value="props.modelValue || ''" @input="onInput" class="w-full" />
</template>
