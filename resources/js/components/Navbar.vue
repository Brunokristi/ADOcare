<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import Logo from '@/components/Logo.vue';
import AdoSearch from '@/components/ado/AdoSearch.vue';
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
                <span class="text-xs bg-white/10 px-2 py-0.5 rounded text-white">{{ auth.role || '—' }}</span>
                <span class="text-xs opacity-80">{{ auth.branch || '—' }}</span>
                <span class="font-semibold">{{ auth.company || '—' }}</span>
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
