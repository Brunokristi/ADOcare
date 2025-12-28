<script setup lang="ts">
import { onMounted, computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Navbar from '@/components/Navbar.vue';
import Footer from '@/components/Footer.vue';
import Sidebar from '@/components/Sidebar.vue';
import TercialNavbar from './components/TercialNavbar.vue';
import { useAuthStore } from '@/stores/auth';
import ModalProvider from './components/ModalProvider.vue';
import GlobalPatientModal from '@/components/GlobalPatientModal.vue';

const router = useRouter();
const auth = useAuthStore();

auth.init();

onMounted(() => {
  window.addEventListener('unauthenticated', () => {
    // so the alert shows again on next successful login
    localStorage.removeItem('price_check_alert_seen');
    router.push({ name: 'login' });
  });
});

const isLoggedIn = computed(() => auth.isAuthenticated);

const isSidebarOpen = ref(false);
function handleToggleSidebar() {
  isSidebarOpen.value = !isSidebarOpen.value;
}

/* -------------------------------------------------------------------------- */
/*  Login alert (once per login)                                              */
/* -------------------------------------------------------------------------- */

const showPriceAlert = ref(false);

watch(
  isLoggedIn,
  (loggedIn) => {
    if (!loggedIn) return;

    const seen = 0;
    if (!seen) {
      showPriceAlert.value = true;
    }
  },
  { immediate: true },
);

function closePriceAlert() {
  localStorage.setItem('price_check_alert_seen', '1');
  showPriceAlert.value = false;
}

function goToPricesPage() {
  localStorage.setItem('price_check_alert_seen', '1');
  showPriceAlert.value = false;
  router.push('/settings/procedures');
}
</script>

<template>
  <div class="h-screen flex flex-col bg-darkgrey">
    <Navbar class="flex-none" @toggle-sidebar="handleToggleSidebar" />

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

    <ModalProvider />
    <GlobalPatientModal />

    <!-- ✅ Login alert -->
    <Dialog
      v-model:visible="showPriceAlert"
      :modal="true"
      :closable="false"
      header="Upozornenie"
      :style="{ width: '520px', maxWidth: '95vw' }"
    >
      <div class="flex flex-col gap-4">
        <p class="text-normal">
          Skontrolujte ceny úhrady zdravotnej starostlivosti
        </p>

        <div class="flex justify-end gap-2">
          <Button
            label="Neskôr"
            text
            @click="closePriceAlert"
            class="!text-darkgrey"
          />

          <Button
            label="Otvoriť ceny"
            class="!bg-accent !border-0 !text-white hover:!bg-darkgrey !px-3"
            @click="goToPricesPage"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>
