<script setup lang="ts">
import { ref, computed } from 'vue'
import PatientPointsForm from './PatientPointsForm.vue'
import PatientPointsTable from './PatientPointsTable.vue'
import { usePatientStore } from '@/stores/patientStore'
import { useRouter } from 'vue-router'
import type Button from 'primevue/button'

const tableRef = ref<any>(null)
const patientStore = usePatientStore()
const router = useRouter()
const hasPatient = computed(() => !!patientStore.current)

function onCreated() {
    tableRef.value?.reload?.()
}

function goToPatients() {
    router.push('/patients')
}
</script>

<template>
    <div class="flex flex-col gap-6 overflow-y-auto">
        <div v-if="!hasPatient" class="h-[60vh] flex items-center justify-center">
            <div class="text-center p-8 bg-white rounded shadow w-full max-w-2xl">
                <div class="flex justify-center mb-6">
                    <!-- icon -->
                    <i class="bi bi-person-x text-6xl text-gray-400"></i>
                </div>

                <h2 class="text-xl font-semibold mb-2">Nie je vybraný žiadny pacient</h2>
                <p class="text-normal text-muted mb-4">Vyberte pacienta v hornej lište, aby ste mohli spravovať body
                    pacienta.</p>
                <div class="flex justify-center">
                    <Button label="Vyberte pacienta" class="btn btn-primary" @click="goToPatients" />
                </div>
            </div>
        </div>

        <div v-else class="flex flex-col gap-6">
            <PatientPointsForm @created="onCreated" />
            <PatientPointsTable ref="tableRef" />
        </div>
    </div>
</template>
