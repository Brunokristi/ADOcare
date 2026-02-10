<script setup lang="ts">
import Button from '@/website/components/Button.vue'
import FormField from '../components/FormField.vue'
import { useRoute } from 'vue-router'
import { getThemeColors } from '@/website/config/themes'
import type { BrandColors } from '@/website/config/themes'
import { computed, ref } from 'vue'

const route = useRoute()

const brandColors = computed<BrandColors>(() => {
    return getThemeColors(route.meta.theme as any)
})

const email = ref('')
const message = ref('')
const screenshot = ref<File | null>(null)

</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-20 md:gap-10">
        <div class="flex flex-col gap-3">
            <form>
                <FormField
                    label="emailová adresa"
                    type="email"
                    v-model="email"
                    :brand-colors="brandColors"
                    required
                />

                <FormField
                    label="popis chyby / návrh"
                    type="textarea"
                    v-model="message"
                    :brand-colors="brandColors"
                />
                
                <FormField
                    label="obrázky / snímky obrazovky"
                    type="file"
                    :multiple="true"
                    v-model="screenshot"
                    :brand-colors="brandColors"
                />
                <Button
                    label="odoslať"
                    color="light"
                    text-color="dark"
                    variant="light"
                    icon="bi-arrow-right"
                    align="right"
                    :brand-colors="brandColors"
                />
            </form>
        </div>

        <div class="flex flex-col gap-3">
        </div>
    </div>
</template>