<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import WebsiteNavbar from '@/website/components/WebsiteNavbar.vue'
import WebsiteFooter from '@/website/components/WebsiteFooter.vue'
import { getThemeColors } from '@/website/config/themes'
import type { BrandColors } from '@/website/config/themes'

const router = useRouter()
const route = useRoute()

onMounted(() => {
    window.addEventListener('unauthenticated', () => {
        router.push({ name: 'website-home' })
    })
})

const brandColors = computed<BrandColors>(() => {
    const theme = route.meta.theme as any
    const colors = getThemeColors(theme)
    console.log('Current theme:', theme)
    console.log('Brand colors:', colors)
    return colors
})
</script>

<template>
    <div class="flex flex-col min-h-screen" :style="{ backgroundColor: brandColors.primary }">
        <WebsiteNavbar
            class="flex-none"
            :brand-colors="brandColors"
        />

        <main class="flex-1 overflow-auto p-8 pt-20">
            <router-view />
        </main>

        <WebsiteFooter 
            v-if="route.meta.theme"
            class="flex-none"
            :brand-colors="brandColors"
        />
    </div>
</template>
