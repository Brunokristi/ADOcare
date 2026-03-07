<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'

import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Doctor, Patient } from '@/types/models'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { usePatientStore } from '@/stores/patientStore'
import router from '@/router'
import CreatePatientForm from './partials/form/CreatePatientForm.vue'
import DeleteConfirmationForm from './partials/form/DeleteConfirmationForm.vue'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import useModal from '@/composables/useModal'
import { openPatientDocumentsModal, openPatientEditModal } from '@/helpers/modalHelpers'
import { formatBranchFullName, formatUserFullName } from '@/utils/formatUtils'

const patientStore = usePatientStore()
const toast = useToast()

const { openModal } = useModal()

const authStore = useAuthStore()
const branchId = computed(() => authStore.currentBranch?.id ?? null)
const companyId = computed(() => authStore.user?.company?.id ?? null)
const tableKey = computed(() => `patients-${branchId.value ?? 'none'}`)
const actionRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

// soft-delete toggle helpers
import { useTableActions } from '@/composables/useTableActions'
const { showDeleted, toggleAction, deleteAction } = useTableActions(
    actionRemote.value,
    {
        softDelete: {
            restoreEndpoint: '/v1/patients/restore',
            deletePrompt: async ({ selectedRows, remote }) => {
                await openModal(
                    markRaw(DeleteConfirmationForm),
                    { title: 'Vymazať', selectedRows, remote },
                    { style: { width: '60%' } },
                )
            },
        },
    },
)

function openPatientDocuments(patientId: number) {
    void openPatientDocumentsModal(patientId)
}

async function openEditPatient(patientId: number) {
    await openPatientEditModal(patientId)
    actionRemote.value?.reload()
}

const endpointUrl = computed(() => {
    if (authStore.isManager) {
        return `v1/companies/${companyId.value}/patients`
    } else {
        return `v1/branches/${branchId.value}/patients`
    }
})

const options = computed<DataTableOptions<Patient>>(() => {

    const tableOptions: DataTableOptions<Patient> = {
        rowKey: 'id',
        endpointUrl: endpointUrl.value || '',
        defaultPageSize: 25,
        pageSizeOptions: [10, 25, 50],
        selectable: true,
        afterInit: ({ remote }) => {
            actionRemote.value = remote;
        },
        extraParams: {
            ...(authStore.isManager ? { with: 'nurse,branch,doctor' } : {}),
            ...(showDeleted.value ? { only_deleted: 1 } : {}),
        },

        columns: [
            {
                field: 'first_name', header: 'Meno', sortable: true,
                render: (v) => showDeleted.value ? `<s>${v}</s>` + ' (zmazaný)' : v
            },
            {
                field: 'last_name', header: 'Priezvisko', sortable: true,
            },
            {
                field: 'personal_number',
                header: 'Rodné číslo',
                sortable: true,
                render: (v) => v,
            },
            {
                field: 'adress',
                header: 'Adresa',
                render: (_v, row) => {
                    if (!row) return ''
                    const parts = []
                    if (row.address) parts.push(row.address)
                    const address = parts.join(', ') || ''
                    return address.length > 50 ? address.substring(0, 40) + '...' : address
                },
            },
            { field: 'city', header: 'Mesto', sortable: true },
            {
                field: 'doctor',
                header: 'Ošetrujúci lekár',
                render: (v: Doctor) => (v ? `${v.title} ${v.first_name} ${v.last_name}` : ''),
                sortable: false,
            },
            {
                field: 'pin',
                header: '',
                width: '3rem',
                component: ActionButtons,
                componentOptions: [
                    {
                        icon: (row: Patient) =>
                            patientStore.current?.id === row.id ? 'bi bi-pin-fill' : 'bi bi-pin',
                        color: 'info',
                        tooltip: 'Pripnúť pacienta',
                        action: (row: Patient) => {
                            patientStore.setPatient(row)
                            router.push(`/patient/points`)
                        },
                    },
                ],
            },
            {
                field: 'documents',
                header: '',
                width: '3rem',
                component: ActionButtons,
                componentOptions: [
                    {
                        icon: 'bi bi-folder',
                        color: 'info',
                        tooltip: 'Zobraziť dokumenty',
                        action: (row: Patient) => {
                            openPatientDocuments(row.id)
                        },
                    },
                ],
            },
            {
                field: 'edit',
                header: '',
                width: '3rem',
                component: markRaw(ActionButtons),
                componentOptions: [
                    {
                        icon: 'bi bi-pencil',
                        color: 'info',
                        tooltip: 'Editovať pacienta',
                        action: (row: Patient) => {
                            openEditPatient(row.id)
                        },
                    },
                ],
            },
        ],

        actions: [
            deleteAction,
            {
                key: 'add',
                icon: 'bi bi-plus-lg',
                class: 'bg-accent!',
                handler: async () => {
                    await openModal(
                        markRaw(CreatePatientForm),
                        { title: 'Pridať Pacienta' },
                        { style: { width: '90%' } },
                    )
                },
            },
            toggleAction,
        ],
    }
    if (authStore.isManager) {
        tableOptions.actions = [];
        // Remove address, city, pin columns for manager view
        tableOptions.columns = tableOptions.columns?.filter((col => !['personal_number', 'adress', 'city', 'pin'].includes(col.field || '')));
        // Add Sestra, Prevádzka columns for manager view after last name
        const doctorIndex = tableOptions.columns.findIndex(col => col.field === 'doctor');
        const newColumns = [
            {
                field: 'nurse',
                header: 'Sestra',
                render: (_v: any, row: Patient) => {
                    if (row.nurse) {
                        return formatUserFullName(row.nurse)
                    }
                    return '';
                },
                sortable: false,
            },
            {
                field: 'branch',
                header: 'Prevádzka',
                render: (_: any, row: Patient) => {
                    const branch = row.branch;
                    return formatBranchFullName(branch)
                },
                sortable: false,
            },
        ];
        tableOptions.columns.splice(doctorIndex + 1, 0, ...newColumns);


    }


    return tableOptions
})



</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable v-if="options.endpointUrl" :key="tableKey" :options="options" ref="tableEl"
            @action="(key, payload) => console.log('action emitted', key, payload)" />
    </div>
</template>


<style scoped>
.text-muted {
    color: #6b7280;
}
</style>
