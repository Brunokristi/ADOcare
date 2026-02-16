<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { usePatientStore } from '@/stores/patientStore'
import type { User } from '@/types/models'
import usePatients from '@/composables/usePatients'
import useBranches from '@/composables/useBranches'

const router = useRouter()
const authStore = useAuthStore()
const patientStore = usePatientStore()

const emit = defineEmits<{
    (e: 'toggle-sidebar'): void
}>()

const props = defineProps<{
    isSidebarOpen?: boolean
}>()

/* ------------ BASIC AUTH / USER ------------ */

const isAuthenticated = computed(() => authStore.isAuthenticated)
const user = computed<User | null>(() => authStore.user as User | null)

const companyName = computed(() => user.value?.company?.name ?? '')
const fullName = computed(() =>
    user.value ? `${user.value.title ?? ''} ${user.value.first_name ?? ''} ${user.value.last_name ?? ''}`.trim() : ''
)

/* ------------ BRANCH SELECT OPTIONS ------------ */

const { branchOptions, selectedBranchId, applyBranchSelection } = useBranches()

/* ------------ PATIENT SELECT ------------ */

const {
    patientOptions,
    selectedPatient,
    patientsLoading,
    patientFilterString,
    loadPatients,
    onLazyLoadPatients,
    onFilterPatients,
    setBranchId,
} = usePatients()


/* when patient is selected from navbar → save & go to detail page */
watch(selectedPatient, (opt) => {
    if (!opt) return
    patientStore.setPatient(opt.raw)
    router.push('/patient/points')
    selectedPatient.value = null
})

/* ------------ NAVIGATION / ACTIONS ------------ */

async function logout() {
    try {
        await authStore.clearAuth()
    } catch (e) {
        console.error('Logout failed', e)
    } finally {
        router.push('/login')
    }
}

function toggleSidebar() {
    emit('toggle-sidebar')
}

/* ------------ LIFECYCLE ------------ */

onMounted(async () => {
    await authStore.waitUntilInitialized()

    // Handle role-specific initialization
    if (authStore.isManager) {
        // Manager: clear patient data and set manager branch
        patientStore.clear()
        selectedBranchId.value = -1
        await applyBranchSelection(-1)
    } else {
        // Nurse: load patient data for current branch
        patientStore.loadFromStorage()
        selectedBranchId.value = authStore.currentBranch?.id ?? null
        setBranchId(authStore.currentBranch?.id ?? null)
        await loadPatients()
    }
})

/* reload patients when branch changes */
watch(
    () => authStore.currentBranch?.id,
    () => {
        if (!authStore.isManager) {
            setBranchId(authStore.currentBranch?.id ?? null)
            loadPatients()
            selectedBranchId.value = authStore.currentBranch?.id ?? null
        }
    }
)

/* handle role changes (nurse ↔ manager) */
watch(
    () => authStore.currentRole,
    async (newRole, oldRole) => {
        // Only trigger on actual role change, not on initial load
        if (!oldRole) return

        if (newRole === 'manager') {
            patientStore.clear()
            selectedBranchId.value = -1
            await applyBranchSelection(-1)
            await router.dashboard()
        } else if (oldRole === 'manager') {
            selectedBranchId.value = authStore.currentBranch?.id ?? null
            setBranchId(authStore.currentBranch?.id ?? null)
            await loadPatients()
            await router.dashboard()
        }
    }
)
</script>

<template>
    <nav class="px-3 py-2 flex items-center justify-end bg-darkgrey text-lightgrey min-h-10"
        :key="authStore.currentRole ?? 'default'">
        <div v-if="isAuthenticated" class="flex items-center gap-2 text-normal">
            <Button icon="bi bi-arrow-left" text
                class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
                @click="router.back" />

            <Button icon="bi bi-circle text-xs" text
                class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
                @click="router.dashboard" />

            <!-- PATIENT SELECT -->
            <Select v-if="!authStore.isManager" v-model="selectedPatient" :options="patientOptions" optionLabel="name"
                :virtualScrollerOptions="{ lazy: true, onLazyLoad: onLazyLoadPatients, appendOnly: true, itemSize: 38, showLoader: false, loading: patientsLoading, delay: 10 }"
                :placeholder="patientsLoading ? 'Načítavam pacientov...' : 'Vyberte pacienta'"
                dropdownIcon="bi bi-chevron-down !text-white" class="w-60 h-7! flex items-center border-none! bg-tag2!">
                <template #value>
                    <span class="text-normal text-white">Vyberte pacienta</span>
                </template>

                <template #header>
                    <!-- Filter -->
                    <div class="p-2">
                        <IconField class="flex items-center gap-2">
                            <InputText v-model="patientFilterString" placeholder="Filtrovať pacientov..." class="w-full"
                                @input="onFilterPatients" aria-label="Filtrovať pacientov" />
                            <InputIcon @click="patientFilterString = ''; onFilterPatients()" class="cursor-pointer">
                                <i v-if="patientFilterString" class="bi bi-x-lg text-darkgrey text-sm" />
                                <i v-else class="bi bi-search text-darkgrey" />
                            </InputIcon>
                        </IconField>
                    </div>
                </template>

                <template #option="{ option }">
                    <div class="flex">
                        <span class="text-normal text-darkgrey pr-2">{{ option.name }}</span>
                        <span class="bg-darkgrey rounded-md text-mini text-white px-2 content-center">
                            {{ option.personalNumber }}
                        </span>
                    </div>
                </template>

                <template #empty>
                    <div class="flex items-center gap-2 px-2 py-1 text-normal text-darkgrey">
                        <i v-if="patientsLoading" class="bi bi-arrow-repeat animate-spin" />
                        <span>{{ patientsLoading ? 'Načítavam pacientov...' : 'Pacienti neboli nájdení' }}</span>
                    </div>
                </template>
            </Select>

            <!-- User name -->
            <span v-if="user"
                class="h-7 flex items-center rounded-md bg-tag2 text-lightgrey px-3 text-normal whitespace-pre-line">
                {{ fullName }}
            </span>

            <!-- Branch select (only for non-managers) -->
            <Select @change="(e) => applyBranchSelection(e.value)" :options="branchOptions" optionLabel="label"
                optionValue="id" placeholder="Vyberte pobočku" labelClass="text-white!"
                dropdownIcon="bi bi-chevron-down text-white!" :key="authStore.currentBranch?.id ?? ''"
                v-model="selectedBranchId" class="w-60 h-7! flex items-center bg-tag2! border-none!" />

            <!-- Company name -->
            <span v-if="companyName" class="h-7 flex items-center rounded-md bg-tag2 text-lightgrey px-3 text-normal">
                {{ companyName }}
            </span>

            <Button icon="bi bi-box-arrow-right" text
                class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
                @click="logout" />

            <Button :icon="props.isSidebarOpen ? 'bi bi-x-lg' : 'bi bi-list'" text
                class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
                @click="toggleSidebar" />
        </div>
    </nav>
</template>
