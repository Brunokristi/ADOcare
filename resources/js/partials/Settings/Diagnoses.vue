<script setup lang="ts">
import { ref, computed } from 'vue'
import { FilterMatchMode } from '@primevue/core/api'

const dt = ref<any>(null)

const products = ref([
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
  {
    id: 4,
    code: '174829',
    price25: '174829',
    price24: '174829',
    price27: '174829',
    description: 'Random description 4',
  },
  {
    id: 5,
    code: '285736',
    price25: '285736',
    price24: '285736',
    price27: '285736',
    description: 'Random description 5',
  },
  {
    id: 6,
    code: '396154',
    price25: '396154',
    price24: '396154',
    price27: '396154',
    description: 'Random description 6',
  },
  {
    id: 7,
    code: '417263',
    price25: '417263',
    price24: '417263',
    price27: '417263',
    description: 'Random description 7',
  },
  {
    id: 8,
    code: '528971',
    price25: '528971',
    price24: '528971',
    price27: '528971',
    description: 'Random description 8',
  },
  {
    id: 9,
    code: '639845',
    price25: '639845',
    price24: '639845',
    price27: '639845',
    description: 'Random description 9',
  },
  {
    id: 10,
    code: '741236',
    price25: '741236',
    price24: '741236',
    price27: '741236',
    description: 'Random description 10',
  },
  {
    id: 11,
    code: '852147',
    price25: '852147',
    price24: '852147',
    price27: '852147',
    description: 'Random description 11',
  },
  {
    id: 12,
    code: '963741',
    price25: '963741',
    price24: '963741',
    price27: '963741',
    description: 'Random description 12',
  },
  {
    id: 13,
    code: '184957',
    price25: '184957',
    price24: '184957',
    price27: '184957',
    description: 'Random description 13',
  },
  {
    id: 14,
    code: '295864',
    price25: '295864',
    price24: '295864',
    price27: '295864',
    description: 'Random description 14',
  },
  {
    id: 15,
    code: '316479',
    price25: '316479',
    price24: '316479',
    price27: '316479',
    description: 'Random description 15',
  },
  {
    id: 16,
    code: '427158',
    price25: '427158',
    price24: '427158',
    price27: '427158',
    description: 'Random description 16',
  },
  {
    id: 17,
    code: '538296',
    price25: '538296',
    price24: '538296',
    price27: '538296',
    description: 'Random description 17',
  },
  {
    id: 18,
    code: '649713',
    price25: '649713',
    price24: '649713',
    price27: '649713',
    description: 'Random description 18',
  },
  {
    id: 19,
    code: '751894',
    price25: '751894',
    price24: '751894',
    price27: '751894',
    description: 'Random description 19',
  },
  {
    id: 20,
    code: '862531',
    price25: '862531',
    price24: '862531',
    price27: '862531',
    description: 'Random description 20',
  },
])

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
})

const recordsInfo = computed(() => {
  if (!dt.value) return ''

  const total = products.value.length
  const filtered = dt.value.processedData?.length

  if (filtered == null) return `${total} z ${total} záznamov`
  return `${filtered} z ${total} záznamov`
})
</script>

<template>
  <div>
    <Toolbar class="!bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between">
      <template #end>
        <div class="flex items-center gap-2">
          <IconField>
            <InputText v-model="filters['global'].value" />
            <InputIcon>
              <i class="bi bi-search text-darkgrey" />
            </InputIcon>
          </IconField>
        </div>
      </template>
    </Toolbar>

    <DataTable
      ref="dt"
      :value="products"
      dataKey="id"
      :filters="filters"
      stripedRows
      removableSort
      scrollable
      scrollHeight="600px"
    >
      <Column field="code" header="Kód" sortable />
      <Column field="description" header="Popis" />
    </DataTable>

    <div class="text-mini text-accent flex justify-end w-full py-2">
      {{ recordsInfo }}
    </div>
  </div>
</template>

::contentReference[oaicite:0]{index=0}
