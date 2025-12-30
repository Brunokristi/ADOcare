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
const first = ref(0)      // offset
const perPage = ref(25)   // fixed page size (no dropdown)
const totalRecords = ref(0)

// server-side sorting
const sortField = ref(null)
const sortOrder = ref(null) // 1 asc, -1 desc

// form model
const product = ref({
  id: null,
  code: '',
  price25: null,
  price24: null,
  price27: null,
  description: '',
})

const isEditing = computed(() => !!product.value?.id)

// --- API layer ---
const ProceduresApi = {
  list: (params = {}) => api.get('/v1/procedures', { params }),
  create: (payload) => api.post('/v1/procedures', payload),
  update: (id, payload) => api.put(`/v1/procedures/${id}`, payload),
  remove: (id) => api.delete(`/v1/procedures/${id}`),
  bulkRemove: (ids) => api.post('/v1/procedures/bulk-delete', { ids }), // optional
}

const normalizeRow = (r) => ({
  id: r.id,
  code: r.code,
  price25: r.price25,
  price24: r.price24,
  price27: r.price27,
  description: r.description,
})

function uiFieldToApiField(field) {
  // map UI field names to DB/API fields (same names here)
  const map = {
    code: 'code',
    price25: 'price25',
    price24: 'price24',
    price27: 'price27',
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

const fetchRows = async (page = 1) => {
  loading.value = true
  try {
    const res = await ProceduresApi.list({
      q: search.value?.trim() || undefined,
      page,
      per_page: perPage.value,
      sort: buildSortParam(),
    })

    const payload = res.data?.data ?? {}

    // Expecting BaseCollection-like:
    // payload.items + payload.total
    // (fallbacks included)
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
      detail: 'Nepodarilo sa načítať záznamy z databázy.',
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
  // keep perPage fixed; if you ever want to allow it, set perPage.value = e.rows
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
    code: '',
    price25: null,
    price24: null,
    price27: null,
    description: '',
  }
  submitted.value = false
  productDialog.value = true
}

const editProduct = (row) => {
  product.value = { ...row }
  submitted.value = false
  productDialog.value = true
}

const hideDialog = () => {
  productDialog.value = false
  submitted.value = false
}

// --- Save (create/update) ---
const saveProduct = async () => {
  submitted.value = true

  const isValid =
    product.value.code &&
    product.value.price25 !== null &&
    product.value.price24 !== null &&
    product.value.price27 !== null &&
    product.value.description?.trim()

  if (!isValid) return

  const payload = {
    code: product.value.code?.toString().trim(),
    price25: product.value.price25,
    price24: product.value.price24,
    price27: product.value.price27,
    description: product.value.description?.toString().trim(),
  }

  loading.value = true
  try {
    if (isEditing.value) {
      await ProceduresApi.update(product.value.id, payload)
      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Záznam bol úspešne upravený.',
        life: 3000,
      })
    } else {
      await ProceduresApi.create(payload)
      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Záznam bol úspešne vytvorený.',
        life: 3000,
      })
    }

    productDialog.value = false
    product.value = {
      id: null,
      code: '',
      price25: null,
      price24: null,
      price27: null,
      description: '',
    }

    // reload current page after save
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

// --- Single delete (not wired in template currently) ---
const confirmDeleteProduct = (row) => {
  product.value = { ...row }
  deleteProductDialog.value = true
}

const deleteProduct = async () => {
  if (!product.value?.id) return

  loading.value = true
  try {
    await ProceduresApi.remove(product.value.id)

    deleteProductDialog.value = false
    product.value = {
      id: null,
      code: '',
      price25: null,
      price24: null,
      price27: null,
      description: '',
    }

    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: 'Záznam bol úspešne vymazaný.',
      life: 3000,
    })

    // if we removed the last item on a page, go one page back
    if (products.value.length === 1 && first.value >= perPage.value) {
      first.value = first.value - perPage.value
    }
    await fetchRows(currentPage())
  } catch (e) {
    console.error(e)
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Vymazanie zlyhalo.',
      life: 4000,
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
      await ProceduresApi.bulkRemove(ids)
    } catch {
      await Promise.all(ids.map((id) => ProceduresApi.remove(id)))
    }

    deleteProductsDialog.value = false
    showRows.value = []

    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: 'Záznamy boli úspešne vymazané.',
      life: 3000,
    })

    // adjust page if needed (deleted last items on page)
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

