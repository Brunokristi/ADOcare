<script setup lang="ts">
import Button from '@/website/components/Button.vue'
import FormField from '../components/FormField.vue'
import Message from '../components/Message.vue'
import { useRoute } from 'vue-router'
import { getThemeColors } from '@/website/config/themes'
import type { BrandColors } from '@/website/config/themes'
import { computed, ref, watch } from 'vue'

const route = useRoute()

const brandColors = computed<BrandColors>(() => {
    return getThemeColors(route.meta.theme as any)
})

const email = ref('')
const preference = ref('notify')
const website = ref('')
const emailError = ref('')
const preferenceError = ref('')
const isSubmitting = ref(false)
const submitMessage = ref<{ type: 'success' | 'warning', label?: string, text: string } | null>(null)

const validateForm = () => {
    let isValid = true

    emailError.value = ''
    preferenceError.value = ''

    if (!email.value.trim()) {
        emailError.value = 'Email je povinný'
        isValid = false
    } 
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        emailError.value = 'Neplatný formát emailu'
        isValid = false
    }

    if (!preference.value) {
        preferenceError.value = 'Vyberte možnosť'
        isValid = false
    }

    return isValid
}

const handleSubmit = async (e: Event) => {
    e.preventDefault()

    if (!validateForm()) {
        return
    }

    isSubmitting.value = true
    submitMessage.value = null

    try {
        const response = await fetch('/api/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                email: email.value,
                preference: preference.value,
                website: website.value, // honeypot
            }),
        })

        const data = await response.json()

        if (response.ok) {
            submitMessage.value = {
                type: 'success',
                label: 'úspech',
                text: 'ďakujeme za vašu registráciu.'
            }
            email.value = ''
            preference.value = 'notify'
        } else {
            submitMessage.value = {
                type: 'warning',
                label: 'chyba',
                text: data.message || 'došlo k chybe pri odoslaní formulára.'
            }
        }
    } catch (error) {
        submitMessage.value = {
            type: 'warning',
            label: 'chyba',
            text: 'chyba pri komunikácii so serverom.'
        }
    } finally {
        isSubmitting.value = false
    }
}

watch(email, () => {
    if (emailError.value) emailError.value = ''
})

watch(preference, () => {
    if (preferenceError.value) preferenceError.value = ''
})


</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-20 md:gap-10">
        <div class="flex flex-col gap-3">
            <Message
                label="upozornenie"
                text="aplikácia je vo vývoji a momentálne ju testujeme s vybranými zdravotnými zariadeniami, preto nie je možné ju zakúpiť. nechajte nám svoju emailovú adresu, a ozveme sa vám."
                type="warning"
            />
        </div>

        <div class="flex flex-col gap-3">
            <form @submit="handleSubmit">
                <FormField
                    label="emailová adresa"
                    type="email"
                    v-model="email"
                    :error="emailError"
                    :brand-colors="brandColors"
                />

                <FormField
                    label=""
                    type="select"
                    v-model="preference"
                    :error="preferenceError"
                    :options="[
                        { label: 'chcem byť informovaný o spustení aplikácie', value: 'notify' },
                        { label: 'chcem sa zúčastniť testovania aplikácie', value: 'test' },
                    ]"
                    :brand-colors="brandColors"
                />

                <input
                    v-model="website"
                    type="text"
                    name="website"
                    autocomplete="off"
                    tabindex="-1"
                    class="absolute left-[-9999px] top-[-9999px]"
                />

                <Button
                    label="odoslať"
                    color="light"
                    textColor="dark"
                    variant="light"
                    icon="bi-arrow-right"
                    align="right"
                    type="submit"
                    :disabled="isSubmitting"
                    :brand-colors="brandColors"
                />

                <div class="mt-4">
                    <Message
                        v-if="submitMessage"
                        :label="submitMessage.label"
                        :text="submitMessage.text"
                        :type="submitMessage.type"
                    />
                </div>
            </form>
        </div>

        <div class="flex flex-col gap-3">
        </div>
    </div>
</template>