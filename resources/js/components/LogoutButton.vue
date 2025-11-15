<script setup lang="ts">
import authService from '@/services/auth';
import { useRouter } from 'vue-router';
import { ref } from 'vue';
import { ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const loading = ref(false);

async function doLogout() {
    loading.value = true;
    try {
        await authService.logout();
        router.push({ name: 'login' });
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <button @click="doLogout" class="text-white px-2 py-1 rounded hover:bg-white/10 flex items-center space-x-2">
        <ArrowRightOnRectangleIcon class="h-5 w-5" />
        <span>Logout</span>
    </button>
</template>

<style scoped></style>
