<script setup lang="ts">
import { computed, ref, watch, useSlots } from 'vue'

export interface AdoTableColumn {
  field?: string
  header?: string
  width?: string
  sortable?: boolean
  disabled?: boolean
}

const props = defineProps<{
  rows: any[]
  columns: AdoTableColumn[]
  selectable?: boolean
  searchable?: boolean
  showAddButton?: boolean
  showDeleteButton?: boolean
  showRowActions?: boolean
}>()

const emit = defineEmits<{
  (e: 'add'): void
  (e: 'delete-selected', rows: any[]): void
  (e: 'update:selection', rows: any[]): void
  (e: 'row-click', row: any): void
  (e: 'row-update', row: any): void
}>()

const search = ref('')
const selection = ref<any[]>([])
const slots = useSlots()

const filteredRows = computed(() => {
  if (!props.searchable || !search.value) return props.rows

  const q = search.value.toLowerCase()

  return props.rows.filter((row) =>
    props.columns
      .map((c) => (c.field ? row[c.field] : null))
      .filter((v) => v !== null && v !== undefined)
      .some((value) => String(value).toLowerCase().includes(q)),
  )
})

watch(selection, (val) => emit('update:selection', val))

const onAdd = () => emit('add')

const onDeleteSelected = () => {
  if (!selection.value.length) return
  emit('delete-selected', selection.value)
}

const onRowClick = (row: any) => emit('row-click', row)

const onRowUpdate = (row: any) => emit('row-update', row)

const hasToolbarSlot = computed(() => !!slots.toolbar)
const hasRowActionsSlot = computed(() => !!slots['row-actions'])
</script>

<template>
  <div class="w-full font-sans">
    <!-- TOOLBAR -->
    <div
      v-if="searchable || showAddButton || showDeleteButton || hasToolbarSlot"
      class="mb-md flex items-center justify-between gap-md"
    >
      <slot name="toolbar" v-bind="{ search, selection, filteredRows }">
        <!-- LEFT SIDE (custom actions) -->
        <div class="flex items-center gap-sm">
          <slot name="actions" v-bind="{ selection, filteredRows }" />
        </div>

        <!-- RIGHT SIDE (search + buttons) -->
        <div class="flex items-center gap-sm">
          <!-- SEARCH -->
          <div v-if="searchable" class="relative">
            <span
              class="bi bi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[0.75rem] text-lightgrey"
            />
            <input
              v-model="search"
              type="text"
              class="w-44 rounded-full border border-lightgrey px-sm py-xs pl-8 text-[0.75rem] outline-none focus:ring-1 focus:ring-gray-500"
            />
          </div>

          <!-- DELETE BUTTON -->
          <button
            v-if="showDeleteButton"
            type="button"
            @click="onDeleteSelected"
            class="flex h-9 w-9 items-center justify-center rounded-full bg-warning text-white text-base shadow-custom hover:opacity-90 transition"
          >
            <i class="bi bi-eraser text-[0.75rem]" />
          </button>

          <!-- ADD BUTTON -->
          <button
            v-if="showAddButton"
            type="button"
            @click="onAdd"
            class="flex h-9 w-9 items-center justify-center rounded-full bg-accent text-white text-base shadow-custom hover:opacity-90 transition"
          >
            <i class="bi bi-plus" />
          </button>
        </div>
      </slot>
    </div>

    <!-- TABLE WRAPPER -->
    <div class="overflow-hidden rounded-md border border-lightgrey">
      <DataTable
        :value="filteredRows"
        v-model:selection="selection"
        :dataKey="'id'"
        :selectionMode="selectable ? 'multiple' : undefined"
        striped-rows
        responsive-layout="scroll"
        @row-click="(e) => onRowClick(e.data)"
      >
        <Column
          v-if="selectable"
          selectionMode="multiple"
          style="width: 3rem"
          headerStyle="width: 3rem"
        />

        <Column
          v-for="col in columns"
          :key="col.field || col.header"
          :field="col.field"
          :header="col.header"
          :sortable="col.sortable"
          :style="col.width ? { width: col.width } : null"
          :headerStyle="col.width ? { width: col.width } : null"
        >
          <!-- CUSTOM CELL SLOT -->
          <template
            v-if="col.field && $slots[`cell-${col.field}`]"
            #body="slotProps"
          >
            <span
              :class="[
                'inline-flex items-center',
                col.disabled ? 'text-lightgrey' : ''
              ]"
            >
              <slot
                :name="`cell-${col.field}`"
                v-bind="{ ...slotProps, column: col }"
              />
            </span>
          </template>

          <!-- DEFAULT CELL -->
          <template v-else #body="{ data }">
            <span
              :class="[
                'inline-flex items-center',
                col.disabled ? 'text-lightgrey' : ''
              ]"
            >
              {{ col.field ? data[col.field] : '' }}
            </span>
          </template>
        </Column>

        <!-- ROW ACTIONS COLUMN -->
        <Column
          v-if="showRowActions"
          header=""
          style="width: 5rem"
          headerStyle="width: 5rem"
        >
          <template #body="slotProps">
            <slot
              v-if="hasRowActionsSlot"
              name="row-actions"
              v-bind="slotProps"
            />

            <button
              v-else
              type="button"
              @click.stop="onRowUpdate(slotProps.data)"
              class="flex h-6 w-6 items-center justify-center rounded-full border border-darkgrey bg-transparent p-0 text-darkgrey hover:bg-almostwhite"
            >
              <i class="bi bi-pencil text-[0.75rem]" />
            </button>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- FOOTER -->
    <div class="mt-xs flex justify-end text-[0.7rem] text-lightgrey">
      <slot name="footer" v-bind="{ filteredRows }">
        {{ filteredRows.length }} z {{ rows.length }} záznamov
      </slot>
    </div>
  </div>
</template>

<style scoped>
/* PrimeVue table skin – still easier with a bit of plain CSS
   because these elements are rendered by the library. */

.p-datatable-table {
  border-collapse: collapse;
}

/* left-align everything */
.p-datatable-table thead > tr > th,
.p-datatable-table tbody > tr > td {
  text-align: left;
}

.p-datatable-table thead > tr > th {
  background-color: var(--c-dark-grey);
  color: var(--c-white);
  font-weight: 500;
  font-size: 0.75rem;
  border: none;
}

.p-datatable-table tbody > tr > td {
  border: none;
  font-size: 0.75rem;
}

.p-datatable-table tbody > tr:nth-child(even) > td {
  background-color: var(--c-almost-white);
}

.p-datatable-table tbody > tr:hover > td {
  background-color: #e9f4ff;
}
</style>