// footer counter like previous table
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
        class="h-full"
      >
        <Column selectionMode="multiple" style="width: 3rem" />

        <Column field="code" header="Kód" sortable style="width: 8rem" />
        <Column field="price25" header="Cena 25" sortable style="width: 8rem" />
        <Column field="price24" header="Cena 24" sortable style="width: 8rem" />
        <Column field="price27" header="Cena 27" sortable style="width: 8rem" />

        <Column field="description" header="Popis" style="width: auto" />

        <Column :exportable="false" style="width: 3rem">
          <template #body="{ data }">
            <Button
              icon="bi bi-pencil"
              variant="text"
              class="!text-darkgrey hover:!bg-transparent !p-0"
              @click="editProduct(data)"
            />
          </template>
        </Column>

      </DataTable>
    </div>

    <div class="flex-none text-mini text-accent flex justify-end w-full py-2">
      {{ recordsInfo }}
    </div>

    <Dialog v-model:visible="productDialog" :style="{ width: '600px' }" header="Výkon" :modal="true">
      <div class="flex flex-col gap-6">
        <div>
          <label :class="['block text-normal mb-1', isEditing ? '!text-lightgrey' : '']">Kód</label>
          <InputText
            v-model.trim="product.code"
            fluid
            :invalid="submitted && !product.code"
            :disabled="isEditing"
            class="disabled:!bg-white disabled:!text-lightgrey disabled:!border-lightgrey disabled:!cursor-not-allowed"
          />
          <small v-if="submitted && !product.code" class="text-warning">Kód je povinný.</small>
        </div>

        <div class="grid grid-cols-12 gap-4">
          <div class="col-span-4">
            <label class="block text-normal mb-1">Cena poisťovňa 25</label>
            <InputNumber
              v-model="product.price25"
              mode="decimal"
              :minFractionDigits="2"
              :maxFractionDigits="2"
              :useGrouping="false"
              fluid
              :invalid="submitted && product.price25 == null"
            />
            <small v-if="submitted && product.price25 === null" class="text-warning">Povinné pole.</small>
          </div>

          <div class="col-span-4">
            <label class="block text-normal mb-1">Cena poisťovňa 24</label>
            <InputNumber
              v-model="product.price24"
              mode="decimal"
              :minFractionDigits="2"
              :maxFractionDigits="2"
              :useGrouping="false"
              fluid
              :invalid="submitted && product.price24 == null"
            />
            <small v-if="submitted && product.price24 === null" class="text-warning">Povinné pole.</small>
          </div>

          <div class="col-span-4">
            <label class="block text-normal mb-1">Cena poisťovňa 27</label>
            <InputNumber
              v-model="product.price27"
              mode="decimal"
              :minFractionDigits="2"
              :maxFractionDigits="2"
              :useGrouping="false"
              fluid
              :invalid="submitted && product.price27 == null"
            />
            <small v-if="submitted && product.price27 === null" class="text-warning">Povinné pole.</small>
          </div>
        </div>

        <div>
          <label :class="['block text-normal mb-1', isEditing ? '!text-lightgrey' : '']">Popis</label>
          <Textarea
            v-model.trim="product.description"
            rows="3"
            fluid
            :invalid="submitted && !product.description"
            :disabled="isEditing"
            class="disabled:!bg-white disabled:!text-lightgrey disabled:!border-lightgrey disabled:!cursor-not-allowed"
          />
          <small v-if="submitted && !product.description" class="text-warning">Popis je povinný.</small>
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
