<script setup lang="ts">
import { ref, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';

type Option = {
  code: string;
  name: string;
};

type RecordEntry = {
  id: number;
  date: Date | null;
  diagnosis: Option | null;
  procedure: Option | null;
  referralDate: Date | null;
};

const emit = defineEmits<{
  (e: 'submit', payload: RecordEntry): void;
}>();


const date = ref<Date | null>(new Date());
const referralDate = ref<Date | null>(null);

const diagnosis = ref<Option | null>(null);
const diagnoses = ref<Option[]>([
  { code: 'I10', name: 'Hypertenzná choroba (I10)' },
  { code: 'E11', name: 'Diabetes mellitus 2. typu (E11)' },
  { code: 'J45', name: 'Astma (J45)' },
]);

const filteredDiagnoses = ref<Option[]>([]);

function searchDiagnoses(event: { query: string }) {
  const q = event.query.toLowerCase();
  filteredDiagnoses.value = diagnoses.value.filter(d =>
    d.code.toLowerCase().includes(q) ||
    d.name.toLowerCase().includes(q),
  );
}

// procedures
const procedure = ref<Option | null>(null);
const procedures = ref<Option[]>([
  { code: '213', name: 'Ošetrenie v domácom prostredí' },
  { code: '310', name: 'Kontrolné vyšetrenie' },
  { code: '411', name: 'Odber biologického materiálu' },
]);

const filteredProcedures = ref<Option[]>([]);

function searchProcedures(event: { query: string }) {
  const q = event.query.toLowerCase();
  filteredProcedures.value = procedures.value.filter(p =>
    p.code.toLowerCase().includes(q) ||
    p.name.toLowerCase().includes(q),
  );
}

const submitted = ref(false);
const toast = useToast();

/* ---------- TABLE STATE ---------- */

const records = ref<RecordEntry[]>([]);
const selectedRecords = ref<RecordEntry[]>([]);
const deleteRecordsDialog = ref(false);

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const recordsInfo = computed(() => {
  const total = records.value.length;
  return total ? `Počet záznamov: ${total}` : 'Žiadne záznamy';
});

function formatDate(d: Date | null) {
  if (!d) return '';
  return d.toLocaleDateString('sk-SK');
}

/* ---------- FORM SUBMIT ---------- */

function onSubmit() {
  submitted.value = true;

  if (!date.value || !diagnosis.value || !procedure.value || !referralDate.value) {
    toast.add({
      severity: 'warn',
      summary: 'Chýbajúce údaje',
      detail: 'Vyplňte prosím všetky povinné polia.',
      life: 3000,
    });
    return;
  }

  const newId =
    records.value.length > 0
      ? Math.max(...records.value.map(r => r.id)) + 1
      : 1;

  const payload: RecordEntry = {
    id: newId,
    date: date.value,
    diagnosis: diagnosis.value,
    procedure: procedure.value,
    referralDate: referralDate.value,
  };

  // Add to local table
  records.value.push(payload);

  // Emit to parent if needed
  emit('submit', payload);

  toast.add({
    severity: 'success',
    summary: 'Uložené',
    detail: 'Záznam bol pridaný.',
    life: 3000,
  });

  // Reset form if you like
  date.value = new Date();
  diagnosis.value = null;
  procedure.value = null;
  referralDate.value = null;
  submitted.value = false;
}

/* ---------- DELETE SELECTED ---------- */

function confirmDeleteSelected() {
  if (!selectedRecords.value || !selectedRecords.value.length) return;
  deleteRecordsDialog.value = true;
}

function deleteSelected() {
  const idsToDelete = new Set(selectedRecords.value.map(r => r.id));
  records.value = records.value.filter(r => !idsToDelete.has(r.id));
  selectedRecords.value = [];
  deleteRecordsDialog.value = false;

  toast.add({
    severity: 'success',
    summary: 'Vymazané',
    detail: 'Vybrané záznamy boli vymazané.',
    life: 3000,
  });
}

function duplicateSelected() {
  if (!selectedRecords.value || !selectedRecords.value.length) return;

  const today = new Date();

  // find the highest current id so we keep ids unique
  const baseId =
    records.value.length > 0
      ? Math.max(...records.value.map(r => r.id))
      : 0;

  let nextId = baseId + 1;

  const clones = selectedRecords.value.map(r => ({
    ...r,
    id: nextId++,           // new unique id
    date: today,            // today
    referralDate: today,    // today
  }));

  records.value.push(...clones);

  toast.add({
    severity: 'success',
    summary: 'Duplikované',
    detail: 'Vybrané záznamy boli duplikované s dnešným dátumom.',
    life: 3000,
  });
}

</script>

<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-12 gap-4">
          <!-- Dátum -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Dátum</label>
            <Calendar
              v-model="date"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="submitted && !date" class="text-warning">
              Dátum je povinný.
            </small>
          </div>

          <!-- Diagnóza -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Diagnóza</label>
            <AutoComplete
              v-model="diagnosis"
              :suggestions="filteredDiagnoses"
              field="name"
              :minLength="1"
              @complete="searchDiagnoses"
              class="w-full"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="submitted && !diagnosis" class="text-warning">
              Diagnóza je povinná.
            </small>
          </div>

          <!-- Výkon -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Výkon</label>
            <AutoComplete
              v-model="procedure"
              :suggestions="filteredProcedures"
              field="name"
              :minLength="1"
              @complete="searchProcedures"
              class="w-full"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="submitted && !procedure" class="text-warning">
              Výkon je povinný.
            </small>
          </div>

          <!-- Dátum odporučenia -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Dátum odporučenia</label>
            <Calendar
              v-model="referralDate"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="submitted && !referralDate" class="text-warning">
              Dátum je povinný.
            </small>
          </div>
        </div>
      </section>

      <div class="flex justify-end">
        <Button
          type="submit"
          class="relative flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey px-4 py-2 rounded-md text-white w-100"
        >
          Pridať
          <i
            class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent"
          />
        </Button>
      </div>
    </form>

    <div>
      <Toolbar
        class="!bg-transparent !border-0 !shadow-none flex items-center justify-between py-3 !px-0"
      >

        <template #end>
          <div class="flex items-center gap-2">
            <IconField>
              <InputText v-model="filters['global'].value" />
              <InputIcon>
                <i class="bi bi-search text-darkgrey" />
              </InputIcon>
            </IconField>

            <Button
            icon="bi bi-copy"
            @click="duplicateSelected"
            :disabled="!selectedRecords || !selectedRecords.length"
            class="!bg-accent !border-accent !text-white"
            />


            <Button
              icon="bi bi-eraser"
              @click="confirmDeleteSelected"
              :disabled="!selectedRecords || !selectedRecords.length"
              class="!bg-warning !border-warning !text-white"
            />
          </div>
        </template>
      </Toolbar>

      <DataTable
        v-model:selection="selectedRecords"
        :value="records"
        dataKey="id"
        :filters="filters"
        stripedRows
        removableSort
        scrollable
        scrollHeight="400px"
        class="text-sm"
      >
        <Column
          selectionMode="multiple"
          headerStyle="width: 3rem"
          :exportable="false"
        />

        <Column field="date" header="Dátum" sortable>
          <template #body="slotProps">
            {{ formatDate(slotProps.data.date) }}
          </template>
        </Column>

        <Column field="diagnosis" header="Diagnóza" sortable>
          <template #body="slotProps">
            <span v-if="slotProps.data.diagnosis">
              {{ slotProps.data.diagnosis.code }} – {{ slotProps.data.diagnosis.name }}
            </span>
          </template>
        </Column>

        <Column field="procedure" header="Výkon" sortable>
          <template #body="slotProps">
            <span v-if="slotProps.data.procedure">
              {{ slotProps.data.procedure.code }} – {{ slotProps.data.procedure.name }}
            </span>
          </template>
        </Column>

        <Column field="referralDate" header="Dátum odporučenia" sortable>
          <template #body="slotProps">
            {{ formatDate(slotProps.data.referralDate) }}
          </template>
        </Column>
      </DataTable>

      <div class="text-mini text-accent flex justify-end w-full py-2">
        {{ recordsInfo }}
      </div>

      <Dialog
        v-model:visible="deleteRecordsDialog"
        :style="{ width: '600px' }"
        :modal="true"
        :closable="false"
        header="Upozornenie"
      >
        <div class="flex items-center justify-between w-full">
          <span class="text-heading">
            Naozaj si prajete vymazať vybrané záznamy?
          </span>

          <div class="flex items-center gap-2">
            <Button
              label="Nie"
              text
              @click="deleteRecordsDialog = false"
              class="!bg-accent !px-4 !text-white hover:!bg-darkgrey !border-0"
            />
            <Button
              label="Áno"
              text
              @click="deleteSelected"
              class="!bg-warning !px-4 !text-white"
            />
          </div>
        </div>
      </Dialog>
    </div>
  </div>
</template>
