<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import api from '@/services/api';

const toast = useToast();
const dt = ref(null);

const productDialog = ref(false);
const deleteProductDialog = ref(false); // (not used in your template currently, but kept)
const deleteProductsDialog = ref(false);

const submitted = ref(false);
const loading = ref(false);

// table data
const products = ref([]);

// selection
const showRows = ref([]);

// form model
const product = ref({
  id: null,
  code: '',
  price25: null,
  price24: null,
  price27: null,
  description: '',
});

const isEditing = computed(() => !!product.value?.id);

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// --- API layer (adjust URLs to match your backend) ---
const ProceduresApi = {
  list: (q = '') => api.get('/v1/procedures', { params: q ? { q } : {} }),
  create: (payload) => api.post('/v1/procedures', payload),
  update: (id, payload) => api.put(`/v1/procedures/${id}`, payload),
  remove: (id) => api.delete(`/v1/procedures/${id}`),
  bulkRemove: (ids) => api.post('/v1/procedures/bulk-delete', { ids }), // optional endpoint
};

const normalizeRow = (r) => ({
  id: r.id,
  code: r.code,
  price25: r.price25,
  price24: r.price24,
  price27: r.price27,
  description: r.description,
});

const fetchRows = async () => {
  loading.value = true;
  try {
    const q = (filters.value.global?.value ?? '').toString().trim();
    const res = await ProceduresApi.list(q);

    // Support both styles:
    // 1) plain array response
    // 2) { data: [...] } or your ApiResponse wrapper
    const raw =
      Array.isArray(res.data) ? res.data :
      Array.isArray(res.data?.data) ? res.data.data :
      Array.isArray(res.data?.payload) ? res.data.payload :
      [];

    products.value = raw.map(normalizeRow);
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa načítať záznamy z databázy.',
      life: 4000,
    });
  } finally {
    loading.value = false;
  }
};

onMounted(fetchRows);

// Optional: server-side search as user types (debounced)
let searchTimer = null;
watch(
  () => filters.value.global.value,
  () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      fetchRows();
    }, 350);
  }
);

// --- Dialog actions ---
const openNew = () => {
  product.value = {
    id: null,
    code: '',
    price25: null,
    price24: null,
    price27: null,
    description: '',
  };
  submitted.value = false;
  productDialog.value = true;
};

const editProduct = (row) => {
  product.value = { ...row };
  submitted.value = false;
  productDialog.value = true;
};

const hideDialog = () => {
  productDialog.value = false;
  submitted.value = false;
};

// --- Save (create/update) ---
const saveProduct = async () => {
  submitted.value = true;

  const isValid =
    product.value.code &&
    product.value.price25 !== null &&
    product.value.price24 !== null &&
    product.value.price27 !== null &&
    product.value.description?.trim();

  if (!isValid) return;

  const payload = {
    code: product.value.code?.toString().trim(),
    price25: product.value.price25,
    price24: product.value.price24,
    price27: product.value.price27,
    description: product.value.description?.toString().trim(),
  };

  loading.value = true;
  try {
    if (isEditing.value) {
      await ProceduresApi.update(product.value.id, payload);

      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Záznam bol úspešne upravený.',
        life: 3000,
      });
    } else {
      await ProceduresApi.create(payload);

      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Záznam bol úspešne vytvorený.',
        life: 3000,
      });
    }

    productDialog.value = false;
    product.value = {
      id: null,
      code: '',
      price25: null,
      price24: null,
      price27: null,
      description: '',
    };

    await fetchRows();
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Uloženie zlyhalo. Skontrolujte validáciu alebo odpoveď zo servera.',
      life: 4500,
    });
  } finally {
    loading.value = false;
  }
};

// --- Single delete (wire into your UI if you add a delete button) ---
const confirmDeleteProduct = (row) => {
  product.value = { ...row };
  deleteProductDialog.value = true;
};

const deleteProduct = async () => {
  if (!product.value?.id) return;

  loading.value = true;
  try {
    await ProceduresApi.remove(product.value.id);

    deleteProductDialog.value = false;
    product.value = {
      id: null,
      code: '',
      price25: null,
      price24: null,
      price27: null,
      description: '',
    };

    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: 'Záznam bol úspešne vymazaný.',
      life: 3000,
    });

    await fetchRows();
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Vymazanie zlyhalo.',
      life: 4000,
    });
  } finally {
    loading.value = false;
  }
};

