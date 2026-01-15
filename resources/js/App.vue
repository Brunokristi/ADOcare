<script setup lang="ts">
import { onMounted, computed, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'

import Navbar from '@/components/Navbar.vue'
import Footer from '@/components/Footer.vue'
import Sidebar from '@/components/Sidebar.vue'
import TercialNavbar from './components/TercialNavbar.vue'

import { useAuthStore } from '@/stores/auth'
import ModalProvider from './components/ModalProvider.vue'
import GlobalPatientModal from '@/components/GlobalPatientModal.vue'

// ✅ query-driven patient documents modal (global overlay)
import PatientDocumentsModal from '@/pages/partials/patient/PatientDocumentsModal.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

auth.init()

const OPT_OUT_KEY = 'price_check_alert_dont_show'
const OLD_SEEN_KEY = 'price_check_alert_seen'

onMounted(() => {
  localStorage.removeItem(OLD_SEEN_KEY)

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
/*  Global: Patient documents modal controlled by query param                  */
/*  Example: ?patientDocuments=123                                             */
/* -------------------------------------------------------------------------- */

const showPatientDocuments = computed(() => {
  const v = route.query.patientDocuments
  if (v === undefined || v === null) return false
  // Accept "0" only if you want, otherwise treat it as false:
  return String(v).length > 0
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

/* -------------------------------------------------------------------------- */
/*  Login alert (every login unless opted out)                                 */
/* -------------------------------------------------------------------------- */

const showPriceAlert = ref(false)
const dontShowAgain = ref(false)

watch(
  isLoggedIn,
  (loggedIn) => {
    if (!loggedIn) return

    const optedOut = localStorage.getItem(OPT_OUT_KEY) === '1'
    if (!optedOut) {
      dontShowAgain.value = false // reset choice each time dialog opens
      showPriceAlert.value = true
    }
  },
  { immediate: true },
)

function persistOptOutIfChecked() {
  if (dontShowAgain.value) {
    localStorage.setItem(OPT_OUT_KEY, '1')
  }
}

function closePriceAlert() {
  persistOptOutIfChecked()
  showPriceAlert.value = false
}

function goToPricesPage() {
  persistOptOutIfChecked()
  showPriceAlert.value = false
  router.push('/settings/procedures')
}
</script>

<template>
  <div class="h-screen flex flex-col bg-darkgrey">
    <Navbar
      class="flex-none"
      :isSidebarOpen="isSidebarOpen"
      @toggle-sidebar="handleToggleSidebar"
    />

    <TercialNavbar v-if="isLoggedIn" class="flex-none" />

    <div class="flex flex-1 overflow-hidden">
      <div class="flex-1 overflow-auto bg-white p-8">
        <router-view />
      </div>

      <Sidebar
        v-if="isSidebarOpen && isLoggedIn"
        class="flex-none bg-darkgrey text-white border-l border-lightgrey"
      />
    </div>

    <Footer class="flex-none" />

    <Toast position="bottom-right" />

    <!-- your existing global modals -->
    <ModalProvider />
    <GlobalPatientModal />

    <!-- ✅ global overlay modal that DOES NOT change the background route -->
    <PatientDocumentsModal
      v-if="showPatientDocuments && patientDocumentsId > 0"
      :patient-id="patientDocumentsId"
      @close="closePatientDocuments"
    />

    <!-- you can keep this if you still use named modal routes elsewhere -->
    <router-view name="modal" />

    <Dialog
      v-model:visible="showPriceAlert"
      :modal="true"
      :closable="false"
      header="Upozornenie"
      :style="{ width: '520px', maxWidth: '95vw' }"
    >
      <div class="flex flex-col gap-4">
        <p class="text-normal">Skontrolujte ceny úhrady zdravotnej starostlivosti</p>

        <div class="flex items-center gap-2">
          <Checkbox v-model="dontShowAgain" binary inputId="dontShowAgain" />
          <label for="dontShowAgain" class="text-sm text-darkgrey">
            Už viac nezobrazovať
          </label>
        </div>

        <div class="flex justify-end gap-2">
          <Button label="Neskôr" text @click="closePriceAlert" class="text-darkgrey!" />

          <Button
            label="Otvoriť ceny"
            class="bg-accent! border-0! text-white! hover:bg-darkgrey! px-3!"
            @click="goToPricesPage"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>
