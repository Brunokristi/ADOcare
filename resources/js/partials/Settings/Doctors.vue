<script setup>
import { ref, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';

const dt = ref(null);

// Raw data
const rows = ref([
    { id: 1, firstname: 'Viliam', lastname: 'Džurbala', title: 'MUDr.', zpr: 'A95466020', pzs: 'P58480020202', favourite: true },
    { id: 2, firstname: 'Jana', lastname: 'Kováčová', title: 'MUDr.', zpr: 'A12457893', pzs: 'P58480020203', favourite: false },
    { id: 3, firstname: 'Peter', lastname: 'Horváth', title: 'MUDr.', zpr: 'A87456231', pzs: 'P58480020204', favourite: false },
    { id: 4, firstname: 'Lucia', lastname: 'Marekova', title: 'MUDr.', zpr: 'A65231478', pzs: 'P58480020205', favourite: false },
    { id: 5, firstname: 'Martin', lastname: 'Šimek', title: 'MUDr.', zpr: 'A98745621', pzs: 'P58480020206', favourite: false },
    { id: 6, firstname: 'Simona', lastname: 'Krajčíková', title: 'MUDr.', zpr: 'A65478932', pzs: 'P58480020207', favourite: false },
    { id: 7, firstname: 'Tomáš', lastname: 'Novotný', title: 'MUDr.', zpr: 'A78451269', pzs: 'P58480020208', favourite: false },
    { id: 8, firstname: 'Katarína', lastname: 'Valentová', title: 'MUDr.', zpr: 'A54123687', pzs: 'P58480020209', favourite: true },
    { id: 9, firstname: 'Andrej', lastname: 'Bielik', title: 'MUDr.', zpr: 'A96325847', pzs: 'P58480020210', favourite: false },
    { id: 10, firstname: 'Mária', lastname: 'Zelená', title: 'MUDr.', zpr: 'A75395146', pzs: 'P58480020211', favourite: false },
    { id: 11, firstname: 'Rastislav', lastname: 'Urban', title: 'MUDr.', zpr: 'A25874139', pzs: 'P58480020212', favourite: false },
    { id: 12, firstname: 'Veronika', lastname: 'Foltínová', title: 'MUDr.', zpr: 'A15948732', pzs: 'P58480020213', favourite: false },
    { id: 13, firstname: 'Patrik', lastname: 'Holub', title: 'MUDr.', zpr: 'A48715926', pzs: 'P58480020214', favourite: false },
    { id: 14, firstname: 'Barbora', lastname: 'Kalinová', title: 'MUDr.', zpr: 'A36987412', pzs: 'P58480020215', favourite: true },
    { id: 15, firstname: 'Marek', lastname: 'Škoda', title: 'MUDr.', zpr: 'A24861379', pzs: 'P58480020216', favourite: false },
    { id: 16, firstname: 'Nikola', lastname: 'Veselá', title: 'MUDr.', zpr: 'A95175348', pzs: 'P58480020217', favourite: false },
    { id: 17, firstname: 'Adam', lastname: 'Krajčír', title: 'MUDr.', zpr: 'A78641235', pzs: 'P58480020218', favourite: false },
    { id: 18, firstname: 'Eva', lastname: 'Malíková', title: 'MUDr.', zpr: 'A31478592', pzs: 'P58480020219', favourite: false },
    { id: 19, firstname: 'Filip', lastname: 'Konečný', title: 'MUDr.', zpr: 'A62574381', pzs: 'P58480020220', favourite: false },
    { id: 20, firstname: 'Daniela', lastname: 'Hrivnáková', title: 'MUDr.', zpr: 'A85741236', pzs: 'P58480020221', favourite: false },
]);

// local reactive copy
const products = ref([...rows.value]);

// Search filter
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// Toggle: show only favourites?
const showOnlyFav = ref(false);

// Computed filtered data (before search)
const filteredBase = computed(() => {
    return showOnlyFav.value
        ? products.value.filter(p => p.favourite)
        : products.value;
});

// DataTable uses `filteredBase` as its value
const tableData = computed(() => filteredBase.value);

// Record counter text
const recordsInfo = computed(() => {
    if (!dt.value) return '';

    const total = filteredBase.value.length;
    const filtered = dt.value.processedData?.length;

    if (filtered == null) return `${total} z ${total} záznamov`;
    return `${filtered} z ${total} záznamov`;
});

// Toggle favourite
const toggleFavourite = (row) => {
    row.favourite = !row.favourite;
};
</script>

<template>
  <div>

    <Toolbar class="!bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between">

      <template #end>
        <div class="flex items-center gap-4">

          <IconField>
            <InputText v-model="filters['global'].value" />
            <InputIcon>
              <i class="bi bi-search text-darkgrey" />
            </InputIcon>
          </IconField>

          <Button
            icon="bi bi-heart-fill"
            @click="showOnlyFav = !showOnlyFav"
            class="!bg-accent !border-accent !text-white hover:!bg-darkgrey"
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
    >
      <Column field="firstname" header="Meno" sortable />
      <Column field="lastname" header="Priezvisko" sortable />
      <Column field="title" header="Titul" sortable />
      <Column field="zpr" header="ZPR" sortable />
      <Column field="pzs" header="PZS" sortable />

      <!-- Favourite icon -->
      <Column header=" " style="width: 3rem">
        <template #body="{ data }">
          <Button
            :icon="data.favourite ? 'bi bi-heart-fill' : 'bi bi-heart'"
            @click="toggleFavourite(data)"
            variant="text"
            class="!text-accent hover:!bg-transparent p-0"
          />
        </template>
      </Column>

    </DataTable>

    <div class="text-mini text-accent flex justify-end w-full py-2">
      {{ recordsInfo }}
    </div>

  </div>
</template>
