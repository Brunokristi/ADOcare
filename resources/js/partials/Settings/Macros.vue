<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'

const toast = useToast()
const dt = ref(null)

const productDialog = ref(false)
const deleteProductDialog = ref(false) // kept
const deleteProductsDialog = ref(false)

const submitted = ref(false)
const loading = ref(false)

// table rows
const products = ref([])

// selection
const showRows = ref([])

// server-side search text
const search = ref('')

// paginator state
const first = ref(0)
const perPage = ref(25) // fixed
const totalRecords = ref(0)

// server-side sorting
const sortField = ref(null)
const sortOrder = ref(null) // 1 asc, -1 desc

// form model
const product = ref({
  id: null,
  name: '',
  abbreviation: '',
  text: '',
})

const isEditing = computed(() => !!product.value?.id)

// --- API layer ---
const MacrosApi = {
  list: (params = {}) => api.get('/v1/macros', { params }),
  create: (payload) => api.post('/v1/macros', payload),
  update: (id, payload) => api.put(`/v1/macros/${id}`, payload),
  remove: (id) => api.delete(`/v1/macros/${id}`),
  bulkRemove: (ids) => api.post('/v1/macros/bulk-delete', { ids }),
}

const normalizeRow = (r) => ({
  id: r.id,
  name: r.name ?? '',
  abbreviation: r.abbreviation ?? '',
  text: r.text ?? '',
})

function uiFieldToApiField(field) {
  const map = {
    name: 'name',
    abbreviation: 'abbreviation',
    text: 'text',
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

const fetchRows = async (page = 1) => {
  loading.value = true
  try {
    const res = await MacrosApi.list({
      q: search.value?.trim() || undefined,
      page,
      per_page: perPage.value,
      sort: buildSortParam(),
    })

    const payload = res.data?.data ?? {}

    const items =
      payload.items ??
      payload.data ??
      (Array.isArray(payload) ? payload : []) ??
      []

    const total =
      payload.total ??
      payload.meta?.total ??
      (Array.isArray(items) ? items.length : 0)

    products.value = items.map(normalizeRow)
    totalRecords.value = Number(total) || 0
  } catch (e) {
    console.error(e)
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa načítať makrá z databázy.',
      life: 4000,
    })
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchRows(1))

// --- debounced server-side search ---
let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    first.value = 0
    fetchRows(1)
  }, 250)
})

// --- paginator handler ---
const onPage = (e) => {
  first.value = e.first
  fetchRows(currentPage())
}

// --- sort handler (server-side) ---
const onSort = (e) => {
  sortField.value = e.sortField
  sortOrder.value = e.sortOrder
  first.value = 0
  fetchRows(1)
}

// --- Dialog actions ---
const openNew = () => {
  product.value = {
    id: null,
    name: '',
    abbreviation: '',
    text: '',
  }
  submitted.value = false
  productDialog.value = true
}

const hideDialog = () => {
  productDialog.value = false
  submitted.value = false
}

const editProduct = (row) => {
  product.value = { ...row }
  submitted.value = false
  productDialog.value = true
}

// --- Save (create/update) ---
const saveProduct = async () => {
  submitted.value = true

  const isValid =
    product.value.name?.trim() &&
    product.value.abbreviation?.trim() &&
    product.value.text?.trim()

  if (!isValid) return

  const payload = {
    name: product.value.name.toString().trim(),
    abbreviation: product.value.abbreviation.toString().trim(),
    text: product.value.text.toString().trim(),
  }

  loading.value = true
  try {
    if (isEditing.value) {
      await MacrosApi.update(product.value.id, payload)
      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Makro bolo úspešne upravené.',
        life: 3000,
      })
    } else {
      await MacrosApi.create(payload)
      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Makro bolo úspešne vytvorené.',
        life: 3000,
      })
    }

    productDialog.value = false
    product.value = { id: null, name: '', abbreviation: '', text: '' }

    await fetchRows(currentPage())
  } catch (e) {
    console.error(e)
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Uloženie zlyhalo. Skontrolujte validáciu alebo odpoveď zo servera.',
      life: 4500,
    })
  } finally {
    loading.value = false
  }
}

// --- Bulk delete ---
const confirmDeleteSelected = () => {
  deleteProductsDialog.value = true
}

