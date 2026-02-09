<script setup lang="ts">
import router from '@/router';
import Button from './Button.vue'
import FooterLogo from './FooterLogo.vue'
import type { BrandColors } from '@/website/config/themes'


interface Props {
    brandColors?: BrandColors
}

withDefaults(defineProps<Props>(), {
    brandColors: () => ({
        primary: '#5C9EAD',
        light: '#DEECEF',
        dark: '#575252',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    }),
})

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const navigateToHome = () => {
    router.push({ name: 'website-home' })
}
</script>

<template>
    <footer class="text-center p-8">
            <Button
                icon="bi-arrow-up"
                label="hore"
                color="light"
                type="light"
                align="left"
                @click="scrollToTop"
                :brand-colors="brandColors"
            />
            <Button
                icon="bi-arrow-right"
                label="úvod"
                color="light"
                type="light"
                align="left"
                @click="navigateToHome"
                :brand-colors="brandColors"
                class="mb-4"
            />

            <FooterLogo
                :primary="brandColors.primary"
                :dark="brandColors.light"
                :light="brandColors.dark"
                :height="30"
                class="mb-4"

            />


            <p class="text-normal text-left" :style="{ color: brandColors.light }">všetky práva vyhradené</p>
    </footer>
</template>
