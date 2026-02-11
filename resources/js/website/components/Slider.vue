<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import type { BrandColors } from '@/website/config/themes'

interface Slide {
    title: string
    text: string
}

interface Props {
    slides: Slide[]
    height?: string
    brandColors?: BrandColors
    autoSlide?: boolean
    autoSlideInterval?: number
}

const props = withDefaults(defineProps<Props>(), {
    backgroundColor: '#333333',
    autoSlide: true,
    autoSlideInterval: 3000,
    brandColors: () => ({
        primary: '#5C9EAD',
        light: '#DEECEF',
        dark: '#575252',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    }),
})

const currentIndex = ref(0)
const sliderHeight = ref('auto')
let autoSlideTimer: ReturnType<typeof setInterval> | null = null

const nextSlide = () => {
    currentIndex.value = (currentIndex.value + 1) % props.slides.length
    resetAutoSlide()
}

const prevSlide = () => {
    currentIndex.value = (currentIndex.value - 1 + props.slides.length) % props.slides.length
    resetAutoSlide()
}

const startAutoSlide = () => {
    if (props.autoSlide && props.slides.length > 1) {
        autoSlideTimer = setInterval(() => {
            currentIndex.value = (currentIndex.value + 1) % props.slides.length
        }, props.autoSlideInterval)
    }
}

const stopAutoSlide = () => {
    if (autoSlideTimer) {
        clearInterval(autoSlideTimer)
        autoSlideTimer = null
    }
}

const resetAutoSlide = () => {
    stopAutoSlide()
    startAutoSlide()
}

onMounted(() => {
    startAutoSlide()
})

onUnmounted(() => {
    stopAutoSlide()
})
</script>

<template>
    <div class="w-full">
        <div
            ref="slidesContainer"
            class="relative w-full rounded-lg overflow-hidden"
            :style="{
                backgroundColor: brandColors.light,
                height: props.height || sliderHeight,}"
        >
        
            <div
                v-for="(slide, index) in slides"
                :key="index"
                data-slide
                :class="[
                    'absolute inset-0 w-full h-full p-8 flex flex-col justify-between transition-opacity duration-500',
                    currentIndex === index ? 'opacity-100' : 'opacity-0 pointer-events-none',
                ]"
            >
                <div>
                    <h3 class="text-normal text-white">{{ slide.title }}</h3>
                </div>

                <div>
                    <p class="text-normal" :style="{ color: brandColors.dark }">{{ slide.text }}</p>
                </div>
            </div>

            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                <button
                    v-for="(_, index) in slides"
                    :key="index"
                    @click="currentIndex = index"
                    :class="[
                        'w-1 h-1 rounded-full transition-all cursor-pointer',
                        currentIndex === index ? 'w-8' : 'w-1',
                    ]"
                    :style="{
                        backgroundColor: currentIndex === index ? brandColors.primary : brandColors.secondary,
                    }"
                    :aria-label="`Go to slide ${index + 1}`"
                ></button>
            </div>
        </div>

        <div class="flex justify-end gap-4 mt-2">
            <button
                @click="prevSlide"
                :style="{ color: brandColors.dark }"
                aria-label="Previous slide"
            >
                <i class="bi bi-arrow-left text-normal text-white cursor-pointer hover:opacity-80"></i>
            </button>

            <button
                @click="nextSlide"
                :style="{ color: brandColors.dark }"
                aria-label="Next slide"
            >
                <i class="bi bi-arrow-right text-normal text-white cursor-pointer hover:opacity-80"></i>
            </button>
        </div>
    </div>
</template>
