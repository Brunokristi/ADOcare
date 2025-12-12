<script setup lang="ts">
import { ref, onMounted } from 'vue';
import UniversalDataTable from '@/components/UniversalDataTable.vue';
import type { Patient } from '@/types/models';
import useAuthStore from '@/stores/auth';
import ActionButtons from '@/components/table-columns/ActionButtons.vue';

// Simple formatter used in the example
function formatBirthNumber(value?: string) {
    const digits = (value || '').replace(/\D/g, '');
    const first = digits.slice(0, 6);
    const last = digits.slice(6, 10);
    return last.length ? `${first}/${last}` : first;
}

const tableEl = ref<InstanceType<typeof UniversalDataTable> | null>(null);

// Typed options for the universal table — notice the generic here
const branchId = useAuthStore().currentBranch?.id || 'null';
const options = ref<DataTableOptions<Patient>>({
    rowKey: 'id',
    endpointUrl: `v1/branches/${branchId}/patients`,
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    columns: [
        { field: 'first_name', header: 'Meno', sortable: true },
        { field: 'last_name', header: 'Priezvisko', sortable: true },
        { field: 'personal_number', header: 'Rodné číslo', sortable: false, render: (v) => formatBirthNumber(v) },
        { field: 'sex', header: 'Pohlavie', render: (v) => v === 'M' ? 'Muž' : 'Žena' },
        {
            field: 'edit', header: 'Akcie', component: ActionButtons, componentOptions: [
                {
                    icon: 'bi bi-pencil',
                    color: 'info',
                    tooltip: 'Editovať pacienta',
                    action: (row: Patient) => {
                        console.log('Edit patient', row);
                    }
                },
                {
                    icon: 'bi bi-eye',
                    color: 'success',
                    tooltip: 'Zobraziť detaily pacienta',
                    action: (row: Patient) => {
                        console.log('View patient', row);
                    }
                }
            ]
        },
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-warning!',
            confirm: 'Delete selected?',
            handler: async ({ rows, selectedRows, remote }) => {
                // Simulate delete API call
                console.log('Deleting rows:', rows);
                // After deletion, reload the table data
                await remote.loadPage(1);
            }
        }, {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async ({ remote }) => {
                // Simulate add action
                console.log('Add action triggered');
                // After adding, reload the table data
                await remote.loadPage(1);
            }
        },
    ],
});

</script>
<template>
    <div>
        <SecondaryNavbar />
        <UniversalDataTable :options="options" ref="table"
            @action="(key, payload) => console.log('action emitted', key, payload)">
            <!-- Provide a custom slot for the personal_number column to show formatted value -->
            <template #col-personal_number="{ value }">
                <span class="text-muted">{{ formatBirthNumber(value) }}</span>
            </template>
        </UniversalDataTable>
    </div>
</template>

<style scoped>
.text-muted {
    color: #6b7280
}
</style>
