<script setup lang="ts">
import { computed } from 'vue'
import type { BrandColors } from '@/website/config/themes'

interface Props {
    label?: string
    type?: 'email' | 'text' | 'textarea' | 'select' | 'file'
    modelValue?: string | number | File | null
    error?: string
    placeholder?: string
    options?: Array<{ label: string; value: string | number }>
    brandColors?: BrandColors
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    modelValue: '',
    brandColors: () => ({
        primary: '#5C9EAD',
        light: '#DEECEF',
        dark: '#575252',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    }),
})

const emit = defineEmits<{
    'update:modelValue': [value: string | number | File | null]
}>()

const handleFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] || null
    emit('update:modelValue', file)
}

const hasValue = computed(() => {
    return props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined
})

</script>

<template>
    <div class="w-full mb-6">
        <div class="relative">
            <input
                v-if="type === 'text' || type === 'email'"
                :type="type"
                :value="typeof modelValue === 'string' || typeof modelValue === 'number' ? modelValue : ''"
                @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
                :placeholder="placeholder || ''"
                :class="[
                    'w-full bg-transparent py-1 px-0',
                    'border-b-1 border-white transition-colors duration-200',
                    'focus:outline-none text-white text-normal',
                ]"
            />

            <!-- Textarea -->
            <textarea
                v-else-if="type === 'textarea'"
                :value="typeof modelValue === 'string' || typeof modelValue === 'number' ? modelValue : ''"
                @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
                :placeholder="placeholder || ''"
                :class="[
                    'w-full bg-transparent py-1 px-0',
                    'border-b-1 border-white transition-colors duration-200',
                    'focus:outline-none resize-none text-white text-normal',
                ]"
                rows="3"
            ></textarea>

            <!-- Select -->
            <select
                v-else-if="type === 'select'"
                :value="typeof modelValue === 'string' || typeof modelValue === 'number' ? modelValue : ''"
                @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
                :class="[
                    'w-full bg-transparent py-1 px-0',
                    'border-b-1 border-white transition-colors duration-200',
                    'focus:outline-none text-white text-normal',
                ]"
            >
                <option value="" class="px-0">{{ label }}</option>
                <option
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                    class="px-0"
                >
                    {{ option.label }}
                </option>
            </select>

            <!-- File Input -->
            <input
                v-else-if="type === 'file'"
                type="file"
                @change="handleFileChange($event)"
                :class="[
                    'w-full py-1 px-0',
                    'focus:outline-none text-white text-normal cursor-pointer border-b-1 border-white transition-colors duration-200',
                ]"
                :style="{
                    color: props.brandColors.dark,
                }"
            />

            <label
                v-if="label && (type !== 'select' && type !== 'file')"
                :class="[
                    'absolute left-0 transition-all duration-200 pointer-events-none text-white text-normal',
                    hasValue ? '-top-4 text-xs' : 'top-2 text-base'
                ]"
            >
                {{ label }}
            </label>
        </div>

        <!-- Error Message -->
        <p
            v-if="error"
            class="mt-1 text-mini text-warning"
        >
            {{ error }}
        </p>
    </div>
</template>
