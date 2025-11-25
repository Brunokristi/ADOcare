<script setup lang="ts">
import { computed } from 'vue';
import { ref } from 'vue';

import { usePatientStore } from '@/stores/patientStore';

const patientStore = usePatientStore();
const patient = computed(() => patientStore.current);

const isHovered = ref(false);

const closePatient = () => {
    isHovered.value = false;
    patientStore.clear();
};


</script>

<template>
  <!-- Show only when a patient is selected -->
  <Menubar
    v-if="patient"
    class="!bg-tag2 !px-3"
  >
    <template #start>
      <h2 class="!text-normal !px-sm !text-almostwhite border-r !border-almostwhite">
        {{ patient.firstname }} {{ patient.lastname }}
      </h2>

      <h2 class="!text-normal !px-sm !text-almostwhite">
        {{ patient.personalnumber }}
      </h2>
    </template>

    <template #end>
      <RouterLink
        :to="{ name: 'doctors' }"
        class="!text-mini !underline !px-sm transition-colors !text-almostwhite hover:!text-accent"
      >
        bodovanie
      </RouterLink>

      <button
          @click="closePatient"
          class="text-almostwhite pl-6 cursor-pointer" 
        >
          <i :class="isHovered ? 'bi bi-pin-angle' : 'bi bi-pin-fill'"
            @mouseenter="isHovered = true"
            @mouseleave="isHovered = false">
        </i>
        </button>
    </template>
  </Menubar>
</template>