// --- Bulk delete (your template uses this) ---
const confirmDeleteSelected = () => {
  deleteProductsDialog.value = true;
};

const deleteshowRows = async () => {
  const ids = (showRows.value ?? []).map((r) => r.id).filter(Boolean);
  if (!ids.length) {
    deleteProductsDialog.value = false;
    return;
  }

  loading.value = true;
  try {
    // If you don’t have bulk-delete on backend, fallback to Promise.all deletes.
    // Preferred: implement ProceduresApi.bulkRemove on the backend.
    try {
      await ProceduresApi.bulkRemove(ids);
    } catch {
      await Promise.all(ids.map((id) => ProceduresApi.remove(id)));
    }

    deleteProductsDialog.value = false;
    showRows.value = [];

    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: 'Záznamy boli úspešne vymazané.',
      life: 3000,
    });

    await fetchRows();
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Hromadné vymazanie zlyhalo.',
      life: 4000,
    });
  } finally {
    loading.value = false;
  }
};

const recordsInfo = computed(() => {
  if (!dt.value) return '';

  const total = products.value.length;
  const filtered = dt.value.processedData?.length;

  if (filtered == null) return `${total} z ${total} záznamov`;
  return `${filtered} z ${total} záznamov`;
});
</script>

<template>
  <div>
    <!-- Toast must be rendered somewhere for useToast() to work -->
    <Toast />

    <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between">
      <template #end>
        <div class="flex items-center gap-2">
          <IconField>
            <InputText v-model="filters['global'].value" />
            <InputIcon>
              <i class="bi bi-search text-darkgrey" />
            </InputIcon>
          </IconField>

          <Button
            icon="bi bi-plus"
            @click="openNew"
            class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey!"
          />

          <Button
            icon="bi bi-eraser"
            @click="confirmDeleteSelected"
            :disabled="!showRows || !showRows.length"
            class="bg-warning! border-warning!"
          />
        </div>
      </template>
    </Toolbar>

    <DataTable
      ref="dt"
      v-model:selection="showRows"
      :value="products"
      dataKey="id"
      :filters="filters"
      stripedRows
      removableSort
      scrollable
      scrollHeight="600px"
      :loading="loading"
    >
      <Column selectionMode="multiple" style="width: 3rem" :exportable="false" />
      <Column field="code" header="Kód" sortable />
      <Column field="price25" header="Cena poisťovňa 25" sortable />
      <Column field="price24" header="Cena poisťovňa 24" sortable disabled />
      <Column field="price27" header="Cena poisťovňa 27" sortable />
      <Column field="description" header="Popis" />
      <Column :exportable="false" style="width: 3rem">
        <template #body="slotProps">
          <Button
            icon="bi bi-pencil"
            @click="editProduct(slotProps.data)"
            variant="text"
            class="text-darkgrey! hover:bg-transparent! p-0!"
          />
        </template>
      </Column>
    </DataTable>

    <div class="text-mini text-accent flex justify-end w-full py-2">
      {{ recordsInfo }}
    </div>

    <Dialog v-model:visible="productDialog" :style="{ width: '600px' }" header="Výkon" :modal="true">
      <div class="flex flex-col gap-6">
        <!-- Kód -->
        <div>
          <label :class="['block text-normal mb-1', isEditing ? '!text-lightgrey' : '']">Kód</label>
          <InputText
            v-model.trim="product.code"
            fluid
            :invalid="submitted && !product.code"
            :disabled="isEditing"
            class="disabled:!bg-white disabled:!text-lightgrey disabled:!border-lightgrey disabled:!cursor-not-allowed"
          />
          <small v-if="submitted && !product.code" class="text-warning">
            Kód je povinný.
          </small>
        </div>

        <!-- Prices -->
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
            <small v-if="submitted && product.price25 === null" class="text-warning">
              Povinné pole.
            </small>
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
            <small v-if="submitted && product.price24 === null" class="text-warning">
              Povinné pole.
            </small>
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
            <small v-if="submitted && product.price27 === null" class="text-warning">
              Povinné pole.
            </small>
          </div>
        </div>

        <!-- Description -->
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
          <small v-if="submitted && !product.description" class="text-warning">
            Popis je povinný.
          </small>
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
