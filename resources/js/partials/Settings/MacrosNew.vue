<script setup lang="ts">
import { ref } from 'vue';
import UniversalDataTable from '@/components/UniversalDataTable.vue';
import SecondaryNavbar from '@/components/SecondaryNavbar.vue';
import type { DataTableOptions } from '@/types/datatable';

// typed options for macros table
const options = ref<DataTableOptions<Macro>>({
  rowKey: 'id',
  endpointUrl: 'v1/macros',
  defaultPageSize: 25,
  pageSizeOptions: [10, 25, 50],
  selectable: true,
  columns: [
    { field: 'id', header: 'ID' },
    { field: 'name', header: 'Názov', sortable: true },
    { field: 'abbreviation', header: 'Skratka' },
    { field: 'text', header: 'Text' },
  ],
  actions: [
    {
      key: 'delete',
      label: 'Vymazať',
      icon: 'bi bi-eraser',
      disabled: ({ selectedRows }) => !selectedRows || selectedRows.length === 0,
      confirm: 'Naozaj vymazať vybrané makrá?',
      handler: async ({ remote, selectedRows }: any) => {
        // simple delete flow: call API for each selected and reload
        try {
          for (const r of selectedRows ?? []) {
            await fetch(`/v1/macros/${r.id}`, { method: 'DELETE' });
          }
        } catch (err) {
          console.error('Delete failed', err);
        } finally {
          await remote.loadPage(1);
        }
      },
    },
    {
      key: 'add',
      label: 'Pridať',
      icon: 'bi bi-plus',
      handler: async ({ remote }: any) => {
        // Demo: create a dummy macro and reload
        try {
          await fetch('/v1/macros', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: 'Nové makro', abbreviation: 'NM', text: '' }) });
        } catch (err) {
          console.error('Add failed', err);
        } finally {
          await remote.loadPage(1);
        }
      },
    },
  ],
});
</script>

<template>
  <div>
    <SecondaryNavbar />
    <div class="p-4">
      <h2 class="text-heading mb-4">Makrá (server-backed) — MacrosNew</h2>

      <UniversalDataTable :options="options" />
    </div>
  </div>
</template>

<style scoped>
.text-heading { font-weight: 600; font-size: 1.125rem }
</style>
