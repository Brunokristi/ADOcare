<script setup lang="ts">
import { ref, onMounted, markRaw } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ProcedureForm from './ProcedureForm.vue'
import type { ColumnDef, DataTableOptions } from '@/types/datatable'
import api from '@/services/api'
import type { InsuranceCompany, Procedure } from '@/types/models'
import useModal from '@/composables/useModal'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { useAuthStore } from '@/stores/auth'

const { openModal } = useModal()
const authStore = useAuthStore()
const actionRemote = ref<any | null>(null)

async function openCreate() {
    try {
        const res = await openModal(markRaw(ProcedureForm), { procedure: null }, { header: 'Výkon', style: { width: '760px' }, closable: true })
        if (res?.changed && actionRemote.value?.reload) {
            await actionRemote.value.reload()
        }
    } catch (e) {
        console.error('Failed to open procedure modal', e)
    }
}

async function openEdit(row: Procedure) {
    try {
        const res = await openModal(markRaw(ProcedureForm), { procedure: row }, { header: 'Výkon', style: { width: '760px' }, closable: true })
        if (res?.changed && actionRemote.value?.reload) {
            await actionRemote.value.reload()
        }
    } catch (e) {
        console.error('Failed to open procedure modal', e)
    }
}

const companies = ref<InsuranceCompany[]>([])

onMounted(async () => {
    try {
        companies.value = (await api.fetchEntities<InsuranceCompany>('/v1/insurance-companies'))

        const companyCols = companies.value.map((c) => {
            const ret: ColumnDef<Procedure> = {
                field: `price_${c.id}`,
                header: (c.name ?? c.code ?? String(c.id)).split(' ')[0],
                width: '120px',
                render: (_value, row) => {
                    const existing = row.insurance_companies_prices_minimal ?? []
                    const found: any = existing.find((p: any) => Number(p.id) === Number(c.id))
                    const price = found ? (found.pivot?.price ?? null) : null

                    if (price === null || price === undefined) {
                        return '-'
                    }

                    return `
                        <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                            <span>${Number(price).toFixed(2)}</span>
                            <span class="text-normal text-darkgrey">€</span>
                        </div>
                    `
                }
            }
            return ret
        })

        const editColumn: ColumnDef<Procedure> = {
            field: 'edit',
            header: '',
            width: '3rem',
            component: markRaw(ActionButtons),
            componentOptions: [
                {
                    color: 'info',
                    icon: 'bi bi-pencil',
                    tooltip: 'Upraviť',
                    action: (row: Procedure) => openEdit(row),
                }
            ]
        }

        const columns = [
            { field: 'code', header: 'Kód', sortable: true },
            { field: 'description', header: 'Popis' },
            ...companyCols,
        ]

        if (authStore.isManager || authStore.isSuperadmin) {
            columns.push(editColumn)
        }

        options.value.columns = columns
    } catch (e) {
        console.error('Failed to load companies', e)
        companies.value = []
    }
})

const options = ref<DataTableOptions<any>>({
    rowKey: 'id',
    endpointUrl: 'v1/procedures',
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: authStore.isSuperadmin,
    columns: [
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'description', header: 'Popis' },
        { field: 'prices_summary', header: 'Ceny', width: '30%' },
    ],
    afterInit: ({ remote }) => { actionRemote.value = remote },
    actions: authStore.isSuperadmin ? [
        {
            key: 'delete',
            label: '',
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            tooltip: 'Vymazať vybrané výkony',
            disabled: ({ selectedRows }) => !selectedRows || selectedRows.length === 0,
            confirm: 'Naozaj vymazať vybrané výkony?',
            handler: async ({ remote, selectedRows }: any) => {
                try {
                    for (const r of selectedRows ?? []) {
                        await api.delete(`/v1/procedures/${r.id}`)
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
            tooltip: 'Pridať nový výkon',
            icon: 'bi bi-plus',
            class: 'bg-accent!',
            handler: async () => {
                openCreate()
            },
        },
    ] : [],
})
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :options="options" />
    </div>
</template>

<style scoped>
.text-heading {
    font-weight: 600;
    font-size: 1.125rem
}
</style>
