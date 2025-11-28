<script setup lang="ts">
import { onBeforeMount, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import auth from '@/services/auth';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const router = useRouter();
const route = useRoute();

// Before loading the login page, check if user is logged in

onBeforeMount(async () => {

    if (await auth.isAuthenticated()) {
        const redirect = (route.query.redirect as string) || '/';
        router.push(redirect);
    }
});

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
        <div class="w-full max-w-md min-w-75 p-6 bg-white rounded shadow-custom">
            <h1 class="text-heading-accent text-center mb-4">Vitajte 👋<br>Prihláste sa do svojho účtu.</h1>
            <form @submit.prevent="submit" class="space-y-3">
                <label for="login" class="block text-sm font-medium ">Prihlasovacie meno/kód</label>
                <InputText id="login" class="w-full" v-model="login" placeholder="Prihlasovacie meno/kód" />

                <label for="pin" class="block text-sm font-medium">PIN</label>
                <InputText id="pin" class="w-full" v-model="pin" type="password" placeholder="PIN" />

                <Button type="submit" :disabled="loading" class="w-full justify-center">Prihlásiť</Button>
                <div v-if="error" class="text-red-600">{{ error }}</div>
            </form>
        </div>
    </div>
</template>

<style scoped></style>
