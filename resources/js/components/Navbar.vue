<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import Logo from '@/components/Logo.vue';
import AdoSearch from '@/components/ado/AdoSearch.vue';
import AdoSelect from '@/components/ado/AdoSelect.vue';
import MenuDropdown from '@/components/MenuDropdown.vue';
import LogoutButton from '@/components/LogoutButton.vue';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const isAuthenticated = computed(() => auth.isAuthenticated);

function onSearch(q: string) {
    if (isAuthenticated.value) {
        router.push({ name: 'patients', query: { q } });
    } else {
        router.push({ name: 'login', query: { redirect: '/' } });
    }
}
</script>

<template>
    <nav class="p-3 flex items-center justify-between" style="background:#333333">
        <div class="flex items-center space-x-4">
            <Logo />
            <div class="hidden sm:block">
                <AdoSearch @search="onSearch" />
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <div class="text-sm text-white flex flex-row items-center space-x-3">
                <AdoSelect v-if="(auth.user?.roles_list.length ?? 0) > 1" v-model="auth.currentRole"
                    :options="auth.user?.roles_list || []" size="sm" class="bg-white/10 text-white"
                    @change="auth.setCurrentRole" />
                <span v-else class="text-xs bg-white/10 px-2 py-0.5 rounded text-white">{{ auth?.currentRole ||
                    '—' }}</span>
                <AdoSelect v-if="(auth.user?.branches?.length ?? 0) > 1" v-model="auth.currentBranch!.id"
                    :options="auth.user?.branches.map(b => ({ label: b.address, value: b.id })) || []" size="sm"
                    class="bg-white/10 text-white" @change="auth.setCurrentBranch" />
                <span v-else class="text-xs opacity-80">{{ auth?.currentBranch?.code || '—' }}</span>
                <span class="font-semibold">{{ auth.user?.company.name || '—' }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <MenuDropdown>
                    <a href="/settings" class="block px-4 py-2">Settings</a>
                    <a href="/help" class="block px-4 py-2">Help</a>
                </MenuDropdown>
                <LogoutButton v-if="isAuthenticated" />
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
