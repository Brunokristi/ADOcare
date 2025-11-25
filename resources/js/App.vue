<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Navbar from '@/components/Navbar.vue';
import Footer from '@/components/Footer.vue';
import Sidebar from '@/components/Sidebar.vue';
import TercialNavbar from './components/TercialNavbar.vue';

const router = useRouter();
const route = useRoute();
onMounted(() => {
    window.addEventListener('unauthenticated', () => {
        router.push({ name: 'login' });
    });
});

const showNavbar = computed(() => {
    return !(route.meta && (route.meta as any).hideNavbar === true);
});

const showPatient = computed(() => {
    return route.name !== 'home' && route.name !== 'settings';
});
</script>

<template>
    <div class="h-screen flex flex-col bg-darkgrey">
        <Navbar v-if="showNavbar" class="flex-none" />
        <TercialNavbar v-if="showPatient" class="flex-none" />

        <div class="flex h-screen flex-row-reverse">
            <Sidebar v-if="showNavbar" />
            <div class="main-content h-full flex-1 overflow-auto bg-white p-8">
                <router-view />
            </div>
        </div>

        <Footer class="flex-none" />

        <Toast />
    </div>
</template>

<style scoped></style>
