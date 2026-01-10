<script setup lang="ts">
import { ref, onMounted } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ProcedureForm from '@/partials/Settings/Procedures/ProcedureForm.vue'
import type { ColumnDef, DataTableOptions } from '@/types/datatable'
import api from '@/services/api'
import type { InsuranceCompany, Procedure } from '@/types/models'

const showDialog = ref(false)
const editing = ref<any | null>(null)
const actionRemote = ref<any | null>(null)

function openCreate(remote?: any) {
    editing.value = null
    actionRemote.value = remote ?? null
    showDialog.value = true
}

function openEdit({ selectedRows, remote }: any) {
    const first = selectedRows?.[0] ?? null
    if (!first) return
    editing.value = { ...first }
    actionRemote.value = remote ?? null
    showDialog.value = true
}

async function onSave() {
    try {
        if (actionRemote.value?.loadPage) await actionRemote.value.loadPage(1)
    } finally {
        showDialog.value = false
        editing.value = null
        actionRemote.value = null
    }
}

async function onClose() {
    showDialog.value = false
    editing.value = null
    actionRemote.value = null
}

// normalizeRow removed — will use per-column render functions instead

const companies = ref<InsuranceCompany[]>([])

onMounted(async () => {
    try {
        companies.value = (await api.fetchEntities<InsuranceCompany>('/v1/insurance-companies'))

        const companyCols = companies.value.map((c) => {
            const ret: ColumnDef<Procedure> = {
                field: `price_${c.id}`,
                header: c.name ?? c.code ?? String(c.id),
                width: '120px',
                render: (value,row) => {
                    const existing = row.insurance_companies_prices_minimal ?? []
                    const found = existing.find((p: any) => Number(p.id) === Number(c.id))
                    const price = found ? (found.pivot?.price ?? null) : null
                    return price === null || price === undefined ? '-' : String(price)
                }
            }
            return ret
        })

        options.value.columns = [
            { field: 'code', header: 'Kód', sortable: true },
            { field: 'description', header: 'Popis' },
            ...companyCols,
        ]
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
    selectable: true,
    columns: [
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'description', header: 'Popis' },
        { field: 'prices_summary', header: 'Ceny', width: '30%' },
    ],
    actions: [
        {
            key: 'edit',
            label: '',
            icon: 'bi bi-pencil',
            disabled: ({ selectedRows }: any) => !selectedRows || selectedRows.length !== 1,
            handler: async (ctx: any) => openEdit(ctx),
        },
        {
            key: 'delete',
            label: '',
            icon: 'bi bi-eraser',
            disabled: ({ selectedRows }: any) => !selectedRows || selectedRows.length === 0,
            confirm: 'Naozaj vymazať vybrané záznamy?',
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
            icon: 'bi bi-plus',
            handler: async (ctx: any) => openCreate(ctx.remote),
        },
    ],
    // no normalizeRow — use column.render to display company prices
})
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :options="options" />

        <ProcedureForm v-if="showDialog" :procedure="editing" @save="onSave" @close="onClose" />
    </div>
</template>

<style scoped>
.text-heading {
    font-weight: 600;
    font-size: 1.125rem
}
</style>
