<script setup lang="ts">
import { ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
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

function formatMoney(value: number | null | undefined) {
    if (value === null || value === undefined) return '-'
    return `${Number(value).toFixed(2)} €`
}

function formatUsersLimit(value: number | null | undefined) {
    if (!value) return 'Neobmedzene'
    return `${value} používateľov`
}

const tierOptions = ref<DataTableOptions<SubscriptionTier>>({
    rowKey: 'id',
    endpointUrl: 'v1/subscription-tiers',
    defaultPageSize: 10,
    pageSizeOptions: [10, 25, 50],
    selectable: false,
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
    ],
})
</script>

<template>
    <div class="h-full flex flex-col gap-4 overflow-hidden min-h-0">
        <div class="rounded-md bg-tag3 p-4 text-normal text-lightgrey">
            Toto sú legacy balíky - iba na historický prehľad. Nové balíky, ceny a ich správu
            vykonáva StudioKristian.
        </div>

        <div class="flex-1 min-h-[320px] overflow-hidden">
            <UniversalDataTable :options="tierOptions" />
        </div>
    </div>
</template>