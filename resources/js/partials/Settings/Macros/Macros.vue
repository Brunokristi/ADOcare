<script setup lang="ts">
import { ref } from 'vue'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import MacroForm from '@/partials/Settings/Macros/MacroForm.vue'
import type { Macro } from '@/types/models'

// UI state for modal
const showMacroDialog = ref(false)
const editingMacro = ref<Partial<Macro> | null>(null)
const actionRemote = ref<any>(null) // remote controller passed from UniversalDataTable actions

function openCreate(remote?: any) {
  editingMacro.value = { name: '', abbreviation: '', text: '' }
  actionRemote.value = remote ?? null
  showMacroDialog.value = true
}

function openEdit({ selectedRows, remote }: any) {
  const first = selectedRows?.[0] ?? null
  if (!first) return
  editingMacro.value = { ...first }
  actionRemote.value = remote ?? null
  showMacroDialog.value = true
}

async function onSaveMacro() {
  // after save, reload table if remote provided
  try {
    if (actionRemote.value?.loadPage) {
      await actionRemote.value.loadPage(1)
    }
  } finally {
    showMacroDialog.value = false
    editingMacro.value = null
    actionRemote.value = null
  }
}

async function onCloseDialog() {
  showMacroDialog.value = false
  editingMacro.value = null
  actionRemote.value = null
}

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
    { field: 'text', width: '50%', header: 'Text' },
  ],
  actions: [
    {
      key: 'edit',
      label: '',
      icon: 'bi bi-pencil',
      disabled: ({ selectedRows }) => !selectedRows || selectedRows.length !== 1,
      handler: async (ctx: any) => {
        openEdit(ctx)
      },
    },
    {
      key: 'delete',
      label: '',
      icon: 'bi bi-eraser',
      disabled: ({ selectedRows }) => !selectedRows || selectedRows.length === 0,
      confirm: 'Naozaj vymazať vybrané makrá?',
      handler: async ({ remote, selectedRows }: any) => {
        try {
          for (const r of selectedRows ?? []) {
            await fetch(`/v1/macros/${r.id}`, { method: 'DELETE' })
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
      handler: async (ctx: any) => {
        openCreate(ctx.remote)
      },
    },
  ],
})
</script>

<template>
  <div class="h-full flex flex-col overflow-hidden min-h-0">

      <UniversalDataTable :options="options" />

      <MacroForm
        v-if="showMacroDialog"
        :macro="editingMacro"
        @save="onSaveMacro"
        @close="onCloseDialog"
      />
    </div>
</template>

<style scoped>
.text-heading { font-weight: 600; font-size: 1.125rem }
</style>
