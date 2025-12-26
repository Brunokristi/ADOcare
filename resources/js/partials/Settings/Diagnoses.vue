<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/services/api'

type DiagnosisApiItem = {
  id: number
  code: string
  description: string
}

type DiagnosisRow = {
  id: number
  code: string
  description: string
}

const rows = ref<DiagnosisRow[]>([])
const loading = ref(false)

const search = ref('')
const first = ref(0)
const perPage = ref(50)
const totalRecords = ref(0)
const sortField = ref<string | null>(null)
const sortOrder = ref<number | null>(null) // 1 asc, -1 desc

function uiFieldToApiField(field: string) {
  // UI fields match DB here, but keep mapping for consistency
  const map: Record<string, string> = {
    code: 'code',
    description: 'description',
  }
  return map[field] ?? field
}

function buildSortParam() {
  if (!sortField.value || !sortOrder.value) return undefined
  const apiField = uiFieldToApiField(sortField.value)
  return sortOrder.value === -1 ? `-${apiField}` : apiField
}

function currentPage() {
  return Math.floor(first.value / perPage.value) + 1
}

// ---- API ----
async function loadDiagnoses(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/v1/diagnoses', {
      params: {
        // ✅ server-side search across ALL records
        q: search.value?.trim() || undefined,

        // ✅ pagination
        page,
        per_page: perPage.value,

        // ✅ optional server-side sorting
        sort: buildSortParam(),
      },
    })

    // Support common shapes:
    // 1) { data: { items: [...], total: N } }
    // 2) { data: { data: [...], meta: { total: N } } }
    // 3) { data: [...] }  (no pagination backend yet)
    const payload = res.data?.data
    const items: DiagnosisApiItem[] =
      payload?.items ??
      payload?.data ??
      (Array.isArray(payload) ? payload : []) ??
      []

    const total =
      payload?.total ??
      payload?.meta?.total ??
      (Array.isArray(items) ? items.length : 0)

    rows.value = items.map((d) => ({
      id: d.id,
      code: d.code ?? '',
      description: d.description ?? '',
    }))

    totalRecords.value = Number(total) || 0
  } finally {
    loading.value = false
  }
}

// ---- PrimeVue handlers ----
function onPage(e: any) {
  first.value = e.first
  perPage.value = e.rows
  loadDiagnoses(currentPage())
}

function onSort(e: any) {
  sortField.value = e.sortField
  sortOrder.value = e.sortOrder
  first.value = 0
  loadDiagnoses(1)
}

// ---- Debounced search -> reload page 1 ----
let searchTimer: number | undefined
watch(search, () => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    first.value = 0
    loadDiagnoses(1)
  }, 250)
})

onMounted(() => {
  loadDiagnoses(1)
})

// ---- footer counter (like previous table) ----
const recordsInfo = computed(() => {
  const total = totalRecords.value
  if (!total) return `0 z 0 záznamov`
  const from = first.value + 1
  const to = Math.min(first.value + perPage.value, total)
  return `${from}-${to} z ${total} záznamov`
})
</script>

<template>
  <!-- Same full-height, non-page-scroll format as the previous table -->
  <div class="h-full flex flex-col overflow-hidden min-h-0">
    <Toolbar
      class="flex-none !bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between"
    >
      <template #end>
        <div class="flex items-center gap-2">
          <IconField>
            <InputText v-model="search" />
            <InputIcon>
              <i class="bi bi-search text-darkgrey" />
            </InputIcon>
          </IconField>
        </div>
      </template>
    </Toolbar>

    <div class="flex-1 overflow-hidden min-h-0">
      <DataTable
        ref="dt"
        :value="rows"
        dataKey="id"
        :loading="loading"
        stripedRows
        removableSort

        lazy
        paginator
        :first="first"
        :rows="perPage"
        :totalRecords="totalRecords"
        @page="onPage"
        sortMode="single"
        @sort="onSort"

        scrollable
        scrollHeight="flex"
        class="h-full"
      >
        <Column field="code" header="Kód" sortable />
        <Column field="description" header="Popis" sortable />
      </DataTable>
    </div>

    <div class="flex-none text-mini text-accent flex justify-end w-full py-2">
      {{ recordsInfo }}
    </div>
  </div>
</template>