const deleteshowRows = async () => {
  const ids = (showRows.value ?? []).map((r) => r.id).filter(Boolean)
  if (!ids.length) {
    deleteProductsDialog.value = false
    return
  }

  loading.value = true
  try {
    try {
      await MacrosApi.bulkRemove(ids)
    } catch {
      await Promise.all(ids.map((id) => MacrosApi.remove(id)))
    }

    deleteProductsDialog.value = false
    showRows.value = []

    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: 'Makrá boli úspešne vymazané.',
      life: 3000,
    })

    // adjust page if needed
    if (ids.length >= products.value.length && first.value >= perPage.value) {
      first.value = first.value - perPage.value
    }

    await fetchRows(currentPage())
  } catch (e) {
    console.error(e)
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Hromadné vymazanie zlyhalo.',
      life: 4000,
    })
  } finally {
    loading.value = false
  }
}

// footer counter like previous tables
const recordsInfo = computed(() => {
  const total = totalRecords.value
  if (!total) return `0 z 0 záznamov`
  const from = first.value + 1
  const to = Math.min(first.value + perPage.value, total)
  return `${from}-${to} z ${total} záznamov`
})
</script>

<template>
  <div class="h-full flex flex-col overflow-hidden min-h-0">
    <Toolbar
      class="!bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between"
    >
      <template #end>
        <div class="flex items-center gap-2">
          <IconField>
            <InputText v-model="search" />
            <InputIcon>
              <i class="bi bi-search text-darkgrey" />
            </InputIcon>
          </IconField>

          <Button
            icon="bi bi-plus"
            @click="openNew"
            class="!bg-accent !border-accent hover:!bg-darkgrey hover:!border-darkgrey !text-white !h-7"
          />

          <Button
            icon="bi bi-eraser"
            @click="confirmDeleteSelected"
            :disabled="!showRows || !showRows.length"
            class="!bg-warning !border-warning !text-white !h-7"
          />
        </div>
      </template>
    </Toolbar>

    <div class="flex-1 overflow-hidden min-h-0">
      <DataTable
        ref="dt"
        v-model:selection="showRows"
        :value="products"
        dataKey="id"
        stripedRows
        removableSort
        :loading="loading"

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
        tableLayout="fixed"
        class="h-full"
      >
        <Column selectionMode="multiple" style="width: 3rem" :exportable="false" />

        <Column field="name" header="Názov" sortable style="width: 14rem" />
        <Column field="abbreviation" header="Skratka" sortable style="width: 10rem" />

        <Column field="text" header="Text" sortable style="width: auto">
          <template #body="{ data }">
            <div class="truncate max-w-full">
              {{ data.text }}
            </div>
          </template>
        </Column>

        <Column :exportable="false" style="width: 3rem">
          <template #body="{ data }">
            <Button
              icon="bi bi-pencil"
              @click.stop="editProduct(data)"
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

    <Dialog v-model:visible="productDialog" :style="{ width: '600px' }" header="Makro" :modal="true">
      <div class="flex flex-col gap-6">
        <div class="grid grid-cols-12 gap-4">
          <div class="col-span-6">
            <label class="block text-normal mb-1">Názov</label>
            <InputText v-model.trim="product.name" fluid :invalid="submitted && !product.name" />
            <small v-if="submitted && !product.name" class="text-warning">Názov je povinný.</small>
          </div>

          <div class="col-span-6">
            <label class="block text-normal mb-1">Skratka</label>
            <InputText v-model.trim="product.abbreviation" fluid :invalid="submitted && !product.abbreviation" />
            <small v-if="submitted && !product.abbreviation" class="text-warning">Skratka je povinná.</small>
          </div>
        </div>

        <div>
          <label class="block text-normal mb-1">Text</label>
          <Textarea v-model.trim="product.text" rows="5" fluid :invalid="submitted && !product.text" />
          <small v-if="submitted && !product.text" class="text-warning">Text je povinný.</small>
        </div>
      </div>

      <template #footer>
        <Button
          label="Uložiť"
          class="!bg-accent !px-md !text-white hover:!bg-darkgrey !border-0"
          @click="saveProduct"
        />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="deleteProductsDialog"
      :style="{ width: '600px' }"
      :modal="true"
      :closable="false"
      header="Upozornenie"
    >
      <div class="flex items-center justify-between w-full">
        <span class="text-heading">Naozaj si prajete vymazať záznamy?</span>

        <div class="flex items-center gap-2">
          <Button
            label="Nie"
            text
            @click="deleteProductsDialog = false"
            class="!bg-accent !px-md !text-white hover:!bg-darkgrey !border-0"
          />
          <Button
            label="Áno"
            text
            @click="deleteshowRows"
            class="!bg-warning !px-md !text-white"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>
