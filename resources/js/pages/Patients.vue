<script setup lang="ts">
import { computed, markRaw } from 'vue'
import { useRoute } from 'vue-router'

import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Doctor, Patient } from '@/types/models'
import { useAuthStore } from '@/stores/auth'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { usePatientStore } from '@/stores/patientStore'
import router from '@/router'
import SecondaryNavbar from '@/components/SecondaryNavbar.vue'
import api from '@/services/api'
import CreatePatientModalBody from './partials/patient/CreatePatientModalBody.vue'
import type { DataTableOptions } from '@/types/datatable'
import useModal from '@/composables/useModal'
import { openPatientDocumentsModal } from '@/helpers/modalHelpers'


// Simple formatter
function formatBirthNumber(value?: string) {
    const digits = (value || '').replace(/\D/g, '')
    const first = digits.slice(0, 6)
    const last = digits.slice(6, 10)
    return last.length ? `${first}/${last}` : first
}

const patientStore = usePatientStore()

// still used for Edit/Create
const { openModal } = useModal()

const authStore = useAuthStore()
const branchId = computed(() => authStore.currentBranch?.id ?? null)
const companyId = computed(() => authStore.user?.company?.id ?? null)
const tableKey = computed(() => `patients-${branchId.value ?? 'none'}`)

const route = useRoute()

function openPatientDocuments(patientId: number) {
    void openPatientDocumentsModal(patientId)
}

function openEditPatient(patientId: number) {
    router.replace({ query: { ...route.query, editPatient: String(patientId) } })
}

const endpointUrl = computed(() => {
    if (branchId.value) {
        return `v1/branches/${branchId.value}/patients`
    } else if (companyId.value) {
        return `v1/companies/${companyId.value}/patients`
    } else {
        return null
    }
})

const options = computed<DataTableOptions<Patient>>(() => ({
    rowKey: 'id',
    endpointUrl: endpointUrl.value || '',
    pageSizeOptions: [10, 25, 50],
    selectable: true,

    columns: [
        { field: 'first_name', header: 'Meno', sortable: true },
        { field: 'last_name', header: 'Priezvisko', sortable: true },
        {
            field: 'personal_number',
            header: 'Rodné číslo',
            sortable: false,
            render: (v) => formatBirthNumber(v),
        },
        {
            field: 'adress',
            header: 'Adresa',
            render: (v) => {
                if (!v) return ''
                const parts = []
                if (v.street) parts.push(v.street)
                if (v.city) parts.push(v.city)
                if (v.zip_code) parts.push(v.zip_code)
                return parts.join(', ')
            },
        },
        { field: 'city', header: 'Mesto', sortable: true },
        {
            field: 'doctor',
            header: 'Ošetrujúci lekár',
            render: (v: Doctor) => (v ? `${v.title} ${v.first_name} ${v.last_name}` : ''),
            sortable: false,
        },
        {
            field: 'pin',
            header: '',
            width: '3rem',
            component: ActionButtons,
            componentOptions: [
                {
                    icon: (row: Patient) =>
                        patientStore.current?.id === row.id ? 'bi bi-pin-fill' : 'bi bi-pin',
                    color: 'info',
                    tooltip: 'Pripnúť pacienta',
                    action: (row: Patient) => {
                        patientStore.setPatient(row)
                        router.push(`patient/points`)
                    },
                },
            ],
        },
        {
            field: 'documents',
            header: '',
            width: '3rem',
            component: ActionButtons,
            componentOptions: [
                {
                    icon: 'bi bi-folder',
                    color: 'info',
                    tooltip: 'Zobraziť dokumenty',
                    action: (row: Patient) => {
                        openPatientDocuments(row.id)
                    },
                },
            ],
        },
        {
            field: 'edit',
            header: '',
            width: '3rem',
            component: markRaw(ActionButtons),
            componentOptions: [
                {
                    icon: 'bi bi-pencil',
                    color: 'info',
                    tooltip: 'Editovať pacienta',
                    action: (row: Patient) => {
                        openEditPatient(row.id)
                    },
                },
            ],
        },
    ],

    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-warning!',
            confirm: 'Delete selected?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/patients', {
                    data: { ids: selectedRows.map((r) => r.id) },
                })
                await remote.loadPage(remote.page.value)
            },
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => {
                await openModal(
                    markRaw(CreatePatientModalBody),
                    { title: 'Pridať Pacienta' },
                    { style: { width: '90%' } },
                )
            },
        },
    ],
}))
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <SecondaryNavbar />

        <UniversalDataTable v-if="options.endpointUrl" :key="tableKey" :options="options" ref="tableEl"
            @action="(key, payload) => console.log('action emitted', key, payload)">
            <template #col-personal_number="{ value }">
                <span class="text-muted">{{ formatBirthNumber(value) }}</span>
            </template>
        </UniversalDataTable>

        <div v-else class="p-4 text-darkgrey">
            Loading branch...
        </div>
    </div>
</template>

<style scoped>
.text-muted {
    color: #6b7280;
}
</style>
