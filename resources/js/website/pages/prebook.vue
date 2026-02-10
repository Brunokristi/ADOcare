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
const preference = ref('notify')
const isSubmitting = ref(false)
const submitMessage = ref<{ type: 'success' | 'error', text: string } | null>(null)

const handleSubmit = async (e: Event) => {
    e.preventDefault()
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
            }),
        })

        const data = await response.json()

        if (response.ok) {
            submitMessage.value = { type: 'success', text: 'Ďakujeme za vašu registráciu!' }
            email.value = ''
            preference.value = 'notify'
        } else {
            submitMessage.value = { type: 'error', text: data.message || 'Došlo k chybe pri odoslaní formulára.' }
        }
    } catch (error) {
        submitMessage.value = { type: 'error', text: 'Chyba pri komunikácii so serverom.' }
    } finally {
        isSubmitting.value = false
    }
}
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
                    :brand-colors="brandColors"
                    required
                />

                <FormField
                    label=""
                    type="select"
                    v-model="preference"
                    :options="[
                        { label: 'chcem byť informovaný o spustení aplikácie', value: 'notify' },
                        { label: 'chcem sa zúčastniť testovania aplikácie', value: 'test' },
                    ]"
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