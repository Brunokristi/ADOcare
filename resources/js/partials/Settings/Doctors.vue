<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';

const toast = useToast();
const authStore = useAuthStore();

const branchId = computed(() => authStore.currentBranch?.id ?? null);

const dt = ref(null);
const rows = ref([]); // doctors from API

const loading = ref(false);
const showOnlyFav = ref(false);

// Search filter
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// -------- Fetch doctors --------
async function loadDoctors() {
  if (!branchId.value) return;

  loading.value = true;
  try {
    const res = await api.get('/v1/doctors', {
      params: {
        branch_id: branchId.value,
        favourites: showOnlyFav.value ? 1 : 0,
        // paginate: false, // if your ApiQuery supports it and you want all
      },
    });

    const items = res.data?.data?.items ?? [];
    rows.value = items.map((d) => ({
      id: d.id,
      firstname: d.first_name ?? '',
      lastname: d.last_name ?? '',
      title: d.title ?? '',
      zpr: d.zpr ?? '',
      pzs: d.pzs ?? '',
      // normalize true/false
      is_favourite: !!d.is_favourite,
      _api: d,
    }));
  } catch (e) {
    console.error(e);
    toast.add({
      severity: 'error',
      summary: 'Chyba pri načítaní',
      detail: 'Lekárov sa nepodarilo načítať.',
      life: 4000,
    });
  } finally {
    loading.value = false;
  }
}

// -------- DataTable source (before built-in search) --------
const tableData = computed(() => rows.value);

// -------- Records info --------
const recordsInfo = computed(() => {
  if (!dt.value) {
    const total = rows.value.length;
    return `${total} z ${total} záznamov`;
  }

  const total = rows.value.length;
  const filtered = dt.value.processedData?.length;

  if (filtered == null) return `${total} z ${total} záznamov`;
  return `${filtered} z ${total} záznamov`;
});

// -------- Toggle favourite (UI + optional backend) --------
async function toggleFavourite(row) {
  // optimistic UI
  row.is_favourite = !row.is_favourite;

  // OPTIONAL: persist to backend (recommended)
  // You need endpoints like:
  // POST   /v1/branches/{branch}/doctors/{doctor}   -> attach
  // DELETE /v1/branches/{branch}/doctors/{doctor}   -> detach
  // If you don't have them yet, you can delete this try/catch and it will remain UI-only.
  try {
    if (row.is_favourite) {
      await api.post(`/v1/branches/${branchId.value}/doctors/${row.id}`);
      toast.add({
        severity: 'success',
        summary: 'Pridané medzi obľúbené',
        detail: 'Lekár bol pridaný medzi obľúbené.',
        life: 2000,
      });
    } else {
      await api.delete(`/v1/branches/${branchId.value}/doctors/${row.id}`);
      toast.add({
        severity: 'success',
        summary: 'Odstránené z obľúbených',
        detail: 'Lekár bol odstránený z obľúbených.',
        life: 2000,
      });
    }
  } catch (e) {
    console.error(e);

    // rollback UI if backend failed
    row.is_favourite = !row.is_favourite;

    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa zmeniť obľúbeného lekára.',
      life: 4000,
    });
  }
}

// When branch changes or toggle changes, reload
watch(
  () => branchId.value,
  (id) => {
    if (!id) return;
    loadDoctors();
  },
  { immediate: true }
);

watch(showOnlyFav, () => {
  loadDoctors();
});

onMounted(() => {
  // in case branchId already exists
  if (branchId.value) loadDoctors();
});
</script>

<template>
  <div>
    <Toolbar class="!bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between">
      <template #end>
        <div class="flex items-center gap-4">
          <IconField>
            <InputText v-model="filters['global'].value" placeholder="Hľadať..." />
            <InputIcon>
              <i class="bi bi-search text-darkgrey" />
            </InputIcon>
          </IconField>

          <Button
            :icon="showOnlyFav ? 'bi bi-heart-fill' : 'bi bi-heart'"
            @click="showOnlyFav = !showOnlyFav"
            class="!bg-accent !border-accent !text-white hover:!bg-darkgrey"
            v-tooltip.bottom="showOnlyFav ? 'Zobraziť všetkých' : 'Len obľúbení'"
          />
        </div>
      </template>
    </Toolbar>

    <DataTable
      ref="dt"
      :value="tableData"
      dataKey="id"
      :filters="filters"
      stripedRows
      removableSort
      scrollable
      scrollHeight="600px"
      :loading="loading"
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
            class="!text-darkgrey hover:!bg-transparent p-0"
          />
        </template>
      </Column>
    </DataTable>

    <div class="text-mini text-accent flex justify-end w-full py-2">
      {{ recordsInfo }}
    </div>
  </div>
</template>
