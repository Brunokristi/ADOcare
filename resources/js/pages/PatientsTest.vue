<script setup lang="ts">
import { ref, shallowRef, computed, markRaw } from 'vue';
import UniversalDataTable from '@/components/UniversalDataTable.vue';
import type { Patient } from '@/types/models';
import { useAuthStore } from '@/stores/auth';
import ActionButtons from '@/components/table-columns/ActionButtons.vue';
import { usePatientStore } from '@/stores/patientStore';
import router from '@/router';
import EditPatientDialog from './partials/patient/EditPatientModalBody.vue';
import useModal from '@/composables/useModal';
import SecondaryNavbar from '@/components/SecondaryNavbar.vue';
import api from '@/services/api';
import CreatePatientModalBody from './partials/patient/CreatePatientModalBody.vue';

// Simple formatter
function formatBirthNumber(value?: string) {
    const digits = (value || '').replace(/\D/g, '');
    const first = digits.slice(0, 6);
    const last = digits.slice(6, 10);
    return last.length ? `${first}/${last}` : first;
}

const patientStore = usePatientStore();

const { openModal } = useModal();

const authStore = useAuthStore();
const branchId = computed(() => authStore.currentBranch?.id ?? null);

// remount table whenever branch changes (forces remote table to re-init)
const tableKey = computed(() => `patients-${branchId.value ?? 'none'}`);

// IMPORTANT: options must be computed so endpointUrl updates when branchId is ready
const options = computed<DataTableOptions<Patient>>(() => ({
    rowKey: 'id',

    // if branch not ready, keep it empty so no request is made
    endpointUrl: branchId.value ? `v1/branches/${branchId.value}/patients` : '',

    defaultPageSize: 25,
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
            field: 'sex',
            header: 'Pohlavie',
            render: (v) => (v === 'M' ? 'Muž' : 'Žena'),
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
                        patientStore.setPatient(row);
                        router.push(`patient/points`);
                    },
                },
            ],
        },
        {
            field: 'edit',
            header: '',
            width: '3rem',
            component: ActionButtons,
            componentOptions: [
                {
                    icon: 'bi bi-pencil',
                    color: 'info',
                    tooltip: 'Editovať pacienta',
                    action: (row: Patient) => {
                        // open the edit dialog programmatically; the dialog component expects `patientId` prop
                        openModal(markRaw(EditPatientDialog), { patientId: row.id }, { style: { width: "90%" } }).then(() => {
                            // optionally handle result after close
                        });
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

                const response = api.delete('v1/patients', {
                    data: {
                        ids: selectedRows.map((r) => r.id),
                    },
                });

                await remote.loadPage(remote.page);
            },
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async ({ remote }) => {
                console.log('Add action triggered');

                await openModal(markRaw(CreatePatientModalBody), {}, { style: { width: "90%" } });

                // await remote.loadPage(1);
            },
        },
    ],
}));





</script>

<template>
    <div>
        <SecondaryNavbar />

        <!-- Only mount the table when branchId is ready -->
        <UniversalDataTable v-if="options.endpointUrl" :key="tableKey" :options="options" ref="tableEl"
            @action="(key, payload) => console.log('action emitted', key, payload)">
            <template #col-personal_number="{ value }">
                <span class="text-muted">{{ formatBirthNumber(value) }}</span>
            </template>
        </UniversalDataTable>

        <!-- fallback while branch isn't ready -->
        <div v-else class="p-4 text-darkgrey">
            Loading branch...
        </div>

        <!-- ModalProvider renders programmatic modals. EditPatientDialog will be opened via `useModal.open()` -->
    </div>
</template>

<style scoped>
.text-muted {
    color: #6b7280;
}
</style>
