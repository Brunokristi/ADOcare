<script setup lang="ts">
import { markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Diagnosis } from '@/types/models'
import DiagnosisForm from './DiagnosisForm.vue'
import api from '@/services/api'
import type { DataTableOptions } from '@/types/datatable'
import { default as ActionButtons, type ActionButtonOptions } from '@/components/table-columns/ActionButtons.vue'
import useModal from '@/composables/useModal'
import useAuthStore from '@/stores/auth'

const auth = useAuthStore()
const { openModal } = useModal()
const actionRemote = ref<any>(null)

async function openCreate() {
    let reponse: any = null
    try {
        reponse = await openModal(markRaw(DiagnosisForm), { diagnosis: null }, { header: 'Diagnóza', style: { width: '640px' }, closable: true })
    } finally {
        if (reponse) await actionRemote.value.loadPage(1)
    }
}

async function openEdit(row: Diagnosis) {
    let reponse: any = null
    try {
        reponse = await openModal(markRaw(DiagnosisForm), { diagnosis: row }, { header: 'Diagnóza', style: { width: '640px' }, closable: true })
    } finally {
        if (reponse) await actionRemote.value.loadPage(1)
    }
}

const options = ref<DataTableOptions<Diagnosis>>({
    rowKey: 'id',
    endpointUrl: 'v1/diagnoses',
    defaultPageSize: 50,
    pageSizeOptions: [25, 50, 100],
    selectable: auth.isSuperadmin,
    afterInit: ({ remote }) => {
        actionRemote.value = remote
    },
    columns: [
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'description', header: 'Popis', sortable: true },
    ],
    actions: [],
})

if (auth.isSuperadmin) {
    options.value.columns.push({
        field: 'edit',
        header: ' ',
        width: '3rem',
        component: markRaw(ActionButtons),
        componentOptions: [{
            color: 'info',
            icon: 'bi bi-pencil',
            tooltip: 'Upraviť diagnózu',
            action: async (row: Diagnosis) => {
                openEdit(row)
            }
        }] as ActionButtonOptions[]
    })

    options.value.actions = [
        {
            key: 'delete',
            label: '',
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            tooltip: 'Vymazať vybrané diagnózy',
            disabled: ({ selectedRows }) => !selectedRows || selectedRows.length === 0,
            confirm: 'Naozaj vymazať vybrané diagnózy?',
            handler: async ({ remote, selectedRows }: any) => {
                try {
                    for (const r of selectedRows ?? []) {
                        await api.delete(`/v1/diagnoses/${r.id}`)
                    }
                } catch (err) {
                    console.error('Delete failed', err)
                } finally {
                    await remote.loadPage(1)
                }
            },
        },
        {
            key: 'add',
            label: '',
            tooltip: 'Pridať novú diagnózu',
            icon: 'bi bi-plus',
            class: 'bg-accent!',
            handler: async () => {
                openCreate()
            },
        },
    ]
}
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :options="options" />
    </div>
</template>
