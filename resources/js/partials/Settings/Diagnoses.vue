<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { FilterMatchMode } from '@primevue/core/api'
import api from '@/services/api'

type DiagnosisRow = {
  id: number
  code: string
  description: string
}

const dt = ref<any>(null)

const products = ref<DiagnosisRow[]>([])
const loading = ref(false)

// keep the same filter UI
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
})

// ---- API ----
async function loadDiagnoses(q = '') {
  try {
    loading.value = true

    const res = await api.get('/v1/diagnoses', {
      params: q ? { q } : {},
    })

    // Your controller returns: success($items, ...) => { data: [...] }
    // But we support both shapes safely:
    const items =
      (res.data?.data?.items as DiagnosisRow[] | undefined) ??
      (res.data?.data as DiagnosisRow[] | undefined) ??
      []

    products.value = Array.isArray(items) ? items : []
  } finally {
    loading.value = false
  }
}

// ---- Debounced search (server-side via `q`) ----
let searchTimer: number | undefined

watch(
  () => filters.value.global.value,
  (val) => {
    window.clearTimeout(searchTimer)
    searchTimer = window.setTimeout(() => {
      loadDiagnoses(String(val ?? '').trim())
    }, 300)
  }
)

onMounted(() => {
  loadDiagnoses('')
})

// ---- footer counter ----
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
      :loading="loading"
    >
      <Column field="code" header="Kód" sortable />
      <Column field="description" header="Popis" />
    </DataTable>

    <div class="text-mini text-accent flex justify-end w-full py-2">
      {{ recordsInfo }}
    </div>
  </div>
</template>
