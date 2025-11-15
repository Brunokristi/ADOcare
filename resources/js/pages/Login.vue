<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import auth from '@/services/auth';
import AdoInput from '@/components/ado/AdoInput.vue';
import AdoButton from '@/components/ado/AdoButton.vue';

const router = useRouter();
const route = useRoute();

const code = ref('');
const pin = ref('');
const loading = ref(false);
const error = ref('');

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        await auth.login({ code: code.value, pin: pin.value });
        const redirect = (route.query.redirect as string) || '/';
        router.push(redirect);
    } catch (e: any) {
        error.value = e?.response?.data?.message || 'Login failed';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center">
        <div class="w-full max-w-md p-6 bg-white rounded shadow">
            <h1 class="text-heading-accent text-center mb-4">Vitajte 👋<br>Prihláste sa do svojho účtu.</h1>
            <div class="space-y-3">
                <label class="block text-sm font-medium">Prihlasovací kód</label>
                <AdoInput class="w-100" v-model="code" placeholder="Prihlasovací kód" />

                <label class="block text-sm font-medium">PIN</label>
                <AdoInput class="w-100" v-model="pin" type="password" placeholder="PIN" />

                <AdoButton @click="submit" :disabled="loading" class="w-full justify-center">Prihlásiť</AdoButton>
                <div v-if="error" class="text-red-600">{{ error }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
