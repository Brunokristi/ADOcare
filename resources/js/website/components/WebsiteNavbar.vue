<script setup lang="ts">
import { ref } from 'vue'
import Logo from './Logo.vue'
import type { BrandColors } from '@/website/config/themes'

interface Props {
    brandColors?: BrandColors
}

withDefaults(defineProps<Props>(), {
    brandColors: () => ({
        primary: '#ffffff',
        light: '#DEECEF',
        dark: '#5C9EAD',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    }),
})

const isMenuOpen = ref(false)

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value
}
</script>

<template>
    <nav class="fixed top-0 left-0 right-0 w-full z-50">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <Logo
                    :light="brandColors.light"
                    :dark="brandColors.dark"
                    width="70"
                    height="70"
                />

                <button
                    @click="toggleMenu"
                    class="rounded-full flex items-center justify-center focus:outline-none"
                    :style="{ 
                        backgroundColor: brandColors.light,
                        color: brandColors.dark,
                        width: '30px',
                        height: '30px'
                    }"
                >
                    <i class="bi bi-arrows-angle-expand text-xs"></i>
                </button>
            </div>
        </div>
    </nav>
</template>
