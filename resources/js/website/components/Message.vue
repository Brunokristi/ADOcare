<script setup lang="ts">
import type { BrandColors } from '@/website/config/themes'

interface Props {
    label?: string
    text?: string
    type?: 'warning' | 'success'
    brandColors?: BrandColors
}

const props = withDefaults(defineProps<Props>(), {
    type: 'success',
    brandColors: () => ({
        primary: '#5C9EAD',
        light: '#DEECEF',
        dark: '#575252',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    }),
})

const getColorValue = (): string => {
    if (props.type === 'warning') {
        return props.brandColors.warning
    }
    return props.brandColors.success
}

const messageColor = getColorValue()
</script>

<template>
    <div class="w-full">
        <div
            :class="[
                'w-full px-2 py-2 rounded-lg',
                'flex items-center justify-between',
                'text-normal text-white',
            ]"
            :style="{
                backgroundColor: messageColor,
            }"
        >
            <span>{{ label }}</span>
            <i
                :class="[
                    'bi',
                    props.type === 'warning' ? 'bi-exclamation' : 'bi-check2-all'
                ]"
                style="stroke-width: 2px;"
            ></i>
        </div>

        <div
            :class="[
                'mt-0.5 px-2 py-2 rounded-lg overflow-hidden',
                'text-normal text-left text-white' ,
            ]"
            :style="{
                backgroundColor: messageColor,
            }"
        >
            <p class="text-normal text-white">{{ text }}</p>
        </div>
    </div>
</template>
