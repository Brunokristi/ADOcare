<!-- src/components/PatientNavbar.vue (your navbar, updated) -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import Menubar from 'primevue/menubar'

import { usePatientStore } from '@/stores/patientStore'
import { useUiModalsStore } from '@/stores/uiModals'
import type { Patient } from '@/types/models'

const router = useRouter()
const patientStore = usePatientStore()
const uiModals = useUiModalsStore()

const patient = computed<Patient | null>(() => patientStore.current)

const patientName = computed(() =>
  patient.value
    ? `${patient.value.first_name ?? ''} ${patient.value.last_name ?? ''}`.trim()
    : ''
)

const patientPersonalNumber = computed(() => patient.value?.personal_number ?? '')

const isHovered = ref(false)

const closePatient = () => {
  isHovered.value = false
  patientStore.clear()
  router.push('/patients')
}

const openEdit = () => {
  const id = patient.value?.id
  if (!id) return
  uiModals.openPatientEdit(id) // ✅ open global dialog, no redirect
}
</script>

<template>
  <Menubar v-if="patient" class="!bg-tag2 !px-3 flex items-center py-2 justify-between">
    <template #start>
      <div class="flex items-center">
        <h2 class="!text-normal !pr-sm !text-almostwhite border-r !border-almostwhite">
          {{ patientName }}
        </h2>

        <h2 class="!text-normal !px-sm !text-almostwhite">
          {{ patientPersonalNumber }}
        </h2>
      </div>
    </template>

    <template #end>
      <div class="flex items-center gap-2">
        <RouterLink
          :to="{ path: '/patient/points' }"
          class="!text-mini underline !px-sm transition-colors !text-almostwhite hover:!text-accent"
        >
          bodovanie
        </RouterLink>

        <button
          type="button"
          @click="openEdit"
          class="!text-mini underline !px-sm transition-colors !text-almostwhite hover:!text-accent"
        >
          upraviť
        </button>

        <button @click="closePatient" class="text-almostwhite cursor-pointer flex items-center ml-4">
          <i
            :class="isHovered ? 'bi bi-pin-angle' : 'bi bi-pin-fill'"
            @mouseenter="isHovered = true"
            @mouseleave="isHovered = false"
            class="text-lg leading-none"
          ></i>
        </button>
      </div>
    </template>
  </Menubar>
</template>
