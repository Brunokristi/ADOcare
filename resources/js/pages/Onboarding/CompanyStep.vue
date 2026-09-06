<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import OnboardingLayout from './OnboardingLayout.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const form = reactive({
    name: auth.user?.company?.name ?? '',
    ico: '',
    dic: '',
    ic_dph: '',
    address: '',
    city: '',
    psc: '',
})

const submitted = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

async function submit() {
    submitted.value = true
    error.value = null

    if (!form.name || !form.ico || !form.dic || !form.address || !form.city || !form.psc) {
        return
    }

    saving.value = true

    try {
        const res = await api.post('v1/onboarding/company', form)

        // Refresh the cached user/company (needsCompanyOnboarding() reads this) before leaving.
        await auth.init()

        // A StudioKristian outage must never block registration - the Company is already
        // saved, so we still proceed to the dashboard and let the activation area there
        // offer a retry instead of trapping the user on this step.
        const billingError = res.data?.data?.billing_error
        if (billingError) {
            toast.add({
                severity: 'warn',
                summary: 'Fakturácia sa zatiaľ nenastavila',
                detail: 'Skúsime to znova z dashboardu - vaše údaje o spoločnosti sú uložené.',
                life: 6000,
            })
        }

        router.push({ name: 'manager-dashboard' })
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Nepodarilo sa uložiť údaje o spoločnosti. Skúste to prosím znova.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <OnboardingLayout current-step="company">
        <div class="flex flex-col gap-6 bg-white rounded-md p-6">
            <div>
                <h1 class="text-heading-accent mb-2">Nastavme vašu spoločnosť</h1>
                <p class="text-normal text-lightgrey">
                    Potrebujeme len pár základných údajov, aby bol váš ADOCare priestor pripravený.
                </p>
            </div>

            <div v-if="error" class="rounded-md bg-danger p-4 text-normal text-white">{{ error }}</div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div>
                    <label class="block text-normal mb-1">Názov spoločnosti</label>
                    <InputText class="w-full" v-model="form.name" />
                    <small v-if="submitted && !form.name" class="text-danger">Názov spoločnosti je povinný.</small>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-6">
                        <label class="block text-normal mb-1">IČO</label>
                        <InputText class="w-full" v-model="form.ico" />
                        <small v-if="submitted && !form.ico" class="text-danger">IČO je povinné.</small>
                    </div>
                    <div class="col-span-6">
                        <label class="block text-normal mb-1">DIČ</label>
                        <InputText class="w-full" v-model="form.dic" />
                        <small v-if="submitted && !form.dic" class="text-danger">DIČ je povinné.</small>
                    </div>
                </div>

                <div>
                    <label class="block text-normal mb-1">IČ DPH <span class="text-lightgrey">(voliteľné)</span></label>
                    <InputText class="w-full" v-model="form.ic_dph" />
                </div>

                <div>
                    <label class="block text-normal mb-1">Ulica a číslo</label>
                    <InputText class="w-full" v-model="form.address" />
                    <small v-if="submitted && !form.address" class="text-danger">Adresa je povinná.</small>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-6">
                        <label class="block text-normal mb-1">Mesto</label>
                        <InputText class="w-full" v-model="form.city" />
                        <small v-if="submitted && !form.city" class="text-danger">Mesto je povinné.</small>
                    </div>
                    <div class="col-span-6">
                        <label class="block text-normal mb-1">PSČ</label>
                        <InputText class="w-full" v-model="form.psc" />
                        <small v-if="submitted && !form.psc" class="text-danger">PSČ je povinné.</small>
                    </div>
                </div>

                <p class="text-mini text-lightgrey">
                    Zvyšné údaje o spoločnosti môžete doplniť neskôr v Nastaveniach.
                </p>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        label="Vytvoriť spoločnosť a spustiť trial"
                        :loading="saving"
                        class="bg-accent! border-0!"
                    />
                </div>
            </form>
        </div>
    </OnboardingLayout>
</template>

