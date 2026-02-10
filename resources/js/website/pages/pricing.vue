<script setup lang="ts">
import Info from '@/website/components/Info.vue'
import Button from '@/website/components/Button.vue'
import { useRoute } from 'vue-router'
import { getThemeColors } from '@/website/config/themes'
import type { BrandColors } from '@/website/config/themes'
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const brandColors = computed<BrandColors>(() => {
    return getThemeColors(route.meta.theme as any)
})
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-10">
        <div class="flex flex-col gap-3">
            <Info
                label="začiatok"
                second-label="20€ / mesiac"
                :functionalities="['všetky funkcie', 'jeden používateľ']"
                :to="{ name: 'website-prebook' }"
                color="light"
                :brand-colors="brandColors"
            />
        </div>

        <div class="flex flex-col gap-3">
            <Info
                label="rast"
                second-label="30€ / mesiac"
                :functionalities="['všetky funkcie', 'jeden používateľ']"
                :perks="['prioritná podpora', 'prispôsobenie']"
                :to="{ name: 'website-prebook' }"
                color="light"
                :brand-colors="brandColors"
            />
        </div>

        <div class="flex flex-col gap-3">
            <Info
                label="rast"
                second-label="30€ / mesiac"
                :functionalities="['všetky funkcie', 'jeden používateľ']"
                :perks="['prioritná podpora', 'prispôsobenie', 'školenie']"
                :to="{ name: 'website-prebook' }"
                color="light"
                :brand-colors="brandColors"
            />

            <span class="flex flex-col mt-8">
                <p class="text-normal text-lightgrey text-end">nie ste si istí?</p>
                <Button
                    label="pomôžeme vám s výberom"
                    color="light"
                    text-color="dark"
                    variant="light"
                    icon="bi-arrow-right"
                    align="right"
                    :brand-colors="brandColors"
                    @click="router.push({ name: 'website-contact' })"
                />
            </span>
        </div>
    </div>
</template>