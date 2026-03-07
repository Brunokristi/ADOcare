<script setup lang="ts">
import { ref } from 'vue'
import type { IModalContentProps } from '@/types/ui'
import type { Patient } from '@/types/models'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import type { RemoteTableReturn } from '@/types/datatable'

const props = defineProps<IModalContentProps & {
    selectedRows: Patient[]
    remote: RemoteTableReturn
}>()

const toast = useToast()
const deletePatientPointsData = ref(false)
const isLoading = ref(false)

const confirmDelete = async () => {
    try {
        isLoading.value = true
        await api.delete('v1/patients', {
            data: {
                ids: props.selectedRows.map((r) => r.id),
                delete_patient_points: deletePatientPointsData.value,
            },
        })
        await props.remote.loadPage(props.remote.page.value)
        toast.add({ severity: 'success', summary: 'Vymazané', detail: 'Pacienti boli vymazaní', life: 3000 })
        if (props.modalResolve) props.modalResolve(true)
    } catch (err) {
        console.error('Failed to delete patients', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa vymazať pacientov', life: 3000 })
    } finally {
        isLoading.value = false
    }
}

const cancel = () => {
    if (props.modalResolve) props.modalResolve(false)
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div>
            <p class="text-danger">
                Naozaj vymazať vybraných pacientov?
            </p>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox inputId="deletePatientPoints" v-model="deletePatientPointsData" :binary="true" />
            <label for="deletePatientPoints" class="text-normal cursor-pointer">Odstrániť týmto pacientom všetky
                body</label>
        </div>

        <div class="col-span-12 mt-4 flex items-center justify-end gap-2">
            <Button label="Zrušiť" text @click="cancel" class="text-accent! px-2!" :loading="isLoading" />
            <Button label="Vymazať" @click="confirmDelete" :loading="isLoading"
                class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white! " />
        </div>
    </div>
</template>
