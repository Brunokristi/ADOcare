<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()

const form = ref({
    first_name: '',
    last_name: '',
    email: '',
    company_name: '',
    pin: '',
    pin_confirmation: '',
})

const loading = ref(false)
const submitted = ref(false)

async function submit() {
    submitted.value = true

    if (
        !form.value.first_name || !form.value.last_name || !form.value.email ||
        !form.value.company_name || !form.value.pin || !form.value.pin_confirmation
    ) {
        return
    }

    loading.value = true

    try {
        const res = await api.post('auth/register-company', form.value)
        const token = res.data?.data?.token

        if (!token) throw new Error('No token received')

        await authStore.setAuth(token)
        await authStore.waitUntilInitialized()

        router.push({ name: 'onboarding' })
    } catch (e: any) {
        const message = e.response?.data?.message || 'Registrácia sa nepodarila. Skúste to prosím znova.'
        toast.add({ severity: 'error', summary: 'Chyba', detail: message, life: 4000 })
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="h-full flex items-center justify-center">
        <div class="w-full max-w-md min-w-100 bg-white">
            <h1 class="text-heading-accent text-center mb-10">
                Vytvorte si účet<br>a začnite so skúšobným obdobím.
            </h1>

            <form @submit.prevent="submit" class="flex flex-col gap-5">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-normal mb-1">Meno</label>
                        <InputText class="w-full" v-model="form.first_name" />
                        <small v-if="submitted && !form.first_name" class="text-danger">Meno je povinné.</small>
                    </div>
                    <div>
                        <label class="block text-normal mb-1">Priezvisko</label>
                        <InputText class="w-full" v-model="form.last_name" />
                        <small v-if="submitted && !form.last_name" class="text-danger">Priezvisko je povinné.</small>
                    </div>
                </div>

                <div>
                    <label class="block text-normal mb-1">Email</label>
                    <InputText class="w-full" type="email" v-model="form.email" />
                    <small v-if="submitted && !form.email" class="text-danger">Email je povinný.</small>
                </div>

                <div>
                    <label class="block text-normal mb-1">Názov spoločnosti</label>
                    <InputText class="w-full" v-model="form.company_name" />
                    <small v-if="submitted && !form.company_name" class="text-danger">Názov spoločnosti je povinný.</small>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-normal mb-1">Heslo</label>
                        <InputText class="w-full" type="password" v-model="form.pin" />
                        <small v-if="submitted && !form.pin" class="text-danger">Heslo je povinné.</small>
                    </div>
                    <div>
                        <label class="block text-normal mb-1">Potvrdiť heslo</label>
                        <InputText class="w-full" type="password" v-model="form.pin_confirmation" />
                        <small v-if="submitted && !form.pin_confirmation" class="text-danger">Potvrďte heslo.</small>
                    </div>
                </div>

                <Button type="submit" :loading="loading"
                    class="relative w-full flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey!">
                    Vytvoriť účet
                </Button>

                <router-link :to="{ name: 'login' }" class="text-normal text-center text-lightgrey">
                    Už máte účet? Prihláste sa
                </router-link>
            </form>
        </div>
    </div>
</template>
