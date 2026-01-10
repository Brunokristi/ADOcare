<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const toast = useToast()
const authStore = useAuthStore()
const branchId = computed(() => authStore.currentBranch?.id ?? null)

const dt = ref(null)
const rows = ref([])
const loading = ref(false)
const showOnlyFav = ref(false)

const search = ref('')
const first = ref(0)
const perPage = ref(50)
const totalRecords = ref(0)
const sortField = ref(null)
const sortOrder = ref(null) // 1 or -1

function uiFieldToApiField(field) {
  const map = {
    firstname: 'first_name',
    lastname: 'last_name',
    title: 'title',
    zpr: 'zpr',
    pzs: 'pzs',
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

async function loadDoctors(page = 1) {
  if (!branchId.value) return

  loading.value = true
  try {
    const res = await api.get('/v1/doctors', {
      params: {
        branch_id: branchId.value,
        favourites: showOnlyFav.value ? 1 : 0,

        // ✅ pagination
        page,
        per_page: perPage.value,

        // ✅ server search across ALL records
        q: search.value?.trim() || undefined,

        // ✅ optional server-side sorting
        sort: buildSortParam(),
      },
    })

    // BaseCollection commonly gives: data.items + data.total (your earlier code suggests that)
    // Keep fallbacks to be safe.
    const data = res.data?.data ?? {}
    const items = data.items ?? data.data ?? []
    const total = data.total ?? data.meta?.total ?? items.length

    rows.value = items.map((d) => ({
      id: d.id,
      firstname: d.first_name ?? '',
      lastname: d.last_name ?? '',
      title: d.title ?? '',
      zpr: d.zpr ?? '',
      pzs: d.pzs ?? '',
      is_favourite: !!d.is_favourite,
      _api: d,
    }))

    totalRecords.value = Number(total) || 0
  } catch (e) {
    console.error(e)
    toast.add({
      severity: 'error',
      summary: 'Chyba pri načítaní',
      detail: 'Lekárov sa nepodarilo načítať.',
      life: 4000,
    })
  } finally {
    loading.value = false
  }
}

function onPage(e) {
  first.value = e.first
  perPage.value = e.rows
  loadDoctors(currentPage())
}

function onSort(e) {
  sortField.value = e.sortField
  sortOrder.value = e.sortOrder
  first.value = 0
  loadDoctors(1)
}

// debounce search -> always reload from page 1
let t = null
watch(search, () => {
  if (t) clearTimeout(t)
  t = setTimeout(() => {
    first.value = 0
    loadDoctors(1)
  }, 250)
})

watch(showOnlyFav, () => {
  first.value = 0
  loadDoctors(1)
})

watch(
  () => branchId.value,
  (id) => {
    if (!id) return
    first.value = 0
    loadDoctors(1)
  },
  { immediate: true }
)

async function toggleFavourite(row) {
  row.is_favourite = !row.is_favourite
  try {
    if (row.is_favourite) {
      await api.post(`/v1/branches/${branchId.value}/doctors/${row.id}`)
    } else {
      await api.delete(`/v1/branches/${branchId.value}/doctors/${row.id}`)
    }

    // if showing only favourites, refresh page after toggle
    if (showOnlyFav.value) loadDoctors(currentPage())
  } catch (e) {
    console.error(e)
    row.is_favourite = !row.is_favourite
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa zmeniť obľúbeného lekára.',
      life: 4000,
    })
  }
}

const recordsInfo = computed(() => {
  const total = totalRecords.value
  if (total === 0) return `0 z 0 záznamov`
  const from = first.value + 1
  const to = Math.min(first.value + perPage.value, total)
  return `${from}-${to} z ${total} záznamov`
})

onMounted(() => {
  if (branchId.value) loadDoctors(1)
})
</script>

<template>
  <div class="h-full flex flex-col overflow-hidden min-h-0">
    <Toolbar
      class="flex-none !bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between"
    >
      <template #end>
        <div class="flex items-center gap-4">
          <IconField>
            <InputText v-model="search" />
            <InputIcon>
              <i class="bi bi-search text-darkgrey" />
            </InputIcon>
          </IconField>

          <Button
            :icon="showOnlyFav ? 'bi bi-heart-fill' : 'bi bi-heart'"
            @click="showOnlyFav = !showOnlyFav"
            class="!bg-accent !border-accent !text-white hover:!bg-darkgrey hover:!border-darkgrey !h-7"
          />
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
        <Column field="firstname" header="Meno" sortable />
        <Column field="lastname" header="Priezvisko" sortable />
        <Column field="title" header="Titul" sortable />
        <Column field="zpr" header="ZPR" sortable />
        <Column field="pzs" header="PZS" sortable />

        <Column header=" " style="width: 3rem">
          <template #body="{ data }">
            <Button
              :icon="data.is_favourite ? 'bi bi-heart-fill' : 'bi bi-heart'"
              @click="toggleFavourite(data)"
              variant="text"
              class="!text-darkgrey hover:!bg-transparent p-0!"
            />
          </template>
        </Column>
      </DataTable>
    </div>

    <div class="flex-none text-mini text-accent flex justify-end w-full py-2">
      {{ recordsInfo }}
    </div>
  </div>
</template>
