<script setup lang="ts">
import { computed, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Company } from '@/types/models'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import api from '@/services/api'
import router from '@/router'
import { formatUserFullName } from '@/utils/formatUtils'

const actionRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)
const deleteDialogVisible = ref(false)
const pendingDeleteCompany = ref<Company | null>(null)

async function openEditCompany(companyId: number) {
    router.push({ name: 'superadmin-company-overview', params: { companyId } })
}

async function openCreateCompany() {
    router.push({ name: 'superadmin-company-create' })
}

const toast = useToast()

function openDeleteCompanyDialog(company: Company) {
    pendingDeleteCompany.value = company
    deleteDialogVisible.value = true
}

function cancelDeleteCompany() {
    deleteDialogVisible.value = false
    pendingDeleteCompany.value = null
}

async function confirmDeleteCompany() {
    const companyId = pendingDeleteCompany.value?.id
    if (!companyId) return

    deleteDialogVisible.value = false

    try {
        await api.delete(`v1/companies/${companyId}`)
        toast.add({ severity: 'success', summary: 'Zmazané', detail: 'Spoločnosť bola odstránená', life: 3000 })
        await actionRemote.value?.reload?.()
    } catch (error) {
        console.error('Failed to delete company', error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa zmazať spoločnosť', life: 5000 })
    } finally {
        pendingDeleteCompany.value = null
    }
}

const options = computed<DataTableOptions<Company>>(() => ({
    rowKey: 'id',
    endpointUrl: 'v1/companies',
    extraParams: {
        with: 'representative',
    },
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
        { field: 'dic', header: 'DIČ', sortable: true },
        {
            field: 'representative_id',
            header: 'Zástupca',
            sortable: true,
            render: (_v: Company['representative_id'], row: Company) =>
                row.representative ? formatUserFullName(row.representative) : '-',
        },
        { field: 'email', header: 'Email', sortable: true },
        {
            field: 'edit', header: '', width: '3rem', component: ActionButtons, componentOptions: [
                { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: Company) => openEditCompany(row.id) }
            ],
        },
        {
            field: 'delete', header: '', width: '3rem', component: ActionButtons, componentOptions: [
                { icon: 'bi bi-eraser', color: 'info', tooltip: 'Zmazať', action: (row: Company) => openDeleteCompanyDialog(row) }
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
        </UniversalDataTable>

        <Dialog
            v-model:visible="deleteDialogVisible"
            :modal="true"
            :closable="false"
            header="Upozornenie"
            :style="{ width: '520px' }"
        >
            <div class="gap-4">
                <span class="text-normal">
                    Naozaj zmazať spoločnosť {{ pendingDeleteCompany?.name ?? '' }}?
                </span>

                <div class="flex items-center gap-2 shrink-0 items-end justify-end mt-4">
                    <Button
                        label="Nie"
                        text
                        class="bg-accent! px-4! text-white! hover:bg-darkgrey! border-0!"
                        @click="cancelDeleteCompany"
                    />
                    <Button
                        label="Áno"
                        text
                        class="bg-danger! px-4! text-white!"
                        @click="confirmDeleteCompany"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>
