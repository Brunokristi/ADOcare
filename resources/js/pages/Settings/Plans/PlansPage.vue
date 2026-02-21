<script setup lang="ts">
import { markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import PlanForm from './PlansForm.vue'
import type { Plan } from '@/types/models'
import api from '@/services/api'
import type { DataTableOptions } from '@/types/datatable'
import { default as ActionButtons, type ActionButtonOptions } from '@/components/table-columns/ActionButtons.vue'
import useModal from '@/composables/useModal'

const { openModal } = useModal()
const actionRemote = ref<any>(null)

async function openCreate() {
    try {
        await openModal(markRaw(PlanForm), { plan: null }, { header: 'Plán', style: { width: '640px' }, closable: true })
    } finally {
        if (actionRemote.value?.loadPage) await actionRemote.value.loadPage(1)
    }
}

async function openEdit(row: Plan) {
    try {
        await openModal(markRaw(PlanForm), { plan: row }, { header: 'Plán', style: { width: '640px' }, closable: true })
    } finally {
        if (actionRemote.value?.loadPage) await actionRemote.value.loadPage(1)
    }
}

// onSave/onClose handled by modal resolve and openCreate/openEdit's finally block

// typed options for plans table
const options = ref<DataTableOptions<Plan>>({
    rowKey: 'id',
    endpointUrl: 'v1/plans',
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    afterInit: ({ remote }) => {
        actionRemote.value = remote;
    },
    columns: [
        { field: 'name', header: 'Názov', sortable: true },
        { field: 'text', width: '70%', header: 'Text' },
        {
            field: 'edit', header: ' ', width: '3rem', component: markRaw(ActionButtons), componentOptions: [{
                color: 'info',
                icon: 'bi bi-pencil',
                tooltip: 'Upraviť plán',
                action: async (row: Plan) => {
                    openEdit(row);
                }
            }] as ActionButtonOptions[]
        }
    ],
    actions: [
        {
            key: 'delete',
            label: '',
            icon: 'bi bi-eraser',
            class: 'bg-warning!',
            tooltip: 'Vymazať vybrané plány',
            disabled: ({ selectedRows }) => !selectedRows || selectedRows.length === 0,
            confirm: 'Naozaj vymazať vybrané plány?',
            handler: async ({ remote, selectedRows }: any) => {
                try {
                    await api.delete(`/v1/plans`, {
                        data: {
                            ids: selectedRows.map((r: Plan) => r.id),
                        },
                    })
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
            tooltip: 'Pridať nový plán',
            icon: 'bi bi-plus',
            class: 'bg-accent!',
            handler: async () => {
                openCreate()
            },
        },
    ],
})
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :options="options" />
    </div>
</template>

<style scoped>
.text-heading {
    font-weight: 600;
    font-size: 1.125rem
}
</style>
