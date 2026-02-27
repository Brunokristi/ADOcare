<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Company } from '@/types/models'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { useToast } from 'primevue/usetoast'
import useModal from '@/composables/useModal'
import CompanyForm from './CompanyForm.vue'
import api from '@/services/api'

const toast = useToast()
const actionRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)
const { openModal } = useModal()

async function openEditCompany(companyId: number) {
    const result = await openModal(markRaw(CompanyForm), { companyId }, { header: 'Upraviť spoločnosť', style: { width: '90%' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Spoločnosť bola upravená', life: 3000 })
        actionRemote.value?.reload()
    }
}

async function openCreateCompany() {
    const result = await openModal(markRaw(CompanyForm), {}, { header: 'Pridať spoločnosť', style: { width: '90%' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Spoločnosť bola vytvorená', life: 3000 })
        actionRemote.value?.reload()
    }
}

const options = computed<DataTableOptions<Company>>(() => ({
    rowKey: 'id',
    endpointUrl: 'v1/companies',
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    afterInit: ({ remote }) => {
        actionRemote.value = remote
    },
    columns: [
        { field: 'name', header: 'Názov', sortable: true },
        { field: 'ico', header: 'IČO', sortable: true },
        { field: 'code', header: 'Kód', sortable: true },
        {
            field: 'edit', header: '', width: '3rem', component: ActionButtons, componentOptions: [
                { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: Company) => openEditCompany(row.id) }
            ]
        }
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-warning!',
            confirm: 'Zmazať vybrané spoločnosti?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/companies', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            }
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => {
                await openCreateCompany()
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
