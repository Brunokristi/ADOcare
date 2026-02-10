<script setup lang="ts">
import { computed, ref, nextTick } from 'vue'
import type { BrandColors } from '@/website/config/themes'

interface Props {
    label?: string
    type?: 'email' | 'text' | 'textarea' | 'select' | 'file'
    modelValue?: string | number | File | File[] | null
    error?: string
    placeholder?: string
    options?: Array<{ label: string; value: string | number }>
    brandColors?: BrandColors
    multiple?: boolean
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
    'update:modelValue': [value: string | number | File | File[] | null]
}>()

const isSelectOpen = ref(false)
const hoveredIndex = ref<number | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const textareaRef = ref<HTMLTextAreaElement | null>(null)

const scrollToBottom = () => {
    nextTick(() => {
        if (textareaRef.value) {
            textareaRef.value.style.height = 'auto'
            textareaRef.value.style.height = textareaRef.value.scrollHeight + 'px'
        }
    })
}

const handleFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement
    if (props.multiple) {
        const files = Array.from(input.files || [])
        emit('update:modelValue', files.length > 0 ? files : null)
    } else {
        const file = input.files?.[0] || null
        emit('update:modelValue', file)
    }
}

const hasValue = computed(() => {
    return props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined
})

const selectedLabel = computed(() => {
    if (!props.options) return ''
    const selected = props.options.find(opt => opt.value === props.modelValue)
    return selected ? selected.label : ''
})

const handleSelectOption = (value: string | number) => {
    emit('update:modelValue', value)
    isSelectOpen.value = false
}

const handleBlur = () => {
    isSelectOpen.value = false
}

const fileCount = computed(() => {
    if (Array.isArray(props.modelValue)) {
        return props.modelValue.length
    }
    return props.modelValue ? 1 : 0
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
                :class="[
                    'w-full bg-transparent py-1.5 px-0',
                    'border-b-1 border-white transition-colors duration-200',
                    'focus:outline-none text-white text-normal',
                ]"
            />

            <!-- Textarea -->
            <textarea
                v-else-if="type === 'textarea'"
                ref="textareaRef"
                :value="typeof modelValue === 'string' || typeof modelValue === 'number' ? modelValue : ''"
                @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value); scrollToBottom()"
                :class="[
                    'w-full bg-transparent py-1 px-0',
                    'border-b-1 border-white transition-colors duration-200',
                    'focus:outline-none resize-none text-white text-normal',
                ]"
                rows="1"
                style="overflow: hidden;"
            ></textarea>

            <!-- Select -->
            <div v-else-if="type === 'select'" class="relative">
                <button
                    type="button"
                    @click="isSelectOpen = !isSelectOpen"
                    @blur="handleBlur()"
                    :class="[
                        'w-full bg-transparent py-1 px-0 text-left',
                        'border-b-1 border-white transition-colors duration-200',
                        'focus:outline-none text-white text-normal',
                        'flex items-center justify-between'
                    ]"
                >
                    <span :class="{ 'opacity-60': !selectedLabel }">
                        {{ selectedLabel || label }}
                    </span>
                    <i 
                        class="bi bi-chevron-down transition-transform duration-200" 
                        :class="{ 'rotate-180': isSelectOpen }"
                    ></i>
                </button>

                <div 
                    v-if="isSelectOpen"
                    class="absolute top-full left-0 right-0 mt-1 rounded-lg shadow-lg z-50 max-h-48 overflow-y-auto"
                    :style="{ backgroundColor: brandColors.light }"
                >
                    <button
                        v-for="(option, index) in options"
                        :key="option.value"
                        type="button"
                        @click="handleSelectOption(option.value)"
                        @mouseenter="hoveredIndex = index"
                        @mouseleave="hoveredIndex = null"
                        :class="[
                            'w-full px-4 py-2 text-left text-normal transition-colors',
                        ]"
                        :style="{
                            backgroundColor: hoveredIndex === index ? brandColors.dark : 'transparent',
                            color: hoveredIndex === index ? brandColors.light : brandColors.dark,
                        }"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <!-- File Input -->
            <input
                v-else-if="type === 'file'"
                ref="fileInput"
                type="file"
                @change="handleFileChange"
                :multiple="multiple"
                class="hidden"
            />

            <button
                v-if="type === 'file'"
                type="button"
                @click="fileInput?.click()"
                :class="[
                    'w-full py-1 px-0 text-left',
                    'border-b-1 border-white transition-colors duration-200',
                    'focus:outline-none text-white text-normal',
                    'flex items-center justify-between',
                ]"
            >
                <label
                    :class="[
                        'transition-all duration-200 pointer-events-none text-white text-normal cursor-pointer',
                    ]"
                >
                    {{ label }}
                </label>
                
                <!-- Icons section -->
                <div class="flex items-center gap-1">
                    <template v-if="fileCount > 0">
                        <i 
                            v-for="n in fileCount" 
                            :key="n"
                            class="bi bi-file-earmark text-white"
                        ></i>
                    </template>
                    <i v-else class="bi bi-paperclip text-white"></i>
                </div>
            </button>

            
            <label
                v-if="label && type !== 'select' && type !== 'file'"
                :class="[
                    'absolute left-0 transition-all duration-200 pointer-events-none text-white text-normal',
                    hasValue ? '-top-4 text-xs' : 'top-2 text-normal',
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
