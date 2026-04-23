<script setup lang="ts">
import { markRaw, ref } from 'vue'
import useModal from '@/composables/useModal'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import api from '@/services/api'
import SubscriptionTierForm from './SubscriptionTierForm.vue'
import type { DataTableOptions } from '@/types/datatable'

interface SubscriptionTier {
    id: number
    name: string
    price_monthly: number | null
    users_limit: number | null
    description: string | null
    is_active: boolean
    sort_order: number
    companies_count?: number
}

const { openModal } = useModal()
const tierRemote = ref<any | null>(null)

function formatMoney(value: number | null | undefined) {
    if (value === null || value === undefined) return '-'
    return `${Number(value).toFixed(2)} €`
}

function formatUsersLimit(value: number | null | undefined) {
    if (!value) return 'Neobmedzene'
    return `${value} používateľov`
}

async function openCreateTier() {
    const res = await openModal(
        markRaw(SubscriptionTierForm),
        { tier: null },
        { header: 'Nový balík', style: { width: '720px' }, closable: true }
    )

    if (res?.changed && tierRemote.value?.reload) {
        await tierRemote.value.reload()
    }
}

async function openEditTier(row: SubscriptionTier) {
    const res = await openModal(
        markRaw(SubscriptionTierForm),
        { tier: row },
        { header: 'Upraviť balík', style: { width: '720px' }, closable: true }
    )

    if (res?.changed && tierRemote.value?.reload) {
        await tierRemote.value.reload()
    }
}

const tierOptions = ref<DataTableOptions<SubscriptionTier>>({
    rowKey: 'id',
    endpointUrl: 'v1/subscription-tiers',
    defaultPageSize: 10,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    afterInit: ({ remote }) => {
        tierRemote.value = remote
    },
    columns: [
        { field: 'name', header: 'Balík', sortable: true },
        {
            field: 'price_monthly',
            header: 'Cena / mesiac',
            sortable: true,
            render: (value) => formatMoney(value),
        },
        {
            field: 'users_limit',
            header: 'Limit používateľov',
            sortable: true,
            render: (value) => formatUsersLimit(Number(value)),
        },
        {
            field: 'edit',
            header: '',
            width: '3rem',
            component: markRaw(ActionButtons),
            componentOptions: [
                {
                    color: 'info',
                    icon: 'bi bi-pencil',
                    tooltip: 'Upraviť balík',
                    action: (row: SubscriptionTier) => openEditTier(row),
                },
            ],
        },
    ],
    actions: [
        {
            key: 'delete',
            label: '',
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            tooltip: 'Vymazať vybrané balíky',
            disabled: ({ selectedRows }) => !selectedRows || selectedRows.length === 0,
            confirm: 'Naozaj vymazať vybrané balíky?',
            handler: async ({ remote, selectedRows }: any) => {
                await api.delete('/v1/subscription-tiers', {
                    data: {
                        ids: selectedRows.map((r: SubscriptionTier) => r.id),
                    },
                })

                await remote.loadPage(1)
            },
        },
        {
            key: 'add',
            label: '',
            tooltip: 'Pridať nový balík',
            icon: 'bi bi-plus',
            class: 'bg-accent!',
            handler: async () => {
                await openCreateTier()
            },
        },
    ],
})
</script>

<template>
    <div class="h-full flex flex-col gap-4 overflow-hidden min-h-0">
        <div class="flex-1 min-h-[320px] overflow-hidden">
            <UniversalDataTable :options="tierOptions" />
        </div>
    </div>
</template>