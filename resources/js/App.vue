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
    <div class="min-h-screen bg-darkgrey">
        <Navbar v-if="showNavbar" />
        <main class="p-4 bg-white h-quote-screen">
            <router-view />
        </main>
    </div>
</template>

<style scoped></style>
