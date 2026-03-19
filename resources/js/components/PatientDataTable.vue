<template>
    <UniversalDataTable v-if="endpointUrl" :key="tableKey" :options="options" ref="tableEl" />
</template>

<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Doctor, Patient } from '@/types/models'
import { useAuthStore } from '@/stores/auth'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { usePatientStore } from '@/stores/patientStore'
import router from '@/router'
import DeleteConfirmationForm from '@/pages/Patients/partials/form/DeleteConfirmationForm.vue'
import CreatePatientForm from '@/pages/Patients/partials/form/CreatePatientForm.vue'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import useModal from '@/composables/useModal'
import { openPatientDocumentsModal, openPatientEditModal } from '@/helpers/modalHelpers'
import { formatBranchFullName, formatUserFullName } from '@/utils/formatUtils'
import api from '@/services/api' // <- changed


interface Props {
    endpointUrl: string
}

const props = defineProps<Props>()
const endpointUrl = computed(() => props.endpointUrl)

const patientStore = usePatientStore()
const { openModal } = useModal()
const authStore = useAuthStore()
const branchId = computed(() => authStore.currentBranch?.id ?? null)
const actionRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

type PatientViewMode = 'active' | 'deleted' | 'dead'
const viewMode = ref<PatientViewMode>('active')

const tableKey = computed(() => `patients-${branchId.value ?? 'none'}-${viewMode.value}`)

const showDeleted = computed(() => viewMode.value === 'deleted')
const showDead = computed(() => viewMode.value === 'dead')

function openPatientDocuments(patientId: number) {
    void openPatientDocumentsModal(patientId)
}

async function openEditPatient(patientId: number) {
    await openPatientEditModal(patientId)
    actionRemote.value?.reload()
}

async function restorePatients(rows: Patient[], remote?: RemoteTableReturn) {
    const ids = rows.filter((row) => row.deleted_at).map((row) => row.id)
    if (!ids.length) return

    await api.post('/v1/patients/restore', { ids }) // <- changed
    remote?.reload?.()
}

async function deletePatients(rows: Patient[], remote?: RemoteTableReturn) {
    await openModal(
        markRaw(DeleteConfirmationForm),
        {
            title: 'Vymazať',
            selectedRows: rows,
            remote,
        },
        {
            style: { width: '60%' },
        },
    )
}

