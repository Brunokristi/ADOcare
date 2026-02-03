<script setup lang="ts">
import { ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { Diagnosis } from '@/types/models'
import type { DataTableOptions } from '@/types/datatable'

const options = ref<DataTableOptions<Diagnosis>>({
    rowKey: 'id',
    endpointUrl: 'v1/diagnoses',
    defaultPageSize: 50,
    pageSizeOptions: [25, 50, 100],
    selectable: false,
    columns: [
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'description', header: 'Popis', sortable: true },
    ],
    // No actions — read-only view
})
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden min-h-0">
        <UniversalDataTable :options="options" />
    </div>
</template>
