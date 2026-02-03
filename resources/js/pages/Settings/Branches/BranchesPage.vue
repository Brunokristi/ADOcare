<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Branch, User } from '@/types/models'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { useToast } from 'primevue/usetoast'
import useModal from '@/composables/useModal'
import BranchModalBody from './BranchModalBody.vue'
import api from '@/services/api'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'

const toast = useToast()
const actionRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

const { openModal } = useModal()

async function openEditBranch(branchId: number) {
    const result = await openModal(markRaw(BranchModalBody), { branchId }, { header: 'Upraviť pobočku', style: { width: '90%' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Pobočka bola upravená' })
        actionRemote.value?.reload()
    }
}

async function openCreateBranch() {
    const result = await openModal(markRaw(BranchModalBody), {}, { header: 'Pridať pobočku', style: { width: '90%' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Pobočka bola vytvorená' })
        actionRemote.value?.reload()
    }
}

const options = computed<DataTableOptions<Branch>>(() => ({
    rowKey: 'id',
    endpointUrl: 'v1/my-company/branches',
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    extraParams: { with: 'representative', count:'users' },
    afterInit: ({ remote }) => {
        actionRemote.value = remote
    },
    columns: [
        { field: 'address', header: 'Adresa', sortable: false, render: (_v, row: Branch) => `${row.address || ''} ${row.city ? ', ' + row.city : ''}` },
        {field: 'city', header: 'Mesto', sortable: true },
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'representative', header: 'Obozorný zástupca', sortable: false, render: (v: User) => v ? `${v.first_name} ${v.last_name}` : '' },
        { field: 'users_count', header: 'Počet zamestnancov', sortable: true },
        { field: 'edit', header: '', width: '3rem', component: ActionButtons, componentOptions: [
            { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: Branch) => openEditBranch(row.id) }
        ] }
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-warning!',
            confirm: 'Zmazať vybrané pobočky?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/branches', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            }
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => {
                await openCreateBranch()
            }
        }
    ]
}))
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :options="options" />
    </div>
</template>

<style scoped>
.text-muted { color: #6b7280; }
</style>
