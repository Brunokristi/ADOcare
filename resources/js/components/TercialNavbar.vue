<!-- src/components/PatientNavbar.vue -->
<script setup lang="ts">
import { computed, ref, watch, useAttrs } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import Menubar from 'primevue/menubar'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

import { usePatientStore } from '@/stores/patientStore'
import type { Patient } from '@/types/models'

defineOptions({ inheritAttrs: false })
const attrs = useAttrs()

const router = useRouter()
const patientStore = usePatientStore()
const patientId = computed(() => patientStore.current?.id ?? null)
const patient = computed<Patient | null>(() => patientStore.current)

const patientName = computed(() =>
  patient.value ? `${patient.value.first_name ?? ''} ${patient.value.last_name ?? ''}`.trim() : ''
)

const patientPersonalNumber = computed(() => patient.value?.personal_number ?? '')

const isHovered = ref(false)

const closePatient = () => {
  isHovered.value = false
  patientStore.clear()
  router.push('/patients')
}

/* -------------------------------------------------------------------------- */
/*  Incomplete info modal (small)                                             */
/* -------------------------------------------------------------------------- */

const showIncompleteModal = ref(false)
const incompletePatientId = ref<number | null>(null)


function patientIsComplete(p: Patient | null) {
  if (!p) return true

  const first = String(p.first_name ?? '').trim()
  const last = String(p.last_name ?? '').trim()
  const pn = String(p.personal_number ?? '').trim()
  const sex = (p as any).sex ?? null
  const doctorId = (p as any).doctor_id ?? null
  const insuranceId = (p as any).insurance_company_id ?? null
  const street = String((p as any).address ?? '').trim()
  const city = String((p as any).city ?? '').trim()
  const zip = String((p as any).zip ?? '').trim()
  const lat = (p as any).latitude
  const lng = (p as any).longitude

  // Check if all required fields are filled (not empty)
  if (!first || !last || !pn || !sex || !doctorId || !insuranceId) return false
  if (!street || !city || !zip) return false
  if (lat == null || lng == null) return false

  return true
}

watch(
  () => patientStore.current,
  (p) => {
    if (!p?.id) {
      showIncompleteModal.value = false
      incompletePatientId.value = null
      return
    }

    if (!patientIsComplete(p)) {
      incompletePatientId.value = p.id
      showIncompleteModal.value = true
    } else {
      showIncompleteModal.value = false
      incompletePatientId.value = null
    }
  },
  { immediate: true, deep: true }
)
</script>

<template>
  <Menubar
    v-if="patient"
    v-bind="attrs"
    class="bg-tag2! px-3! flex items-center py-2 justify-between"
  >
    <template #start>
      <div class="flex items-center">
        <h2 class="text-normal! pr-sm! text-almostwhite! border-r border-almostwhite!">
          {{ patientName }}
        </h2>

        <h2 class="text-normal! px-sm! text-almostwhite!">
          {{ patientPersonalNumber }}
        </h2>
      </div>
    </template>

    <template #end>
      <div class="flex items-center gap-2">
        <RouterLink
          :to="{ path: '/patient/points' }"
          class="text-mini! underline px-sm! transition-colors text-almostwhite! hover:text-accent!"
        >
          bodovanie
        </RouterLink>

        <RouterLink
          :to="{ path: '/patient/dekurz' }"
          class="text-mini! underline px-sm! transition-colors text-almostwhite! hover:text-accent!"
        >
          dekurz
        </RouterLink>

        <RouterLink
          :to="{ path: '/patient/proposal' }"
          class="text-mini! underline px-sm! transition-colors text-almostwhite! hover:text-accent!"
        >
          návrh
        </RouterLink>

        <RouterLink
          :to="{ path: '/patient/agreement' }"
          class="text-mini! underline px-sm! transition-colors text-almostwhite! hover:text-accent!"
        >
          dohoda
        </RouterLink>

        <RouterLink
          :to="{ path: '/patient/record' }"
          class="text-mini! underline px-sm! transition-colors text-almostwhite! hover:text-accent!"
        >
          ošetrovateľský záznam
        </RouterLink>

        <button
          type="button"
          @click="$router.replace({ query: { ...$route.query, editPatient: patientId } })"
          class="text-mini! underline px-sm! transition-colors text-almostwhite! hover:text-accent! cursor-pointer"
        >
          upraviť
        </button>

        <button
          type="button"
          @click="$router.replace({ query: { ...$route.query, patientDocuments: patientId } })"
          class="text-mini! underline px-sm! transition-colors text-almostwhite! hover:text-accent! cursor-pointer"

        >
          dokumenty
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

  <Dialog
    v-model:visible="showIncompleteModal"
    modal
    header="😞 Ajaj"
    :style="{ width: '420px', maxWidth: '95vw' }"
  >
    <div class="flex flex-col gap-4">
      <p class="text-normal">
        Zdá sa, že informácie o tomto pacientovi nie sú kompletné alebo správne.
      </p>

      <div class="flex justify-end gap-2">
        <Button
          label="Upraviť teraz"
          @click="$router.replace({ query: { ...$route.query, editPatient: patientId } })"
          class="bg-accent! border-0! text-white! hover:bg-darkgrey! px-4!"
        />
      </div>
    </div>
  </Dialog>
</template>
