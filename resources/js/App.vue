<script setup lang="ts">
import { onMounted, computed, ref } from 'vue';
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

const isSidebarOpen = ref(false);

function handleToggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value;
}
</script>

<template>
  <div class="h-screen flex flex-col bg-darkgrey">
    <Navbar
      v-if="showNavbar"
      class="flex-none"
      @toggle-sidebar="handleToggleSidebar"
    />

    <TercialNavbar v-if=" showNavbar && showPatient" class="flex-none" />

    <div class="flex flex-1 overflow-hidden">
        <div class="flex-1 overflow-auto bg-white p-8">
            <router-view />
        </div>

        <Sidebar
            v-if="showNavbar&& isSidebarOpen"
            class="flex-none bg-darkgrey text-white border-l border-lightgrey"
        />
    </div>

    <Footer class="flex-none" />

    <Toast />
  </div>
</template>
