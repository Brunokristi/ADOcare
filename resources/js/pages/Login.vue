<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import auth from '@/services/auth';
import AdoInput from '@/components/ado/AdoInput.vue';
import AdoButton from '@/components/ado/AdoButton.vue';

const router = useRouter();
const route = useRoute();

const login = ref('');
const pin = ref('');
const loading = ref(false);
const error = ref('');

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        await auth.login({ login: login.value, pin: pin.value });
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
            <form @submit.prevent="submit" class="space-y-3">
                <label for="login" class="block text-sm font-medium">Prihlasovacie meno/kód</label>
                <AdoInput id="login" class="w-100" v-model="login" placeholder="Prihlasovacie meno/kód" />

                <label for="pin" class="block text-sm font-medium">PIN</label>
                <AdoInput id="pin" class="w-100" v-model="pin" type="password" placeholder="PIN" />

                <AdoButton type="submit" :disabled="loading" class="w-full justify-center">Prihlásiť</AdoButton>
                <div v-if="error" class="text-red-600">{{ error }}</div>
            </form>
        </div>
    </div>
</template>

<style scoped></style>