const options = computed<DataTableOptions<Patient>>(() => {
    const baseColumns: DataTableOptions<Patient>['columns'] = [
        {
            field: 'first_name',
            header: 'Meno',
            sortable: true,
            render: (v, row) => {
                if (!row) return v

                if (row.deleted_at) return `<s>${v}</s>`
                if (row.death_date) return `${v} †`

                return v
            },
        },
        {
            field: 'last_name',
            header: 'Priezvisko',
            sortable: true,
            render: (v, row) => {
                if (!row) return v

                if (row.deleted_at) return `<s>${v}</s>`

                return v
            },
        },
        {
            field: 'personal_number',
            header: 'Rodné číslo',
            sortable: true,
            render: (v) => v,
        },
        {
            field: 'doctor',
            header: 'Ošetrujúci lekár',
            render: (v: Doctor) => (v ? `${v.title} ${v.first_name} ${v.last_name}` : ''),
            sortable: false,
        },
    ]

    if (showDead.value) {
        baseColumns.splice(3, 0, {
            field: 'death_date',
            header: 'Dátum úmrtia',
            sortable: true,
            render: (v) => (v ? new Date(v).toLocaleDateString('sk-SK') : ''),
        })
    }

    if (showDeleted.value) {
        baseColumns.splice(3, 0, {
            field: 'deleted_at',
            header: 'Dátum zmazania',
            sortable: true,
            render: (v) => (v ? new Date(v).toLocaleDateString('sk-SK') : ''),
        })
    }

    if (!showDeleted.value && !showDead.value) {
        baseColumns.splice(
            3,
            0,
            {
                field: 'adress',
                header: 'Adresa',
                render: (_v, row) => {
                    if (!row) return ''

                    const parts: string[] = []
                    if (row.address) parts.push(row.address)

                    const address = parts.join(', ') || ''
                    return address.length > 50 ? address.substring(0, 40) + '...' : address
                },
            },
            { field: 'city', header: 'Mesto', sortable: true },
        )
    }

    // documents = always visible
    baseColumns.push({
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
    })

    // pin = visible on active + dead, hidden on deleted
    if (!showDeleted.value) {
        baseColumns.push({
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
                        router.push('/patient/points')
                    },
                },
            ],
        })
    }

    // edit = only visible on active
    if (!showDeleted.value && !showDead.value) {
        baseColumns.push({
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
        })
    }

    const tableOptions: DataTableOptions<Patient> = {
        rowKey: 'id',
        endpointUrl: endpointUrl.value || '',
        defaultPageSize: 25,
        pageSizeOptions: [10, 25, 50],
        selectable: true,
        afterInit: ({ remote }) => {
            actionRemote.value = remote
        },
        extraParams: {
            ...(authStore.isManager ? { with: 'nurse,branch,doctor' } : {}),
            ...(showDeleted.value ? { only_deleted: 1 } : {}),
            ...(showDead.value ? { only_dead: 1 } : {}),
        },
        columns: baseColumns,
        actions: [
            {
                key: 'show-active',
                icon: 'bi bi-people',
                position: 'start',
                tooltip: 'Zobraziť aktívnych pacientov',
                class: viewMode.value === 'active'
                    ? '!bg-darkgrey !text-white !border !border-solid !border-darkgrey !focus:!border-darkgrey !active:!border-darkgrey !shadow-none focus:!shadow-none'
                    : '!border !border-solid !border-darkgrey !bg-white !text-darkgrey hover:!bg-darkgrey hover:!text-white hover:!border-darkgrey !focus:!border-darkgrey !active:!border-darkgrey !shadow-none focus:!shadow-none',
                handler: async ({ remote }) => {
                    viewMode.value = 'active'
                    remote.reload()
                },
                bordered: true,
            },
            {
                key: 'show-deleted',
                icon: 'bi bi-person-x',
                position: 'start',
                tooltip: 'Zobraziť zmazaných',
                class: viewMode.value === 'deleted'
                    ? '!bg-darkgrey !text-white !border !border-solid !border-darkgrey !focus:!border-darkgrey !active:!border-darkgrey !shadow-none focus:!shadow-none'
                    : '!border !border-solid !border-darkgrey !bg-white !text-darkgrey hover:!bg-darkgrey hover:!text-white hover:!border-darkgrey !focus:!border-darkgrey !active:!border-darkgrey !shadow-none focus:!shadow-none',
                handler: async ({ remote }) => {
                    viewMode.value = 'deleted'
                    remote.reload()
                },
                bordered: true,
            },
            {
                key: 'show-dead',
                icon: 'bi bi-person-exclamation',
                position: 'start',
                tooltip: 'Zobraziť zosulých',
                class: viewMode.value === 'dead'
                    ? '!bg-darkgrey !text-white !border !border-solid !border-darkgrey !focus:!border-darkgrey !active:!border-darkgrey !shadow-none focus:!shadow-none'
                    : '!border !border-solid !border-darkgrey !bg-white !text-darkgrey hover:!bg-darkgrey hover:!text-white hover:!border-darkgrey !focus:!border-darkgrey !active:!border-darkgrey !shadow-none focus:!shadow-none',
                handler: async ({ remote }) => {
                    viewMode.value = 'dead'
                    remote.reload()
                },
                bordered: true,
            },
        ],
    }

    if (!showDeleted.value && !showDead.value) {
        tableOptions.actions?.push(
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
            {
                key: 'delete',
                icon: 'bi bi-eraser',
                class: 'bg-danger!',
                handler: async ({ selectedRows, remote }) => {
                    await deletePatients(selectedRows as Patient[], remote)
                },
            },
        )
    }

    if (showDeleted.value) {
        tableOptions.actions?.push({
            key: 'restore',
            icon: 'bi bi-arrow-counterclockwise',
            class: 'bg-accent! hover:bg-darkgrey! text-white!',
            disabled: ({ selectedRows }) => {
                const rows = (selectedRows || []) as Patient[]
                return !rows.length || rows.some((row) => !row.deleted_at)
            },
            handler: async ({ selectedRows, remote }) => {
                await restorePatients(selectedRows as Patient[], remote)
            },
        })
    }

    if (authStore.isManager) {
        tableOptions.actions = tableOptions.actions?.filter(
            (action) => !['add', 'delete', 'restore'].includes(action.key),
        )

        tableOptions.columns = tableOptions.columns?.filter(
            (col) => !['personal_number', 'adress', 'city', 'pin'].includes(col.field || ''),
        )

        const doctorIndex = tableOptions.columns.findIndex((col) => col.field === 'doctor')

        const newColumns = [
            {
                field: 'nurse',
                header: 'Sestra',
                render: (_v: any, row: Patient) => (row.nurse ? formatUserFullName(row.nurse) : ''),
                sortable: false,
            },
            {
                field: 'branch',
                header: 'Prevádzka',
                render: (_: any, row: Patient) => formatBranchFullName(row.branch),
                sortable: false,
            },
        ]

        tableOptions.columns.splice(doctorIndex + 1, 0, ...newColumns)
    }

    return tableOptions
})
</script>
