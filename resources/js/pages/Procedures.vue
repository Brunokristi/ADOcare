<script setup lang="ts">
import { ref } from 'vue'
import type { AdoTableColumn } from '@/components/ado/AdoTable.vue'

const rows = ref([
  {
    id: 1,
    code: '713482',
    price25: '713482',
    price24: '713482',
    price27: '713482',
    description: 'Bla bla bla',
  },
    {
        id: 2,
        code: '852369',
        price25: '852369',
        price24: '852369',
        price27: '852369',
        description: 'Dalsi bla bla bla',
    },
    {
        id: 3,
        code: '963258',
        price25: '963258',
        price24: '963258',
        price27: '963258',
        description: 'Este dalsi bla bla bla',
    },
])

const columns: AdoTableColumn[] = [
  { field: 'code', header: 'Kód' },
  { field: 'price25', header: 'Cena poisťovňa 25' },
  { field: 'price24', header: 'Cena poisťovňa 24', disabled: true },
  { field: 'price27', header: 'Cena poisťovňa 27', disabled: true },
  { field: 'description', header: 'Popis' },
]


const onAdd = () => {
  console.log('ADD clicked')
}

const onDeleteSelected = (selected: any[]) => {
  console.log('DELETE SELECTED', selected)
}

const onRowClick = (row: any) => {
  console.log('ROW CLICK', row)
}

const onRowUpdate = (row: any) => {
  console.log('UPDATE ROW', row)
}

const onEdit = (row: any) => {
    console.log('edit', row)
}

</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-4">Procedures table – demo</h1>

    <AdoTable
      :rows="rows"
      :columns="columns"
      selectable
      searchable
      show-add-button
      show-delete-button
      show-row-actions
      @add="onAdd"
      @delete-selected="onDeleteSelected"
      @row-click="onRowClick"
      @row-update="onRowUpdate"
    >
      <template #row-actions="{ data }">
        <button class="ado-table__row-action-btn" @click.stop="onEdit(data)">
          <i class="bi bi-pencil" />
        </button>
      </template>
    </AdoTable>
  </div>
</template>
