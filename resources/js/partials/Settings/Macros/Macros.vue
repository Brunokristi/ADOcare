<script setup lang="ts">
import { markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import MacroForm from '@/partials/Settings/Macros/MacroForm.vue'
import type { Macro } from '@/types/models'
import api from '@/services/api'
import type { DataTableOptions } from '@/types/datatable'
import { default as ActionButtons, type ActionButtonOptions } from '@/components/table-columns/ActionButtons.vue'

// UI state for modal
const showMacroDialog = ref(false)
const editingMacro = ref<Partial<Macro> | null>(null)
const actionRemote = ref<any>(null) // remote controller passed from UniversalDataTable actions

function openCreate() {
    editingMacro.value = { name: '', abbreviation: '', text: '' }
    showMacroDialog.value = true
}

function openEdit(row: Macro) {
    editingMacro.value = { ...row }
    showMacroDialog.value = true
}

async function onSaveMacro() {
    // after save, reload table if remote provided
    try {
        if (actionRemote.value?.loadPage) {
            await actionRemote.value.loadPage(1)
        }
    } finally {
        showMacroDialog.value = false
        editingMacro.value = null
    }
}

async function onCloseDialog() {
    showMacroDialog.value = false
    editingMacro.value = null
}

// typed options for macros table
const options = ref<DataTableOptions<Macro>>({
    rowKey: 'id',
    endpointUrl: 'v1/macros',
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    afterInit: ({ remote }) => {
        actionRemote.value = remote;
    },
    columns: [
        { field: 'id', header: 'ID' },
        { field: 'name', header: 'Názov', sortable: true },
        { field: 'abbreviation', header: 'Skratka' },
        { field: 'text', width: '50%', header: 'Text' },
        {
            field: 'edit', header: ' ', width: '3rem', component: markRaw(ActionButtons), componentOptions: [{
                color: 'info',
                icon: 'bi bi-pencil',
                tooltip: 'Upraviť makro',
                action: async (row: Macro) => {
                    console.log('Opening edit macros');

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
            disabled: ({ selectedRows }) => !selectedRows || selectedRows.length === 0,
            confirm: 'Naozaj vymazať vybrané makrá?',
            handler: async ({ remote, selectedRows }: any) => {
                try {
                    for (const r of selectedRows ?? []) {
                        await api.delete(`/v1/macros/${r.id}`)
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
            icon: 'bi bi-plus',
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

        <MacroForm v-if="showMacroDialog" :macro="editingMacro" @save="onSaveMacro" @close="onCloseDialog" />
    </div>
</template>

<style scoped>
.text-heading {
    font-weight: 600;
    font-size: 1.125rem
}
</style>
