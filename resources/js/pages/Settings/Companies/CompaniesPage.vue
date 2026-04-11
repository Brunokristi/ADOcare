<script setup lang="ts">
import { computed, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Company } from '@/types/models'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import api from '@/services/api'
import router from '@/router'

const actionRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

async function openEditCompany(companyId: number) {
    router.push({ name: 'superadmin-company-edit', params: { companyId } })
}

async function openCreateCompany() {
    router.push({ name: 'superadmin-company-create' })

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
        {
            field: 'name', header: 'Názov', sortable: true,
            slot: 'col-name',
        },
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
            class: 'bg-danger!',
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
        <UniversalDataTable :options="options">
            <template #col-name="{ row }">
                <RouterLink :to="{ name: 'superadmin-company-overview', params: { companyId: row.id } }"
                    class="text-accent underline">
                    {{ row.name }}
                </RouterLink>
            </template>
        </UniversalDataTable>
    </div>
</template>
