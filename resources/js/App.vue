<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Navbar from '@/components/Navbar.vue';

// listen for global unauthenticated events from axios and redirect to login
const router = useRouter();
const route = useRoute();
onMounted(() => {
    window.addEventListener('unauthenticated', () => {
        router.push({ name: 'login' });
    });
});

const showNavbar = computed(() => {
    // hide navbar on routes that declare `meta.hideNavbar = true` (e.g. login)
    return !(route.meta && (route.meta as any).hideNavbar === true);
});
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <Navbar v-if="showNavbar" />
        <main>
            <router-view />
        </main>
    </div>
</template>

<style scoped></style>
