<!-- src/App.vue -->
<script setup lang="ts">
import { onMounted, computed, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'

import Navbar from '@/components/Navbar.vue'
import Footer from '@/components/Footer.vue'
import Sidebar from '@/components/Sidebar.vue'
import TercialNavbar from './components/TercialNavbar.vue'

import { useAuthStore } from '@/stores/auth'
import ModalProvider from './components/ModalProvider.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()


onMounted(() => {
    window.addEventListener('unauthenticated', () => {
        router.push({ name: 'login' })
    })
})

const isLoggedIn = computed(() => auth.isAuthenticated)

const isSidebarOpen = ref(false)
function handleToggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value
}

</script>

<template>
    <div class="h-screen flex flex-col bg-darkgrey">
        <Navbar class="flex-none" :isSidebarOpen="isSidebarOpen" @toggle-sidebar="handleToggleSidebar" />

        <TercialNavbar v-if="isLoggedIn" class="flex-none" />

        <div class="flex flex-1 overflow-hidden">
            <div class="flex-1 overflow-auto bg-white p-8">
                <router-view />
            </div>

            <Sidebar v-if="isSidebarOpen && isLoggedIn"
                class="flex-none bg-darkgrey text-white border-l border-lightgrey" />
        </div>

        <Footer class="flex-none" />

        <Toast position="bottom-right" />

        <!-- existing global modals -->
        <ModalProvider />

        <!-- keep if you still use named modal routes anywhere else -->
        <router-view name="modal" />
    </div>
</template>
