<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { usePatientStore } from '@/stores/patientStore'
import api from '@/services/api'
import type { Patient, User } from '@/types/models'
import { useToast } from 'primevue/usetoast'
import type { VirtualScrollerLazyEvent } from 'primevue/virtualscroller'
import { formatBranchFullName } from '@/utils/formatUtils'

const router = useRouter()
const authStore = useAuthStore()
const patientStore = usePatientStore()
const toast = useToast();

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

const selectedBranchId = ref<number | null>(null)
type BranchOption = { id: number; label: string; isManager: boolean }

const branchOptions = computed<BranchOption[]>(() => {
    const userInfo = user.value
    if (!userInfo) return []

    const options: BranchOption[] = (userInfo.branches ?? []).map((branch: any) => ({
        id: branch.id,
        label: formatBranchFullName(branch),
        isManager: false,
    }))

    const userRoles: string[] = userInfo.role_names ?? []
    const hasManager = userRoles.some((role) => role && role.trim().toLowerCase() === 'manager')

    if (hasManager) {
        options.push({ id: -1, label: 'Manažér', isManager: true })
    }

    return options
})

/* ------------ Branch application logic ------------ */

async function applyBranchSelection(id: number) {
    const opt = branchOptions.value.find((o) => o.id === id)
    if (!opt) return
    patientStore.clear()

    if (opt.isManager) {
        authStore.setCurrentRole('manager')
        authStore.clearCurrentBranch();
        selectedBranchId.value = -1
        return
    }

    authStore.setCurrentRole('nurse')
    authStore.setCurrentBranchById(id)
    selectedBranchId.value = id;

}

/* ------------ PATIENT SELECT ------------ */

type PatientOption = {
    id: number
    name: string
    personalNumber: string
    raw: Patient
}

const patientOptions = ref<PatientOption[]>([])
const selectedPatient = ref<PatientOption | null>(null)
const patientsLoading = ref(false)
const branchId = computed(() => authStore.currentBranch?.id ?? null)
const companyId = computed(() => authStore.user?.company?.id ?? null)
const currentRole = computed(() => authStore.currentRole ?? null)
const fetchPatientsURL = computed(() => {
  console.log('Computed fetchPatientsURL for branchId', branchId.value, companyId.value)

  if (currentRole.value === 'manager') {
    return companyId.value ? `/v1/companies/${companyId.value}/patients` : ''
  }

  return branchId.value ? `/v1/branches/${branchId.value}/patients` : ''
})
async function fetchPatients(page: number) {
    try {
        console.log(isAuthenticated.value, fetchPatientsURL.value)
        if (!isAuthenticated.value || !fetchPatientsURL.value) throw new Error('Niečo zlýhalo pri načítaní pacientov. Error 001.')
        const res = await api.fetchEntitiesPaginated<Patient>(fetchPatientsURL.value, {
            per_page: 20,
            page: page,
            q: patientFilterString.value.trim() || undefined,
        })
        const items = res.items || []
        return items;
    } catch (e) {
        console.error('Failed to load patients', e)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa načítať pacientov.' })
    }

    return [];
}

function transformPatientsToPatientOptions(items: Patient[]): PatientOption[] {
    return items.map((p) => ({
        id: p.id,
        name: `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim(),
        personalNumber: p.personal_number ?? '',
        raw: p,
    }))
}

async function loadPatients() {
    patientOptions.value = []
    if (!isAuthenticated.value) return
    patientsLoading.value = true
    const items = await fetchPatients(1)
    patientOptions.value = transformPatientsToPatientOptions(items)
    patientsLoading.value = false
}

let lastLoadedPage = 1;
const patientFilterString = ref('');

async function onLazyLoadPatients(event: VirtualScrollerLazyEvent) {

    const page = Math.floor(event.last / 20 + 1)
    console.log('Lazy load patients', event, page)
    if (page <= lastLoadedPage) {
        patientsLoading.value = false
        return
    }
    lastLoadedPage = page
    patientsLoading.value = true
    const items = await fetchPatients(page)
    const newOptions = transformPatientsToPatientOptions(items)
    patientOptions.value = (page === 1) ? newOptions : [...patientOptions.value, ...newOptions]
    patientsLoading.value = false
}

async function onFilterPatients() {
    lastLoadedPage = 1
    loadPatients()
}


/* when patient is selected from navbar → save & go to detail page */
watch(selectedPatient, (opt) => {
    if (!opt) return
    patientStore.setPatient(opt.raw)
    router.push('/patient/points')
    selectedPatient.value = null
})

/* ------------ NAVIGATION / ACTIONS ------------ */

const goBack = () => router.back()
const goHome = () => router.push('/')

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
    patientStore.loadFromStorage()
    loadPatients()
    await authStore.waitUntilInitialized()
    if (authStore.isManager)
        selectedBranchId.value = -1
    else
        selectedBranchId.value = authStore.currentBranch?.id ?? null;

    console.log('Navbar mounted, selectedBranchId:', selectedBranchId.value);


})

/* reload patients when branch changes */
watch(
    () => authStore.currentBranch?.id,
    () => {
        loadPatients();
        selectedBranchId.value = authStore.currentBranch?.id ?? (authStore.isManager ? -1 : null)
    }
)

watch(
    () => [authStore.currentBranch?.id, authStore.currentRole] as const,
    ([newBranch, newRole], [oldBranch, oldRole]) => {
        if (!oldBranch && !oldRole) return
        if (newBranch !== oldBranch || newRole !== oldRole) {
            patientStore.clear()
            router.push('/')
        }
    }
)
</script>

<template>
    <nav class="px-3 py-2 flex items-center justify-end bg-darkgrey text-lightgrey min-h-10">
        <div v-if="isAuthenticated" class="flex items-center gap-2 text-normal">
            <Button icon="bi bi-arrow-left" text
                class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
                @click="goBack" />

            <Button icon="bi bi-circle text-xs" text
                class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
                @click="goHome" />

            <!-- PATIENT SELECT -->
            <Select v-model="selectedPatient" :options="patientOptions" optionLabel="name"
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

            <!-- Branch select -->
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
