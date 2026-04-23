<script setup lang="ts">
import { markRaw, ref } from 'vue'
import useModal from '@/composables/useModal'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import api from '@/services/api'
import SubscriptionTierForm from './SubscriptionTierForm.vue'
import CompanySubscriptionForm from './CompanySubscriptionForm.vue'
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

interface CompanySubscription {
    id: number
    name: string
    ico: string | null
    users_count?: number
    subscription_tier_id: number | null
    subscription_status: 'active' | 'trial' | 'paused' | 'cancelled'
    subscription_price_monthly: number | null
    subscription_users_limit_override: number | null
    subscription_tier?: {
        id: number
        name: string
        price_monthly: number | null
        users_limit: number | null
    } | null
}

const { openModal } = useModal()

const tierRemote = ref<any | null>(null)
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

async function openEditCompanySubscription(row: CompanySubscription) {
    const res = await openModal(
        markRaw(CompanySubscriptionForm),
        { company: row },
        { header: 'Správa predplatného spoločnosti', style: { width: '760px' }, closable: true }
    )

    if (res?.changed && companyRemote.value?.reload) {
        await companyRemote.value.reload()
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
            field: 'companies_count',
            header: 'Počet spoločností',
            sortable: true,
            render: (value) => String(value ?? 0),
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
            header: 'Balík',
            sortable: true,
            render: (_value, row) => row.subscription_tier?.name ?? '-',
        },
        {
            field: 'subscription_price_monthly',
            header: 'Cena (override)',
            sortable: true,
            render: (value, row) => {
                const effective = value ?? row.subscription_tier?.price_monthly ?? null
                return formatMoney(effective)
            },
        },
        {
            field: 'subscription_users_limit_override',
            header: 'Limit (override)',
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
            field: 'users_count',
            header: 'Používatelia',
            sortable: true,
            render: (value) => String(value ?? 0),
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
                    tooltip: 'Správa predplatného',
                    action: (row: CompanySubscription) => openEditCompanySubscription(row),
                },
            ],
        },
    ],
})
</script>

<template>
    <div class="h-full flex flex-col gap-4 overflow-hidden min-h-0">
        <div class="text-lg font-semibold">Balíky predplatného</div>
        <div class="min-h-[280px]">
            <UniversalDataTable :options="tierOptions" />
        </div>

        <div class="text-lg font-semibold mt-4">Predplatné spoločností</div>
        <div class="flex-1 min-h-[320px] overflow-hidden">
            <UniversalDataTable :options="companyOptions" />
        </div>
    </div>
</template>
