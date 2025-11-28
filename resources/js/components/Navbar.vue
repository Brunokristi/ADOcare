<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Menu from 'primevue/menu';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';
import type { Patient } from '@/types/models';

const router = useRouter();
const auth = useAuthStore();

const emit = defineEmits<{
    (e: 'toggle-sidebar'): void;
}>();


const selectedBranchId = computed({
    get: () => auth.currentBranch?.id ?? null,
    set: (v: number | null) => {
        if (v != null) auth.setCurrentBranch(v);
    },
});

const branches = computed(() => auth.branches ?? []);
const isAuthenticated = computed(() => auth.isAuthenticated);


const patients = ref<{ label: string; command: () => void }[]>([]);
const searchQuery = ref('');

let debounceTimer: number | undefined;

async function fetchPatients(query: string) {
    if (!query) {
        patients.value = [];
        return;
    }

    try {
        const res = await api.get('/v1/patients', {
            params: { paginate: true, q: query },
        });

        const data = res.data?.data as IPaginatedIndexSuccessResponse<Patient>;
        const items = data?.items ?? [];

        patients.value = items.map((p: Patient) => ({
            label: `${p.first_name} ${p.last_name} (ID: ${p.id})`,
            command: () => router.push(`/patients/${p.id}`),
        }));
    } catch (error) {
        console.error('Error fetching patients:', error);
    }
}

watch(searchQuery, (value) => {
    if (debounceTimer) window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => fetchPatients(value), 300);
});


function goBack() {
    router.back();
}

function goHome() {
    router.push('/');
}

async function logout() {
    try {
        await auth.logout();
    } catch (e) {
        console.error('Logout failed', e);
    } finally {
        router.push('/login');
    }
}

function toggleSidebar() {
    emit('toggle-sidebar');
}
</script>

<template>
    <nav class="px-3 py-2 flex items-center justify-between bg-darkgrey text-white">
        <RouterLink
            to="/"
            class="h-10 flex items-center space-x-2 text-accent text-heading-accent"
        >
            <i class="bi bi-flower2 text-xl"></i>
        </RouterLink>

        <div class="flex items-center gap-2 text-normal">
            <!-- Back -->
            <Button
                icon="bi bi-arrow-left"
                text
                class="!h-7 !w-7 !min-h-0 !px-2 !rounded-md !bg-white !text-darkgrey flex items-center justify-center"
                @click="goBack"
            />

            <!-- Home -->
            <Button
                icon="bi bi-circle text-xs"
                text
                class="!h-7 !w-7 !min-h-0 !px-2 !rounded-md !bg-white !text-darkgrey flex items-center justify-center"
                @click="goHome"
            />

            <div class="relative h-7 flex items-center w-48"> 
                <IconField class="h-full flex items-center w-full">
                    <InputText
                        v-model="searchQuery"
                        class="!h-7 !bg-tag2 !text-white !border-none rounded-md pl-8 pr-2 w-full"
                    />
                    <InputIcon>
                        <i class="bi bi-search text-lightgrey" />
                    </InputIcon>
                </IconField>

                <Menu
                    v-if="patients.length"
                    :model="patients"
                    class="absolute left-0 top-7 w-full bg-white text-darkgrey rounded-md shadow-lg z-20"
                />
            </div>

            <!-- User -->
            <span
                class="h-7 flex items-center rounded-md bg-tag2 text-white px-3 text-sm"
            >
                Erika Kaszová
            </span>

            <!-- Branch -->
            <Dropdown
                v-model="selectedBranchId"
                :options="branches"
                optionLabel="name"
                optionValue="id"
                class="w-40 !h-7 flex items-center !bg-tag2 !text-lightgrey !border-none rounded-md px-2 text-sm"
            />


            <!-- Company -->
            <span
                class="h-7 flex items-center rounded-md bg-tag2 text-white px-3 text-sm"
            >
                ADOS ADANED s.r.o.
            </span>

            <!-- Logout -->
            <Button
                v-if="isAuthenticated"
                icon="bi bi-box-arrow-right"
                text
                class="!h-7 !w-7 !min-h-0 !px-2 !rounded-md !bg-white !text-darkgrey flex items-center justify-center"
                @click="logout"
            />

            <!-- Sidebar toggle -->
            <Button
                icon="bi bi-grid-3x3-gap"
                text
                class="!h-7 !w-7 !min-h-0 !px-2 !rounded-md !bg-white !text-darkgrey flex items-center justify-center"
                @click="toggleSidebar"
            />
        </div>
    </nav>
</template>


