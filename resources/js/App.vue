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

import PatientDocumentsModal from '@/pages/partials/patient/PatientDocumentsModal.vue'
import PatientEditModal from '@/pages/partials/patient/PatientEditModal.vue'

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

/* -------------------------------------------------------------------------- */
/*  Global overlays via query params                                           */
/*  ?patientDocuments=123                                                      */
/*  ?editPatient=123                                                           */
/* -------------------------------------------------------------------------- */

const showPatientDocuments = computed(() => {
    const v = route.query.patientDocuments
    return v !== undefined && v !== null && String(Array.isArray(v) ? v[0] : v).length > 0
})
const patientDocumentsId = computed(() => {
    const v = route.query.patientDocuments
    const raw = Array.isArray(v) ? v[0] : v
    const id = Number(raw)
    return Number.isFinite(id) ? id : 0
})
function closePatientDocuments() {
    const q: Record<string, any> = { ...route.query }
    delete q.patientDocuments
    router.replace({ query: q })
}

const showEditPatient = computed(() => {
    const v = route.query.editPatient
    return v !== undefined && v !== null && String(Array.isArray(v) ? v[0] : v).length > 0
})
const editPatientId = computed(() => {
    const v = route.query.editPatient
    const raw = Array.isArray(v) ? v[0] : v
    const id = Number(raw)
    return Number.isFinite(id) ? id : 0
})
function closeEditPatient() {
    const q: Record<string, any> = { ...route.query }
    delete q.editPatient
    router.replace({ query: q })
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

        <!-- ✅ global overlays (do not change background route) -->
        <PatientDocumentsModal v-if="showPatientDocuments && patientDocumentsId > 0" :patient-id="patientDocumentsId"
            @close="closePatientDocuments" />

        <PatientEditModal v-if="showEditPatient && editPatientId > 0" :patient-id="editPatientId"
            @close="closeEditPatient" />

        <!-- keep if you still use named modal routes anywhere else -->
        <router-view name="modal" />
    </div>
</template>
