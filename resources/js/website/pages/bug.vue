<script setup lang="ts">
import Button from '@/website/components/Button.vue'
import FormField from '../components/FormField.vue'
import Message from '../components/Message.vue'
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
const screenshot = ref<File[] | null>(null)
const isLoading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const submitForm = async (e: Event) => {
    e.preventDefault()
    isLoading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    const formData = new FormData()
    formData.append('email', email.value)
    formData.append('message', message.value)
    
    if (screenshot.value && Array.isArray(screenshot.value)) {
        screenshot.value.forEach((file, index) => {
            formData.append(`screenshot[${index}]`, file)
        })
    }

    try {
        const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
        
        const response = await fetch('/api/bug-report', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token || '',
            },
            body: formData
        })

        const data = await response.json()

        if (response.ok) {
            successMessage.value = 'ďakujeme'
            email.value = ''
            message.value = ''
            screenshot.value = null
        } else {
            errorMessage.value = data.message || 'Chyba pri odosielaní formulára'
        }
    } catch (error) {
        errorMessage.value = 'Chyba pri odosielaní formulára'
    } finally {
        isLoading.value = false
    }
}

</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-10 md:gap-10">
        <div class="flex flex-col gap-3">
            <form @submit="submitForm">
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
                    textColor="dark"
                    variant="light"
                    icon="bi-arrow-right"
                    align="right"
                    :brand-colors="brandColors"
                    type="submit"
                    :disabled="isLoading"
                />
            </form>
        </div>
        
        <div class="flex flex-col gap-3">
        </div>

        <div class="flex flex-col gap-3">
            <Message
                v-if="successMessage"
                label="úspech"
                text="ďakujeme za spätnú väzbu. vášmu podnetu sa budeme venovať čo najskôr."
                type="success"
            />
            <Message
                v-if="errorMessage"
                label="chyba"
                text="chyba pri odosielaní formulára"
                type="warning"
            />
        </div>
    </div>
</template>