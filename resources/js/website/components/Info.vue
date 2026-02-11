<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { BrandColors } from '@/website/config/themes'

interface Props {
    label?: string
    secondLabel?: string
    color?: 'primary' | 'secondary' | 'warning' | 'success' | 'dark' | 'light'
    brandColors?: BrandColors
    functionalities?: string[]
    perks?: string[]
    to?: { name: string } | string
}

const props = withDefaults(defineProps<Props>(), {
    color: 'primary',
    functionalities: () => [],
    perks: () => [],
    brandColors: () => ({
        primary: '#5C9EAD',
        light: '#DEECEF',
        dark: '#575252',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    }),
})

const router = useRouter()

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

const handleClick = () => {
    if (props.to) {
        if (typeof props.to === 'string') {
            router.push(props.to)
        } else {
            router.push(props.to)
        }
    }
}
</script>

<style scoped>
.functionalities-list {
    list-style-type: '- ';
}

.perks-list {
    list-style-type: '+ ';
}
</style>

<template>
    <div class="w-full">
        <button
            @click="handleClick"
            :class="[
                'w-full px-2 py-2 rounded-lg transition-all duration-200',
                'flex items-center justify-between',
                'hover:opacity-80',
                'cursor-pointer',
                'text-normal text-white',
            ]"
            :style="{
                backgroundColor: buttonColor,
            }"
        >
            <div class="flex items-start gap-2">
                <span class="text-normal text-white">{{ label }}</span>
                <span v-if="secondLabel" class="text-normal text-lightgrey">{{ secondLabel }}</span>
            </div>
            <i class="bi bi-arrow-right text-normal"></i>
        </button>

        <div
            :class="[
                'mt-0.5 px-2 py-2 rounded-lg',
                'text-normal text-left',
            ]"
            :style="{
                backgroundColor: buttonColor,
            }"
        >

            <div v-if="functionalities.length > 0" :class="'mb-1'">
                <ul class="functionalities-list list-inside space-y-1">
                    <li v-for="(functionality, index) in functionalities" :key="index" class="text-normal text-lightgrey">
                        {{ functionality }}
                    </li>
                </ul>
            </div>

            <div v-if="perks.length > 0" :class="'mb-1'">
                <ul class="perks-list list-inside space-y-1">
                    <li v-for="(perk, index) in perks" :key="index" class="text-normal text-white">
                        {{ perk }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
