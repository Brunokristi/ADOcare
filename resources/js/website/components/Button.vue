<script setup lang="ts">

interface Props {
    icon?: string
    label?: string
    secondLabel?: string
    color?: 'primary' | 'secondary' | 'warning' | 'success' | 'dark' | 'light'
    align?: 'left' | 'center' | 'right'
    variant?: 'light' | 'solid'
    brandColors?: {
        primary: string
        light: string
        dark: string
        secondary: string
        warning: string
        success: string
    }
}

interface Emits {
    (e: 'click'): void
}

const props = withDefaults(defineProps<Props>(), {
    color: 'primary',
    align: 'center',
    variant: 'light',
    brandColors: () => ({
        primary: '#5C9EAD',
        light: '#DEECEF',
        dark: '#575252',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    }),
})

defineEmits<Emits>()

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

const alignClasses = {
    left: 'justify-start',
    center: 'justify-center',
    right: 'justify-end',
}

const buttonColor = getColorValue(props.color)

const getButtonStyles = () => {
    if (props.variant === 'solid') {
        return {
            backgroundColor: buttonColor,
            color: ['light'].includes(props.color) ? props.brandColors.dark : '#ffffff',
        }
    }
    return {
        backgroundColor: 'transparent',
    }
}

const getContentStyles = () => {
    if (props.variant === 'solid') {
        return {
            color: ['light'].includes(props.color) ? props.brandColors.dark : '#ffffff',
        }
    }
    return {
        color: buttonColor,
        borderBottomColor: buttonColor,
    }
}

</script>

<template>
    <button
        :class="[
            'w-full py-1 transition-all duration-200 flex',
            alignClasses[align],
            'hover:opacity-80',
            { 'px-2 py-2 rounded-lg': variant === 'solid' }
        ]"
        :style="getButtonStyles()"
    >
        <span
            v-if="variant === 'light'"
            :class="[
                'flex items-center gap-2 pr-1',
                'border-b-1',
                'text-normal'
            ]"
            :style="{color: buttonColor, borderBottomColor: buttonColor}"
        >
            <i v-if="icon" :class="`bi ${icon}`"></i>
            <span v-if="label">{{ label }}</span>
            <span v-if="secondLabel">{{ secondLabel }}</span>
        </span>

        <span
            v-else
            :class="[
                'flex items-center justify-between gap-2 w-full',
                'text-normal'
            ]"
            :style="getContentStyles()"
        >
            <span class="flex items-center gap-2">
                <span v-if="label" class="text-white">{{ label }} </span>
                <span v-if="secondLabel" class="text-lightgrey">{{ secondLabel }} </span>
            </span>
            <i v-if="icon" :class="`bi ${icon}`" style="stroke-width: 2px;"></i>
        </span>
    </button>
</template>
