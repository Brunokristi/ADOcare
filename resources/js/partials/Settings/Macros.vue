<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import api from '@/services/api';

const toast = useToast();
const dt = ref(null);

const productDialog = ref(false);
const deleteProductDialog = ref(false); // kept if you later add single-delete confirmation
const deleteProductsDialog = ref(false);

const submitted = ref(false);
const loading = ref(false);

const products = ref([]);
const showRows = ref([]);

const product = ref({
  id: null,
  name: '',
  abbreviation: '',
  text: '',
});

const isEditing = computed(() => !!product.value?.id);

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const MacrosApi = {
  list: (q = '') => api.get('/v1/macros', { params: q ? { q } : {} }),
  create: (payload) => api.post('/v1/macros', payload),
  update: (id, payload) => api.put(`/v1/macros/${id}`, payload),
  remove: (id) => api.delete(`/v1/macros/${id}`),
  bulkRemove: (ids) => api.post('/v1/macros/bulk-delete', { ids }),
};

const normalizeRow = (r) => ({
  id: r.id,
  name: r.name,
  abbreviation: r.abbreviation,
  text: r.text,
});

const fetchRows = async () => {
  loading.value = true;
  try {
    const q = (filters.value.global?.value ?? '').toString().trim();
    const res = await MacrosApi.list(q);

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
      detail: 'Nepodarilo sa načítať makrá z databázy.',
      life: 4000,
    });
  } finally {
    loading.value = false;
  }
};

onMounted(fetchRows);

// server-side search debounce
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

const openNew = () => {
  product.value = {
    id: null,
    name: '',
    abbreviation: '',
    text: '',
  };
  submitted.value = false;
  productDialog.value = true;
};

const hideDialog = () => {
  productDialog.value = false;
  submitted.value = false;
};

const editProduct = (row) => {
  product.value = { ...row };
  submitted.value = false;
  productDialog.value = true;
};

const saveProduct = async () => {
  submitted.value = true;

  const isValid =
    product.value.name?.trim() &&
    product.value.abbreviation?.trim() &&
    product.value.text?.trim();

  if (!isValid) return;

  const payload = {
    name: product.value.name.toString().trim(),
    abbreviation: product.value.abbreviation.toString().trim(),
    text: product.value.text.toString().trim(),
  };

  loading.value = true;
  try {
    if (isEditing.value) {
      await MacrosApi.update(product.value.id, payload);
      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Makro bolo úspešne upravené.',
        life: 3000,
      });
    } else {
      await MacrosApi.create(payload);
      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Makro bolo úspešne vytvorené.',
        life: 3000,
      });
    }

    productDialog.value = false;
    product.value = { id: null, name: '', abbreviation: '', text: '' };

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
    // Prefer bulk endpoint, fallback to single deletes
    try {
      await MacrosApi.bulkRemove(ids);
    } catch {
      await Promise.all(ids.map((id) => MacrosApi.remove(id)));
    }

    deleteProductsDialog.value = false;
    showRows.value = [];

    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: 'Makrá boli úspešne vymazané.',
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
        <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between">
            <template #end>
                <div class="flex items-center gap-2 ">
                    <IconField>
                        <InputText v-model="filters['global'].value"  />
                        <InputIcon>
                            <i class="bi bi-search text-darkgrey" />
                        </InputIcon>
                    </IconField>

                    <Button icon="bi bi-plus" @click="openNew" class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey!"/>

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
        >
            <Column selectionMode="multiple" style="width: 3rem" :exportable="false" />
            <Column field="name" header="Názov" sortable />
            <Column field="abbreviation" header="Skratka" sortable />
            <Column field="text" header="Text" sortable disabled />
            <Column :exportable="false" style="width: 3rem">
                <template #body="slotProps">
                    <Button icon="bi bi-pencil" @click="editProduct(slotProps.data)" variant="text" class="text-darkgrey! hover:bg-transparent! p-0!" />
                </template>
            </Column>
        </DataTable>

        <div class="text-mini text-accent flex justify-end w-full py-2">
            {{ recordsInfo }}
        </div>

        <Dialog v-model:visible="productDialog" :style="{ width: '600px' }" header="Makro" :modal="true">
        <div class="flex flex-col gap-6">

            <div class="grid grid-cols-12 gap-4">
            <div class="col-span-6">
                <label class="block text-normal mb-1">Názov</label>
                <InputText
                v-model.trim="product.name"
                fluid
                :invalid="submitted && !product.name"
                />
                <small v-if="submitted && !product.name" class="text-warning">
                Názov je povinný.
                </small>
            </div>

            <div class="col-span-6">
                <label class="block text-normal mb-1">Skratka</label>
                <InputText
                v-model.trim="product.abbreviation"
                fluid
                :invalid="submitted && !product.abbreviation"
                />
                <small v-if="submitted && !product.abbreviation" class="text-warning">
                Skratka je povinná.
                </small>
            </div>
            </div>

            <div>
            <label class="block text-normal mb-1">Text</label>
            <Textarea
                v-model.trim="product.text"
                rows="5"
                fluid
                :invalid="submitted && !product.text"
            />
            <small v-if="submitted && !product.text" class="text-warning">
                Text je povinný.
            </small>
            </div>

        </div>

        <template #footer>
            <Button
            label="Uložiť"
            class="!bg-accent !px-md !text-white hover:!bg-darkgrey"
            @click="saveProduct"
            />
        </template>
        </Dialog>



        <Dialog v-model:visible="deleteProductsDialog" :style="{ width: '600px'}" :modal="true" :closable="false" header="Upozornenie">
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
