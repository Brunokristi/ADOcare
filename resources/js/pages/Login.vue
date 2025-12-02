<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import auth from '@/services/auth';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const router = useRouter();
const route = useRoute();
const toast = useToast();

const login = ref('');
const pin = ref('');
const loading = ref(false);
const showPin = ref(false);
const submitted = ref(false);

function togglePinVisibility() {
    showPin.value = !showPin.value;
}

async function submit() {
    submitted.value = true;

    if (!login.value || !pin.value) return;

    loading.value = true;

    try {
        await auth.login({ login: login.value, pin: pin.value });

        const redirect = (route.query.redirect as string) || '/';
        router.push(redirect);
    } catch (e: any) {
        const message = "Nepodarilo sa prihlásiť. Skúste ešte raz."

        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: message,
            life: 3000
        });
    } finally {
        loading.value = false;
    }
}
</script>


<template>
    <div class="h-full flex items-center justify-center">
        <div class="w-full max-w-md min-w-100 bg-white">
            <h1 class="text-heading-accent text-center mb-15">
                Vitajte 👋<br>Prihláste sa do svojho účtu.
            </h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div>
                    <label for="login" class="block text-normal mb-1">Prihlasovací kód</label>
                    <InputText
                        id="login"
                        class="w-full"
                        v-model="login"
                    />
                    <small
                        v-if="submitted && !login"
                        class="text-warning"
                    >
                        Prihlasovací kód je povinný.
                    </small>
                </div>

                <div>
                    <label for="pin" class="block text-normal mb-1">PIN</label>

                    <IconField class="flex items-center w-full">
                        <InputText
                            id="pin"
                            v-model="pin"
                            :type="showPin ? 'text' : 'password'"
                            class="w-full"
                        />

                        <InputIcon>
                            <i
                                :class="showPin ? 'bi bi-eye' : 'bi bi-eye-slash'"
                                class="cursor-pointer"
                                @click="togglePinVisibility"
                            />
                        </InputIcon>
                    </IconField>

                    <small
                        v-if="submitted && !pin"
                        class="text-warning"
                    >
                        PIN je povinný.
                    </small>
                </div>

                <Button
                    type="submit"
                    :disabled="loading"
                    class="relative w-full flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey"
                >
                    Prihlásiť sa
                    <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
                </Button>
            </form>
        </div>
    </div>
</template>

<style scoped></style>
