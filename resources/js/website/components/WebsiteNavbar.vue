<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
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

const router = useRouter()
const route = useRoute()

const isMenuOpen = ref(false)
const previousRoute = ref<string | null>(null)

const toggleMenu = () => {
    if (route.name === 'website-nav') {
        if (previousRoute.value) {
            router.push({ name: previousRoute.value })
        }
    } else {
        previousRoute.value = route.name as string
        router.push({ name: 'website-nav' })
    }
}
</script>

<template>
    <nav class="fixed top-0 left-0 right-0 w-full z-50">
        <div class="px-8">
            <div class="flex justify-between items-center h-16">
                <button
                    @click="router.push({ name: 'website-home' })"
                    class="focus:outline-none cursor-pointer"
                >
                    <Logo
                        :light="brandColors.light"
                        :dark="brandColors.dark"
                        width="70"
                        height="70"
                    />
                </button>

                <button
                    @click="toggleMenu"
                    class="rounded-full flex items-center justify-center focus:outline-none cursor-pointer"
                    :style="{ 
                        backgroundColor: brandColors.light,
                        color: brandColors.dark,
                        width: '30px',
                        height: '30px'
                    }"
                >
                    <i :class="['bi', 'text-xs', isMenuOpen ? 'bi-arrows-angle-contract' : 'bi-arrows-angle-expand']"></i>
                </button>
            </div>
        </div>
    </nav>
</template>
