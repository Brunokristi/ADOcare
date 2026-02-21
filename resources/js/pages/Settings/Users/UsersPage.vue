<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Role, User } from '@/types/models'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { useToast } from 'primevue/usetoast'
import useModal from '@/composables/useModal'
import UserModalBody from './UserModalBody.vue'
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'

const toast = useToast()
const auth = useAuthStore()
const actionRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

const { openModal } = useModal()

const tableKey = computed(() => `users-${auth.currentBranch?.id ?? 'global'}`)

async function openEditUser(userId: number) {
    const result = await openModal(markRaw(UserModalBody), { userId }, { header: 'Upraviť používateľa', style: { width: '800px' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Používateľ bol uložený', life: 3000 })
        actionRemote.value?.reload()
    }
}

async function openCreateUser() {
    const result = await openModal(markRaw(UserModalBody), {}, { header: 'Pridať používateľa', style: { width: '800px' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Používateľ bol vytvorený' })
        actionRemote.value?.reload()
    }
}

const options = computed<DataTableOptions<User>>(() => ({
    rowKey: 'id',
    endpointUrl: 'v1/my-company/users',
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    extraParams: { with: 'role' },
    selectable: true,
    afterInit: ({ remote }) => {
        actionRemote.value = remote
    },
    columns: [
        { field: 'first_name', header: 'Meno', sortable: true },
        { field: 'last_name', header: 'Priezvisko', sortable: true },
        { field: 'title', header: 'Titul', sortable: false },
        { field: 'code', header: 'Kód', sortable: true },
        // previously we exposed a virtual `system_role` field coming from the
        // old pivot; the API now returns the related `role` object directly.
        { field: 'role', header: 'Systémová rola', sortable: true, render: (v: Role) => v ? v.name : '' },
        { field: 'phone_number', header: 'Telefón', sortable: false },
        { field: 'email', header: 'Email', sortable: false },
        {
            field: 'edit', header: '', width: '3rem', component: markRaw(ActionButtons), componentOptions: [
                { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: User) => openEditUser(row.id) }
            ]
        }
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-warning!',
            confirm: 'Naozaj vymazať vybraných používateľov?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/users', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            }
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => {
                await openCreateUser()
            }
        }
    ]
}))

</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :key="tableKey" :options="options" />
    </div>
</template>

<style scoped>
.text-muted {
    color: #6b7280;
}
</style>
