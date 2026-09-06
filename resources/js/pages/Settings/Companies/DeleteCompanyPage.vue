<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const auth = useAuthStore()

const loading = ref(true)
const deleting = ref(false)
const error = ref<string | null>(null)
const confirmName = ref('')

const companyName = computed(() => auth.user?.company?.name ?? '')
const canDelete = computed(() => confirmName.value.trim() === companyName.value && companyName.value !== '')

onMounted(async () => {
    if (!auth.user?.company) {
        await auth.init()
    }
    loading.value = false
})

async function deleteCompany() {
    if (!canDelete.value) return

    deleting.value = true
    error.value = null

    try {
        await api.delete('v1/my-company', { data: { confirm_name: confirmName.value.trim() } })

        // The Company/User is gone server-side (tokens revoked too) - the current session is
        // no longer valid, so clear it locally and send the user straight to the login page.
        await auth.clearAuth()
        router.push({ name: 'login' })
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Zmazanie spoločnosti sa nepodarilo. Skúste to prosím znova.'
    } finally {
        deleting.value = false
    }
}
</script>

<template>
    <div v-if="!loading" class="flex flex-col gap-6 bg-white rounded-md p-6 max-w-2xl">
        <div>
            <h2 class="text-heading-accent text-danger mb-2">Zmazať spoločnosť</h2>
            <p class="text-normal text-lightgrey">
                Táto akcia natrvalo deaktivuje spoločnosť <strong>{{ companyName }}</strong> a všetko, čo s ňou súvisí:
                pobočky, autá, pacientov, dokumenty, ceny výkonov, faktúry, používateľov a fakturačné údaje.
                Používatelia a pacienti sú zálohovaní a obnoviteľné podporou, ostatné dáta sa zmažú natrvalo.
            </p>
        </div>

        <div class="rounded-md bg-tag3 p-4 text-normal text-lightgrey">
            Po zmazaní budete okamžite odhlásený a už sa nebudete môcť prihlásiť pod týmto účtom.
        </div>

        <div v-if="error" class="rounded-md bg-danger p-4 text-normal text-white">{{ error }}</div>

        <div class="flex flex-col gap-2">
            <label class="text-normal">
                Pre potvrdenie napíšte presný názov spoločnosti: <strong>{{ companyName }}</strong>
            </label>
            <InputText v-model="confirmName" class="w-full" :disabled="deleting" />
        </div>

        <div class="flex justify-end">
            <Button
                label="Natrvalo zmazať spoločnosť"
                :loading="deleting"
                :disabled="!canDelete"
                class="bg-danger! border-0!"
                @click="deleteCompany"
            />
        </div>
    </div>
</template>
