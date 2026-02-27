<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import { useToast } from 'primevue/usetoast'
import useModal from '@/composables/useModal'
import TotalsForm from './TotalsForm.vue'
import api from '@/services/api'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'

interface Total {
    id: number
    user_id?: number
    branch_id?: number
    month: string
    insurance_company_id?: number
    points_total: number
    kilometers_total: number
    user?: { id: number; first_name: string; last_name: string }
    branch?: { id: number; address: string }
    insuranceCompany?: { id: number; name: string }
}

const toast = useToast()
const actionRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

const { openModal } = useModal()

async function openEditTotal(totalId: number) {
    const result = await openModal(
        markRaw(TotalsForm),
        { totalId },
        { header: 'Upraviť hodnotu', style: { width: '600px' } }
    )
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Hodnota uložená', life: 3000 })
        actionRemote.value?.reload()
    }
}

async function openCreateTotal() {
    const result = await openModal(
        markRaw(TotalsForm),
        {},
        { header: 'Pridať hodnotu', style: { width: '600px' } }
    )
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Hodnota vytvorená', life: 3000 })
        actionRemote.value?.reload()
    }
}

const formatUserFullName = (user: { first_name?: string; last_name?: string } | undefined) => {
    if (!user) return ''
    return `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim()
}

const options = computed<DataTableOptions<Total>>(() => ({
    rowKey: 'id',
    endpointUrl: 'v1/totals',
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    extraParams: { with: 'user,branch,insurance_company' },
    afterInit: ({ remote }) => {
        actionRemote.value = remote
    },
    columns: [
        { field: 'month', header: 'Mesiac', sortable: true },
        {
            field: 'user',
            header: 'Užívateľ',
            render: (v: Total['user']) => formatUserFullName(v),
            sortable: false,
        },
        { field: 'branch', header: 'Pobočka', render: (v: Total['branch']) => v?.address ?? '', sortable: false },
        { field: 'insurance_company', header: 'Poisťovňa', render: (v: any) => v?.name.split(' ')[0] ?? '', sortable: false },
        { field: 'points_total', header: 'Body', sortable: true, align: 'right' },
        { field: 'kilometers_total', header: 'Kilometre', sortable: true, align: 'right' },
        {
            field: 'edit',
            header: '',
            width: '3rem',
            component: markRaw(ActionButtons),
            componentOptions: [
                { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: Total) => openEditTotal(row.id) },
            ],
        },
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-warning!',
            confirm: 'Zmazať vybrané položky?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/totals', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            },
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => {
                await openCreateTotal()
            },
        },
    ],
}))
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :options="options" />
    </div>
</template>
