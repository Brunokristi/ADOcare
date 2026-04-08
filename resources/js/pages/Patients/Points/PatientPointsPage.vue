<script setup lang="ts">
import { ref, computed } from 'vue'
import PatientPointsForm from './PatientPointsForm.vue'
import PatientPointsTable from './PatientPointsTable.vue'
import { usePatientStore } from '@/stores/patientStore'

const tableRef = ref<any>(null)
const patientStore = usePatientStore()
const hasPatient = computed(() => !!patientStore.current)

function onCreated() {
    tableRef.value?.reload?.()
}
</script>

<template>
    <div class="flex flex-col gap-6 overflow-y-auto">
        <div v-if="!hasPatient" class="h-[60vh] flex items-center justify-center">
            <div class="text-center p-8">
                <h2 class="text-heading text-accent mb-2">Nie je vybraný žiadny pacient</h2>
                <p class="text-normal text-darkgrey mb-4">Vyberte pacienta v hornej lište, aby ste mohli spravovať body
                    pacienta.</p>

                <img src="/logo.svg" alt="Logo" class="mx-auto mt-6 w-15 h-15 object-contain">
            </div>
        </div>

        <div v-else class="flex flex-col gap-6">
            <PatientPointsForm @created="onCreated" />
            <PatientPointsTable ref="tableRef" />
        </div>
    </div>
</template>
