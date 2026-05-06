<script setup lang="ts">
import { computed } from 'vue'
import Button from 'primevue/button'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import auth from '@/services/auth'

const router = useRouter()
const authStore = useAuthStore()

const company = computed(() => authStore.user?.company ?? null)

const companyName = computed(() => company.value?.name ?? 'Vaša spoločnosť')

async function logout() {
    try {
        await auth.logout()
    } finally {
        router.push({ name: 'login' })
    }
}
</script>

<template>
    <div class="min-h-full flex items-center justify-center px-6 py-12 bg-danger rounded-md">
        <div class="w-full p-8 md:p-10">
                <div class="flex-1">
                    <div class="flex gap-2 items-center mb-6">
                        <i class="bi bi-exclamation-triangle-fill text-white text-xl" />

                        <h1 class="text-heading-accent! text-white">
                            Prístup je dočasne obmedzený
                        </h1>
                    </div>

                    <p class="text-normal leading-7 text-white mb-4">
                        Predplatné spoločnosti <strong>{{ companyName }}</strong> už nie je aktívne.
                        Aby ste mohli pokračovať v používaní aplikácie, je potrebné predplatné obnoviť.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <Button label="Skúsiť znovu načítať" class="bg-white! border-0! text-danger! px-2!" @click="router.go(0)" />
                        <Button label="Späť" class="bg-transparent! border-0! text-white! px-2!" @click="logout" />
                    </div>
                </div>
            </div>
        </div>
</template>