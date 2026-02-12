<script setup lang="ts">
import Button from '@/website/components/Button.vue'
import { useRoute, useRouter } from 'vue-router'
import { getThemeColors } from '@/website/config/themes'
import type { BrandColors } from '@/website/config/themes'
import { computed } from 'vue'

const route = useRoute()
const router = useRouter()

const brandColors = computed<BrandColors>(() => {
    return getThemeColors(route.meta.theme as any)
})

const navigateToLogin = () => {
    window.location.href = '/login'
}
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-10 md:gap-10">
        <div class="flex flex-col gap-3">
            <Button
                icon="bi-arrow-right"
                label="úvod"
                color="light"
                textColor="dark"
                align="left"
                variant="solid"
                :brand-colors="brandColors"
                @click="router.push({ name: 'website-home' })"
            />
            <Button
                icon="bi-arrow-right"
                label="špecifikácia"
                color="light"
                textColor="dark"
                align="left"
                variant="solid"
                :brand-colors="brandColors"
                @click="router.push({ name: 'website-specification' })"
            />
            <Button
                icon="bi-arrow-right"
                label="cenník"
                color="light"
                textColor="dark"
                align="left"
                variant="solid"
                :brand-colors="brandColors"
                @click="router.push({ name: 'website-pricing' })"
            />
            <Button
                icon="bi-arrow-right"
                label="kontakt"
                color="light"
                textColor="dark"
                align="left"
                variant="solid"
                :brand-colors="brandColors"
                @click="router.push({ name: 'website-contact' })"
            />
        </div>

        <div class="flex flex-col gap-3">
            <img 
                src="/nav.png" 
                alt="Navigation" 
                class="w-full h-auto"
            />
        </div>

        <div class="flex flex-col gap-3">
            <Button
                icon="bi-arrow-right"
                label="aplikácia"
                color="light"
                textColor="dark"
                variant="solid"
                :brand-colors="brandColors"
                @click="navigateToLogin"
            />
            <Button
                icon="bi-arrow-right"
                label="nahlásiť problém"
                color="light"
                textColor="dark"
                variant="solid"
                :brand-colors="brandColors"
                @click="router.push({ name: 'website-bug' })"
            />
        </div>

        
    </div>
</template>