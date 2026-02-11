<script setup lang="ts">
import { ref } from 'vue'
import type { BrandColors } from '@/website/config/themes'

interface Props {
    label?: string
    text?: string
    color?: 'primary' | 'secondary' | 'warning' | 'success' | 'dark' | 'light'
    textColor?: 'primary' | 'secondary' | 'warning' | 'success' | 'dark' | 'light'
    brandColors?: BrandColors
    open?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    color: 'primary',
    textColor: 'dark',
    open: false,
    brandColors: () => ({
        primary: '#5C9EAD',
        light: '#DEECEF',
        dark: '#575252',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    }),
})

const isOpen = ref(props.open)

const getColorValue = (colorKey: string): string => {
    const colorMap: Record<string, string> = {
        primary: props.brandColors.primary,
        secondary: props.brandColors.secondary,
        warning: props.brandColors.warning,
        success: props.brandColors.success,
        dark: props.brandColors.dark,
        light: props.brandColors.light,
    }
    return colorMap[colorKey] || props.brandColors.primary
}

const buttonColor = getColorValue(props.color)
</script>

<template>
    <div class="w-full">
        <button
            @click="isOpen = !isOpen"
            :class="[
                'w-full px-2 py-2 rounded-lg transition-all duration-100',
                'flex items-center justify-between',
                'hover:opacity-80',
                'cursor-pointer',
                'text-normal, text-white',
            ]"
            :style="{
                backgroundColor: buttonColor,
            }"
        >
            <span class="text-normal" :style="{ color: getColorValue(props.textColor) }">{{ label }}</span>
            <i
                :class="[
                    'bi text-normal',
                    isOpen ? 'bi-arrow-up' : 'bi-arrow-down'
                ]"
                :style="{ color: getColorValue(props.textColor) }"
                
            ></i>
        </button>

        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 max-h-0"
            enter-to-class="opacity-100 max-h-screen"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 max-h-screen"
            leave-to-class="opacity-0 max-h-0"
        >
            <div
                v-if="isOpen"
                :class="[
                    'mt-0.5 px-2 py-2 rounded-lg overflow-hidden',
                    'text-lightgrey text-normal text-left',
                ]"
                :style="{
                    backgroundColor: buttonColor,
                }"
            >
                <p class="text-normal" :style="{ color: brandColors.secondary }">{{ text }}</p>
            </div>
        </Transition>
    </div>
</template>
