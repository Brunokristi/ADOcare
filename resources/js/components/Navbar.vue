<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import { usePatientStore } from '@/stores/patientStore';
import api from '@/services/api';
import type { Patient, User } from '@/types/models';

const router = useRouter();
const authStore = useAuthStore();
const patientStore = usePatientStore();

const emit = defineEmits<{
  (e: 'toggle-sidebar'): void;
}>();

/* ------------ BASIC AUTH / USER ------------ */

const isAuthenticated = computed(() => authStore.isAuthenticated);
const user = computed<User | null>(() => authStore.user as User | null);

const companyName = computed(() => user.value?.company?.name ?? '');
const fullName = computed(() =>
  user.value
    ? `${user.value.first_name ?? ''} ${user.value.last_name ?? ''}`.trim()
    : ''
);

/* ------------ BRANCH SELECT ------------ */

const branchOptions = computed(() => {
  const u = user.value;
  if (!u) return [];

  const options = (u.branches ?? []).map(b => ({
    id: b.id,
    label: b.address || b.identificator || b.city || '',
    isManager: false,
  }));

  const roleNames = (u as any).roles_list ?? [];
  const hasManager = roleNames.some(
    (r: string) => r && r.trim().toLowerCase() === 'manager'
  );

  if (hasManager) {
    options.push({
      id: 999999,
      label: 'Manažér',
      isManager: true,
    });
  }

  return options;
});

const selectedBranchId = computed<number | null>({
  get: () => {
    if (authStore.currentRole === 'manager') {
      const managerOpt = branchOptions.value.find(o => o.isManager);
      return managerOpt ? managerOpt.id : authStore.currentBranch?.id ?? null;
    }
    return authStore.currentBranch?.id ?? null;
  },
  set: (id) => {
    if (id == null) return;

    const numericId = typeof id === 'string' ? Number(id) : id;
    const opt = branchOptions.value.find(o => o.id === numericId);

    if (!opt) return;

    if (opt.isManager) {
      authStore.setCurrentRole('manager');
      authStore.clearCurrentBranch();
      patientStore.clear();
    } else {
      authStore.setCurrentBranch(numericId);
      authStore.setCurrentRole('nurse');
      patientStore.clear();
    }
  },
});

/* ------------ PATIENT SELECT ------------ */

type PatientOption = {
  id: number;
  name: string;
  personalNumber: string;
  raw: Patient;
};

const patientOptions = ref<PatientOption[]>([]);
const selectedPatient = ref<PatientOption | null>(null);
const patientsLoading = ref(false);

function mapPatients(items: Patient[]): PatientOption[] {
  return items.map(p => ({
    id: p.id,
    name: `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim(),
    personalNumber: p.personal_number ?? '',
    raw: p,
  }));
}

async function loadAllPatients() {
  if (!isAuthenticated.value) {
    patientOptions.value = [];
    return;
  }

  const branchId = authStore.currentBranch?.id ?? null;

  try {
    patientsLoading.value = true;

    const res = await api.get('/v1/patients', {
      params: {
        paginate: 0,
        ...(branchId ? { branch_id: branchId } : {}),
      },
    });

    const data = res.data?.data;
    const items =
      (Array.isArray(data) ? data : data?.items) as Patient[] ?? [];

    patientOptions.value = mapPatients(items);
  } catch (e) {
    console.error('Failed to load patients', e);
    patientOptions.value = [];
  } finally {
    patientsLoading.value = false;
  }
}

/* ------------ NAVIGATION / ACTIONS ------------ */

const goBack = () => router.back();
const goHome = () => router.push('/');

async function logout() {
  try {
    await authStore.clearAuth();
  } catch (e) {
    console.error('Logout failed', e);
  } finally {
    router.push('/login');
  }
}

function toggleSidebar() {
  emit('toggle-sidebar');
}

/* when patient is selected from navbar → save & go to detail page */
watch(selectedPatient, (opt) => {
  if (!opt) return;
  patientStore.setPatient(opt.raw);
  router.push('/patient/points');
  selectedPatient.value = null;
});

/* ------------ LIFECYCLE ------------ */

onMounted(() => {
  patientStore.loadFromStorage();
  loadAllPatients();
});

/* reload patients when branch changes */
watch(
  () => authStore.currentBranch?.id,
  () => {
    loadAllPatients();
  }
);

watch(
  () => [authStore.currentBranch?.id, authStore.currentRole],
  ([newBranch, newRole], [oldBranch, oldRole]) => {
    if (newBranch !== oldBranch || newRole !== oldRole) {
      patientStore.clear();
      router.push('/');
    }
  }
);

</script>

<template>
  <nav class="px-3 py-2 flex items-center justify-between bg-darkgrey text-lightgrey">
    <RouterLink
      to="/"
      class="h-10 flex items-center space-x-2 text-accent text-heading-accent"
    >
      <i class="bi bi-flower2 text-xl"></i>
    </RouterLink>

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
          <span class="text-normal text-white">
            Vyberte pacienta
          </span>
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
            <span>
              {{ patientsLoading ? 'Načítavam pacientov...' : 'Pacienti neboli nájdení' }}
            </span>
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
        v-model="selectedBranchId"
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

      <!-- Logout -->
      <Button
        icon="bi bi-box-arrow-right"
        text
        class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
        @click="logout"
      />

      <!-- Sidebar toggle -->
      <Button
        icon="bi bi-grid-3x3-gap"
        text
        class="h-7! w-7! min-h-0! px-2! rounded-md! bg-white! text-darkgrey! flex items-center justify-center"
        @click="toggleSidebar"
      />
    </div>
  </nav>
</template>
