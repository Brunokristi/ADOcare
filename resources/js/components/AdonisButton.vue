<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

interface Props {
    label?: string
    loadingLabel?: string
    disabled?: boolean
    loading?: boolean
    icon?: string
    loadingIcon?: string
    initialExpandMs?: number
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Hej, Adonis!',
    loadingLabel: 'Adonis premýšľa…',
    disabled: false,
    loading: false,
    icon: '/adonis.svg',
    loadingIcon: '/adonis_thinking.svg',
    initialExpandMs: 2200
})

const emit = defineEmits<{
    (e: 'click', event: MouseEvent): void
}>()

const hovered = ref(false)
const showInitially = ref(true)

let timer: ReturnType<typeof setTimeout> | null = null

const expanded = computed(() => {
    return props.loading || hovered.value || showInitially.value
})

const currentLabel = computed(() => {
    return props.loading ? props.loadingLabel : props.label
})

const iconIsImage = computed(() => {
    const icon = String(props.icon ?? '')
    return icon.startsWith('/') || icon.includes('.svg') || icon.includes('.png') || icon.includes('.webp')
})

const loadingIconIsImage = computed(() => {
    const icon = String(props.loadingIcon ?? '')
    return icon.startsWith('/') || icon.includes('.svg') || icon.includes('.png') || icon.includes('.webp')
})

function handleClick(event: MouseEvent) {
    emit('click', event)
}

onMounted(() => {
    const preloadIcon = (src: string | undefined) => {
        const iconSrc = String(src ?? '').trim()
        if (!iconSrc) return
        if (!(iconSrc.startsWith('/') || iconSrc.includes('.svg') || iconSrc.includes('.png') || iconSrc.includes('.webp'))) return

        const img = new Image()
        img.src = iconSrc
    }

    preloadIcon(props.icon)
    preloadIcon(props.loadingIcon)

    timer = setTimeout(() => {
        showInitially.value = false
    }, props.initialExpandMs)
})

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer)
})
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        @click="handleClick"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
        @focus="hovered = true"
        @blur="hovered = false"
        class="group relative inline-flex h-7 items-center overflow-hidden rounded-md border-0 bg-darkgrey! px-3 text-white transition-all duration-300 ease-out cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 hover:shadow-[0_0_10px_rgba(92,158,173)]"        
        :class="expanded ? 'gap-2 pr-4' : 'w-11 justify-center px-0'"
    >
        <span
            class="pointer-events-none absolute inset-0 rounded-md opacity-80 blur-md transition-all duration-300"
            :class="loading ? 'bg-accent/40 animate-pulse' : 'bg-accent/25'"
        ></span>

        <span class="relative z-10 flex items-center">
            <img
                v-if="loadingIconIsImage"
                v-show="loading"
                :src="props.loadingIcon"
                alt="Adonis loading"
                class="h-4 w-4 object-contain"
            />
            <img
                v-if="iconIsImage"
                v-show="!loading"
                :src="props.icon"
                alt="Adonis"
                class="h-4 w-4 object-contain"
            />
            <i v-if="!loading && !iconIsImage" :class="props.icon" class="text-base"></i>
            <i v-if="loading && !loadingIconIsImage" :class="props.loadingIcon" class="text-base"></i>
        </span>

        <span
            class="relative z-10 whitespace-nowrap text-sm font-medium transition-all duration-300"
            :class="expanded ? 'max-w-[160px] opacity-100 ml-2' : 'max-w-0 opacity-0 ml-0'"
        >
            {{ currentLabel }}
        </span>
    </button>
</template>