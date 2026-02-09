<script setup lang="ts">
import Button from '@/website/components/Button.vue'
import { useRoute } from 'vue-router'
import { getThemeColors } from '@/website/config/themes'
import type { BrandColors } from '@/website/config/themes'
import { computed } from 'vue'

const route = useRoute()

const brandColors = computed<BrandColors>(() => {
    return getThemeColors(route.meta.theme as any)
})
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-20 md:gap-10">
        <div class="flex flex-col gap-3">
            <Button
                icon="bi-arrow-right"
                label="email"
                color="light"
                textColor="dark"
                align="left"
                variant="solid"
                :brand-colors="brandColors"
            />
            <Button
                icon="bi-arrow-right"
                label="whatsapp"
                color="light"
                textColor="dark"
                align="left"
                variant="solid"
                :brand-colors="brandColors"
            />
            <Button
                icon="bi-arrow-right"
                label="správa"
                color="light"
                textColor="dark"
                align="left"
                variant="solid"
                :brand-colors="brandColors"
            />
            <Button
                icon="bi-arrow-right"
                label="telefón"
                color="light"
                textColor="dark"
                variant="solid"
                :brand-colors="brandColors"
            />
        </div>



        <div class="flex flex-col gap-3">
        </div>

        <div class="flex flex-col gap-3">
        </div>
    </div>
</template>