<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRouter} from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { usePatientStore } from '@/stores/patientStore'
import api from '@/services/api'
import type { Patient, User } from '@/types/models'

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
  user.value ? `${user.value.first_name ?? ''} ${user.value.last_name ?? ''}`.trim() : ''
)

/* ------------ BRANCH SELECT OPTIONS ------------ */

type BranchOption = { id: number; label: string; isManager: boolean }

const branchOptions = computed<BranchOption[]>(() => {
  const u = user.value
  if (!u) return []

  const options: BranchOption[] = (u.branches ?? []).map((b: any) => ({
    id: b.id,
    label: b.address + ", " + b.city || b.identificator || b.city || '',
    isManager: false,
  }))

  const roleNames: string[] = (u as any).roles_list ?? []
  const hasManager = roleNames.some((r) => r && r.trim().toLowerCase() === 'manager')

  if (hasManager) {
    options.push({ id: 999999, label: 'Manažér', isManager: true })
  }

  return options
})

/**
 * Local v-model for the Branch <Select>.
 * We keep this in sync with authStore.currentBranch/currentRole.
 */
const selectedBranchIdLocal = ref<number | null>(null)

/* ------------ Persistence to DB ------------ */

/**
 * Update user->last_branch in DB.
 * Change this endpoint to whatever you actually have.
 *
 * Examples you might have:
 * - PATCH /v1/users/me  { last_branch_id }
 * - PATCH /v1/me        { last_branch_id }
 * - PATCH /v1/profile   { last_branch_id }
 */
async function saveLastBranchToDb(branchId: number) {
  try {
    await api.patch('/v1/users/me/last-branch', { last_branch_id: branchId })
  } catch (e) {
    console.error('Failed to save last_branch_id', e)
  }
}

/* ------------ Branch application logic ------------ */

function getUserLastBranchId(u: any): number | null {
  // support different shapes:
  return (
    (u?.last_branch_id ?? null) ||
    (u?.last_branch?.id ?? null) ||
    null
  )
}

function userHasBranch(u: any, branchId: number) {
  return (u?.branches ?? []).some((b: any) => Number(b.id) === Number(branchId))
}

async function applyBranchSelection(id: number) {
  const opt = branchOptions.value.find((o) => o.id === id)
  if (!opt) return

  if (opt.isManager) {
    authStore.setCurrentRole('manager')
    authStore.clearCurrentBranch()
    patientStore.clear()
    return
  }

  authStore.setCurrentRole('nurse')
  authStore.setCurrentBranch(id)
  patientStore.clear()

  await saveLastBranchToDb(id)
}

/**
 * Initialize branch on login:
 * - If user has last_branch_id and it’s allowed -> use it.
 * - Else pick first available branch (non-manager) and save it.
 * - If user has no branches -> keep null.
 */
async function ensureBranchInitialized() {
  const u: any = user.value
  if (!isAuthenticated.value || !u) return

  // If already in manager role, don't force a branch
  if (authStore.currentRole === 'manager') {
    selectedBranchIdLocal.value = 999999
    return
  }

  // If store already has a branch, just reflect it
  const current = authStore.currentBranch?.id ?? null
  if (current) {
    selectedBranchIdLocal.value = current
    return
  }

  const last = getUserLastBranchId(u)
  if (last && userHasBranch(u, last)) {
    selectedBranchIdLocal.value = last
    await applyBranchSelection(last)
    return
  }

  // pick first real branch option
  const firstBranch = branchOptions.value.find((o) => !o.isManager)
  if (firstBranch) {
    selectedBranchIdLocal.value = firstBranch.id
    await applyBranchSelection(firstBranch.id)
    return
  }

  selectedBranchIdLocal.value = null
}

/* Keep local select synced when store changes externally */
watch(
  () => [authStore.currentBranch?.id, authStore.currentRole] as const,
  ([branchId, role]) => {
    if (role === 'manager') {
      selectedBranchIdLocal.value = 999999
    } else {
      selectedBranchIdLocal.value = branchId ?? null
    }
  },
  { immediate: true }
)

/* When user/options load, initialize branch if needed */
watch(
  () => [isAuthenticated.value, user.value, branchOptions.value.length] as const,
  async () => {
    await ensureBranchInitialized()
  },
  { immediate: true }
)

/* When user picks a branch in UI, apply + persist */
watch(
  selectedBranchIdLocal,
  async (id, oldId) => {
    if (id == null) return
    if (id === oldId) return

    // If user clicks current value, ignore
    if (authStore.currentRole === 'manager' && id === 999999) return
    if (authStore.currentRole !== 'manager' && id === authStore.currentBranch?.id) return

    await applyBranchSelection(id)
  }
)

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

function mapPatients(items: Patient[]): PatientOption[] {
  return items.map((p) => ({
    id: p.id,
    name: `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim(),
    personalNumber: p.personal_number ?? '',
    raw: p,
  }))
}

async function loadAllPatients() {
  if (!isAuthenticated.value) {
    patientOptions.value = []
    return
  }

  const branchId = authStore.currentBranch?.id ?? null

  try {
    patientsLoading.value = true

    const res = await api.get(`/v1/branches/${branchId}/patients`, {
      params: {
        paginate: 0
      },
    })

    const data = res.data?.data
    const items = ((Array.isArray(data) ? data : data?.items) as Patient[]) ?? []

    patientOptions.value = mapPatients(items)
  } catch (e) {
    console.error('Failed to load patients', e)
    patientOptions.value = []
  } finally {
    patientsLoading.value = false
  }
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

onMounted(() => {
  patientStore.loadFromStorage()
  loadAllPatients()
})

/* reload patients when branch changes */
watch(
  () => authStore.currentBranch?.id,
  () => {
    loadAllPatients()
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
      <Button
        icon="bi bi-arrow-left"
        text
        class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
        @click="goBack"
      />

      <Button
        icon="bi bi-circle text-xs"
        text
        class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
        @click="goHome"
      />

      <!-- PATIENT SELECT -->
      <Select
        v-model="selectedPatient"
        :options="patientOptions"
        optionLabel="name"
        filter
        :loading="patientsLoading"
        :placeholder="patientsLoading ? 'Načítavam pacientov...' : 'Vyberte pacienta'"
        dropdownIcon="bi bi-chevron-down !text-white"
        class="w-60 h-7! flex items-center bg-tag2! border-none!"
      >
        <template #value>
          <span class="text-normal text-white">Vyberte pacienta</span>
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
      <span
        v-if="user"
        class="h-7 flex items-center rounded-md bg-tag2 text-lightgrey px-3 text-normal whitespace-pre-line"
      >
        {{ fullName }}
      </span>

      <!-- Branch select -->
      <Select
        v-model="selectedBranchIdLocal"
        :options="branchOptions"
        optionLabel="label"
        optionValue="id"
        placeholder="Vyberte pobočku"
        labelClass="text-white!"
        dropdownIcon="bi bi-chevron-down text-white!"
        class="w-60 h-7! flex items-center bg-tag2! border-none!"
      />

      <!-- Company name -->
      <span
        v-if="companyName"
        class="h-7 flex items-center rounded-md bg-tag2 text-lightgrey px-3 text-normal"
      >
        {{ companyName }}
      </span>

      <Button
        icon="bi bi-box-arrow-right"
        text
        class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
        @click="logout"
      />

      <Button
        :icon="props.isSidebarOpen ? 'bi bi-x-lg' : 'bi bi-list'"
        text
        class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
        @click="toggleSidebar"
      />
    </div>
  </nav>
</template>
