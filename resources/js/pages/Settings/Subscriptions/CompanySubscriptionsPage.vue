<script setup lang="ts">
import { ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { DataTableOptions } from '@/types/datatable'

interface CompanySubscription {
    id: number
    name: string
    ico: string | null
    users_count?: number
    subscription_tier_id: number | null
    subscription_status: 'active' | 'trial' | 'paused' | 'cancelled'
    subscription_price_monthly: number | null
    subscription_users_limit_override: number | null
    billing_provisioned: boolean
    subscription_tier?: {
        id: number
        name: string
        price_monthly: number | null
        users_limit: number | null
    } | null
}

const companyRemote = ref<any | null>(null)

function formatMoney(value: number | null | undefined) {
    if (value === null || value === undefined) return '-'
    return `${Number(value).toFixed(2)} €`
}

function formatUsersLimit(value: number | null | undefined) {
    if (!value) return 'Neobmedzene'
    return `${value} používateľov`
}

function formatStatus(value: string | null | undefined) {
    switch (value) {
        case 'active': return 'Aktívne'
        case 'trial': return 'Trial'
        case 'paused': return 'Pozastavené'
        case 'cancelled': return 'Zrušené'
        default: return '-'
    }
}

const companyOptions = ref<DataTableOptions<CompanySubscription>>({
    rowKey: 'id',
    endpointUrl: 'v1/companies/subscriptions',
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: false,
    afterInit: ({ remote }) => {
        companyRemote.value = remote
    },
    columns: [
        { field: 'name', header: 'Spoločnosť', sortable: true },
        { field: 'ico', header: 'IČO', sortable: true },
        {
            field: 'subscription_tier_id',
            header: 'Balík (legacy)',
            sortable: true,
            render: (_value, row) => row.subscription_tier?.name ?? '-',
        },
        {
            field: 'subscription_price_monthly',
            header: 'Cena (legacy)',
            sortable: true,
            render: (value, row) => {
                const effective = value ?? row.subscription_tier?.price_monthly ?? null
                return formatMoney(effective)
            },
        },
        {
            field: 'subscription_users_limit_override',
            header: 'Limit (legacy)',
            sortable: true,
            render: (value, row) => {
                const effective = value ?? row.subscription_tier?.users_limit ?? null
                return formatUsersLimit(Number(effective))
            },
        },
        {
            field: 'subscription_status',
            header: 'Stav',
            sortable: true,
            render: (value) => formatStatus(String(value)),
        },
        {
            field: 'billing_provisioned',
            header: 'Zdroj fakturácie',
            sortable: true,
            render: (value) => (value ? 'StudioKristian' : 'Legacy (lokálne)'),
        },
        {
            field: 'users_count',
            header: 'Používatelia',
            sortable: true,
            render: (value) => String(value ?? 0),
        },
    ],
})
</script>

<template>
    <div class="h-full flex flex-col gap-4 overflow-hidden min-h-0">
        <div class="rounded-md bg-tag3 p-4 text-normal text-lightgrey">
            Toto je len historický/read-only prehľad. Aktívne platené predplatné, ceny a fakturáciu
            spravuje StudioKristian - Superadmin ich už nemôže meniť priamo v ADOCare.
        </div>

        <div class="flex-1 min-h-[320px] overflow-hidden">
            <UniversalDataTable :options="companyOptions" />
        </div>
    </div>
</template>