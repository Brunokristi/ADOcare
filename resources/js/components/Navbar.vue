<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Logo from '@/components/Logo.vue';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import { useAuthStore } from '@/stores/auth';
import Menu from 'primevue/menu';
import api from '@/services/api';
import type { Patient } from '@/types/models';

const router = useRouter();
const auth = useAuthStore();

const selectedBranchId = computed({
    get: () => auth.currentBranch?.id ?? null,
    set: (v: number | null) => {
        if (v != null) auth.setCurrentBranch(v);
    },
});

const isAuthenticated = computed(() => auth.isAuthenticated);

const menuItems = [
    { label: 'Settings', command: () => router.push('/settings') },
    { label: 'Help', command: () => router.push('/help') },
];
if (isAuthenticated.value) menuItems.push({ label: 'Logout', command: () => router.push('/logout') });



const patients = ref([] as any[]);

// Watch for search input changes
let searchQuery = ref('');
let debounceTimer = ref(0);
const patientsSearchMenuEl = ref<any>(null);

async function searchPatients(e: Event) {
    if (debounceTimer.value) clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(async () => {
        let data: IPaginatedIndexSuccessResponse<Patient> = {} as any;
        const query = (e.target as HTMLInputElement).value;
        try {
            const res = await api.get('/v1/patients', { params: { paginate: true, q: query } });
            data = res.data?.data;
        } catch (error) {
            console.error('Error fetching patients:', error);
            return;
        }

        const items = data.items;
        patients.value = items.map(p => ({
            label: `${p.first_name} ${p.last_name} (ID: ${p.id})`,
            command: () => {
                router.push(`/patients/${p.id}`);
            },
        }));
        patientsSearchMenuEl.value.show(e, e.target);


    }, 300); // 300ms debounce
};




</script>

<template>
    <nav class="p-3 flex items-center justify-between" style="background:#333333">
        <div class="flex items-center space-x-4">
            <Logo />
        </div>

        <div class="flex items-center space-x-4">
            <div class="hidden sm:block">
                <InputText ref="patientsSearchInputEl" placeholder="Search…" class="bg-white/10 text-white"
                    @input="searchPatients" />
                <Menu ref="patientsSearchMenuEl" :model="patients" :popup="true" />
            </div>
            <div class="text-sm text-white flex flex-row items-center space-x-3">
                <Dropdown v-if="(auth.user?.roles_list.length ?? 0) > 1" v-model="auth.currentRole"
                    :options="(auth.user?.roles_list || []).map(r => ({ label: r, value: r }))" optionLabel="label"
                    optionValue="value" class="bg-white/10 text-white text-xs rounded"
                    @change="() => auth.currentRole && auth.setCurrentRole(auth.currentRole)" />
                <span v-else class="text-xs bg-white/10 rounded text-white">{{ auth?.currentRole ||
                    '—' }}</span>
                <Dropdown v-if="(auth.user?.branches?.length ?? 0) > 1" v-model="selectedBranchId"
                    :options="(auth.user?.branches || []).map(b => ({ label: b.address, value: b.id }))"
                    optionLabel="label" optionValue="value" class="bg-white/10 text-white text-xs rounded" />
                <span v-else class="text-xs opacity-80">{{ auth?.currentBranch?.code || '—' }}</span>
                <span class="font-semibold">{{ auth.user?.company.name || '—' }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <template v-if="isAuthenticated">
                    <Button type="button" severity="secondary" class="text-black!" icon="pi pi-bars"
                        @click="($refs as any).menu.toggle($event)" />
                    <Menu ref="menu" :model="menuItems" :popup="true" />
                </template>
                <router-link v-else to="/login" class="text-sm text-white">Login</router-link>
            </div>
        </div>
    </nav>
</template>

<style scoped>
/* small adjustments */
.company {
    color: #ffffff;
}
</style>
