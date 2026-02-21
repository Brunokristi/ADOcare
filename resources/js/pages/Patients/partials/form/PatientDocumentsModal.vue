<script setup lang="ts">
import PatientDocumentsTable from './PatientDocumentsForm.vue'

const props = defineProps<{ patientId: number }>()
const emit = defineEmits<{ (e: 'close'): void }>()
</script>

<template>
  <Dialog :visible="true" modal header="Dokumenty pacienta" class="w-11/12" @update:visible="emit('close')">
    <PatientDocumentsTable :patient-id="props.patientId" />
  </Dialog>
</template>
