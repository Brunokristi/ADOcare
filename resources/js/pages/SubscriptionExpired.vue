<script setup lang="ts">
import { computed } from 'vue'
import Button from 'primevue/button'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { formatSubscriptionEndDate } from '@/utils/subscription'
import auth from '@/services/auth'

const router = useRouter()
const authStore = useAuthStore()

const company = computed(() => authStore.user?.company ?? null)

const companyName = computed(() => company.value?.name ?? 'Vaša spoločnosť')
const subscriptionEndsAt = computed(() => formatSubscriptionEndDate(company.value?.subscription_ends_at))

async function logout() {
    try {
        await auth.logout()
    } finally {
        router.push({ name: 'login' })
    }
}
</script>

<template>
    <div class="min-h-full flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-2xl rounded-3xl border border-lightgrey bg-white shadow-xl p-8 md:p-10">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-danger/10 text-danger flex items-center justify-center text-2xl shrink-0">
                    <i class="bi bi-exclamation-triangle-fill" />
                </div>

                <div class="flex-1">
                    <p class="text-mini uppercase tracking-[0.25em] text-darkgrey mb-2">Predplatné vypršalo</p>
                    <h1 class="text-3xl font-semibold text-heading-accent mb-4">
                        Prístup je dočasne obmedzený
                    </h1>

                    <p class="text-normal leading-7 text-darkgrey mb-4">
                        Predplatné spoločnosti <strong>{{ companyName }}</strong> už nie je aktívne.
                        Aby ste mohli pokračovať v používaní aplikácie, je potrebné predplatné obnoviť.
                    </p>

                    <div class="grid gap-3 rounded-2xl bg-lightgrey/40 p-4 mb-6">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-sm text-darkgrey">Spoločnosť</span>
                            <span class="font-medium text-heading-accent text-right">{{ companyName }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-sm text-darkgrey">Dátum vypršania</span>
                            <span class="font-medium text-heading-accent text-right">{{ subscriptionEndsAt }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <Button label="Odhlásiť sa" icon="pi pi-sign-out" severity="secondary" outlined @click="logout" />
                        <Button label="Skúsiť znovu načítať" icon="pi pi-refresh" class="bg-accent! border-0!" @click="router.go(0)" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>