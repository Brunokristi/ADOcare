<!-- src/App.vue -->
<script setup lang="ts">
import { onMounted, computed, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'

import Navbar from '@/components/Navbar.vue'
import Footer from '@/components/Footer.vue'
import Sidebar from '@/components/Sidebar.vue'
import TercialNavbar from './components/TercialNavbar.vue'
import AccessError from '@/pages/AccessError.vue'
import { navigationAccessError } from '@/router'

import { useAuthStore } from '@/stores/auth'
import { usePatientStore } from '@/stores/patientStore'
import ModalProvider from './components/ModalProvider.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const toast = useToast()
const patientStore = usePatientStore()
const { current: currentPatient } = storeToRefs(patientStore)
const deathCheckRequestId = ref(0)
const deathUpdateInProgress = ref(false)
const lastDeathToastKey = ref('')
const ROUTES_TOAST_GROUP = 'kilometers-routes-toast'


onMounted(() => {
    window.addEventListener('unauthenticated', () => {
        router.push({ name: 'login' })
    })
})

watch(
    currentPatient,
    async (patient) => {
        await auth.waitUntilInitialized()
        if (!auth.isAuthenticated || !patient || deathUpdateInProgress.value) {
            console.debug('[UDZS] Watcher: Not authenticated or no patient', { isAuthenticated: auth.isAuthenticated, patient });
            return;
        }

        const requestId = ++deathCheckRequestId.value;
        console.debug('[UDZS] Watcher: Checking patient death', { patientId: patient.id, requestId });

        try {
            const result = await patientStore.checkPatientDeath(patient.id);
            console.debug('[UDZS] Watcher: Death check result', { result, requestId, currentRequestId: deathCheckRequestId.value });

            if (requestId !== deathCheckRequestId.value || result.status !== 'dead') {
                console.debug('[UDZS] Watcher: Skipping toast', { requestId, currentRequestId: deathCheckRequestId.value, status: result.status });
                return;
            }

            const details = result.data ?? {};
            const fullName = [details.meno, details.priezvisko].filter(Boolean).join(' ').trim();
            const deathDate = typeof details.datumUmrtia === 'string'
                ? details.datumUmrtia.slice(0, 10)
                : null;

            const dateLabel = deathDate
                ? new Date(`${deathDate}T00:00:00`).toLocaleDateString('sk-SK')
                : '';
            const toastKey = `${patient.id}:${deathDate ?? 'unknown'}`;

            if (deathDate && currentPatient.value && currentPatient.value.death_date !== deathDate) {
                deathUpdateInProgress.value = true;
                try {
                    currentPatient.value.death_date = deathDate;
                    await patientStore.persistPatientData(currentPatient.value);
                } finally {
                    deathUpdateInProgress.value = false;
                }
            }

            if (lastDeathToastKey.value === toastKey) {
                console.debug('[UDZS] Watcher: Duplicate death toast prevented', { toastKey });
                return;
            }

            lastDeathToastKey.value = toastKey;

            const detailParts = [
                fullName ? `Pacient: ${fullName}` : null,
                dateLabel ? `Dátum úmrtia: ${dateLabel}` : null,
            ].filter(Boolean);

            console.debug('[UDZS] Watcher: Showing toast', { detailParts });
            toast.add({
                severity: 'warn',
                summary: 'Pacient je zosnulý',
                detail: detailParts.join(' \n ') || 'Pacient je evidovaný ako zosnulý.',
            });
        } catch (error) {
            console.error('[UDZS] Watcher: Failed to check patient death status', error);
        }
    },
    { immediate: true }
)

const isLoggedIn = computed(() => auth.isAuthenticated)
const showNavbar = computed(() => route.meta.shownavbar !== false)

const isSidebarOpen = ref(false)
function handleToggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value
}

function goToRoutesFromToast() {
    toast.removeGroup(ROUTES_TOAST_GROUP)
    router.push('/accounting/routes')
}


</script>

<template>
    <div class="h-screen flex flex-col bg-darkgrey">
        <Navbar v-if="showNavbar" class="flex-none" :isSidebarOpen="isSidebarOpen"
            @toggle-sidebar="handleToggleSidebar" />

        <TercialNavbar v-if="isLoggedIn && showNavbar" class="flex-none" />

        <div class="flex flex-1 overflow-hidden">
            <div class="flex-1 overflow-auto bg-white p-8 relative">
                <!-- show access error component inline when the guard has flagged it -->
                <AccessError v-if="navigationAccessError" />
                <router-view v-else />
            </div>

            <Sidebar v-if="isSidebarOpen && isLoggedIn"
                class="flex-none bg-darkgrey text-white border-l border-lightgrey" />
        </div>

        <Footer class="flex-none" />

        <Toast position="bottom-right" />
        
        <Toast :group="ROUTES_TOAST_GROUP" position="bottom-right">
            <template #message="slotProps">
                <div class="flex flex-col gap-2 w-full">
                    <div class="font-semibold">{{ slotProps.message.summary }}</div>
                    <div class="text-sm">{{ slotProps.message.detail }}</div>
                    <Button
                        label="Skontrolovať"
                        size="small"
                        class="!bg-white !border-0 !text-success hover:!bg-darkgrey !px-2 !w-auto self-start"
                        @click="goToRoutesFromToast"
                    />
                </div>
            </template>
        </Toast>

        <!-- existing global modals -->
        <ModalProvider />

        <!-- keep if you still use named modal routes anywhere else -->
        <router-view name="modal" />
    </div>
</template>
