<script setup lang="ts">
import { ref, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';
import api from '@/services/api';
import type { Diagnosis, Procedure } from '@/types/models';

type Option = {
  id: number;
  code: string;
  description: string;
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

/* ---------- DIAGNOSES ---------- */

const diagnosis = ref<Option | null>(null);
const filteredDiagnoses = ref<Option[]>([]);

async function searchDiagnoses(event: { query: string }) {
  try {
    const q = event.query?.trim() ?? '';

    if (!q || q.length < 1) {
      filteredDiagnoses.value = [];
      return;
    }

    const { data } = await api.get<Diagnosis[]>('/v1/diagnoses', {
      params: { q },
    });

    filteredDiagnoses.value = data.map((d) => ({
      id: d.id,
      code: d.code ?? '',
      description: d.description ?? '',
    }));
  } catch (e) {
    console.error('Failed to load diagnoses', e);
    filteredDiagnoses.value = [];
  }
}

/* ---------- PROCEDURES ---------- */

const procedure = ref<Option | null>(null);
const filteredProcedures = ref<Option[]>([]);

async function searchProcedures(event: { query: string }) {
  try {
    const q = event.query?.trim() ?? '';

    if (!q || q.length < 1) {
      filteredProcedures.value = [];
      return;
    }

    const { data } = await api.get<Procedure[]>('/v1/procedures', {
      params: { q },
    });

    filteredProcedures.value = data.map((p) => ({
      id: p.id,
      code: p.code ?? '',
      description: p.description ?? '',
    }));
  } catch (e) {
    console.error('Failed to load procedures', e);
    filteredProcedures.value = [];
  }
}

/* ---------- NORMALIZATION HELPERS ---------- */

// Parse manual date input like "1.2.25", "01.02.2025", "1.2.2025"
function parseDateInput(raw: unknown): Date | null {
  if (raw instanceof Date) {
    return isNaN(raw.getTime()) ? null : raw;
  }

  if (typeof raw !== 'string') return null;

  const value = raw.trim();
  if (!value) return null;

  // dd.mm.yy or dd.mm.yyyy
  const match = value.match(/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/);
  if (!match) return null;

  let [, dStr, mStr, yStr] = match as RegExpMatchArray;
  const day = Number(dStr);
  const month = Number(mStr);
  let year = Number(yStr);

  if (yStr!.length === 2) {
    year += 2000;
  }

  // Basic sanity checks
  if (month < 1 || month > 12 || day < 1 || day > 31) return null;

  const result = new Date(year, month - 1, day);

  // Validate date (e.g. 31.02.2025 should be rejected)
  if (
    result.getFullYear() !== year ||
    result.getMonth() !== month - 1 ||
    result.getDate() !== day
  ) {
    return null;
  }

  return result;
}

// Resolve diagnosis if user only typed the code
async function ensureDiagnosisSelected(): Promise<boolean> {
  const value = diagnosis.value as unknown;

  // already an object with id → ok
  if (value && typeof value === 'object' && 'id' in (value as any)) {
    return true;
  }

  const raw = (value as string | undefined) ?? '';
  const code = raw.trim();
  if (!code) {
    diagnosis.value = null;
    return false;
  }

  try {
    const { data } = await api.get<Diagnosis[]>('/v1/diagnoses', {
      params: { q: code },
    });

    const match = data.find(
      (d) => (d.code ?? '').toLowerCase() === code.toLowerCase(),
    );

    if (!match) {
      diagnosis.value = null;
      return false;
    }

    diagnosis.value = {
      id: match.id,
      code: match.code ?? '',
      description: match.description ?? '',
    };

    return true;
  } catch (e) {
    console.error('Failed to resolve diagnosis by code', e);
    diagnosis.value = null;
    return false;
  }
}

// Resolve procedure if user only typed the code
async function ensureProcedureSelected(): Promise<boolean> {
  const value = procedure.value as unknown;

  if (value && typeof value === 'object' && 'id' in (value as any)) {
    return true;
  }

  const raw = (value as string | undefined) ?? '';
  const code = raw.trim();
  if (!code) {
    procedure.value = null;
    return false;
  }

  try {
    const { data } = await api.get<Procedure[]>('/v1/procedures', {
      params: { q: code },
    });

    const match = data.find(
      (p) => (p.code ?? '').toLowerCase() === code.toLowerCase(),
    );

    if (!match) {
      procedure.value = null;
      return false;
    }

    procedure.value = {
      id: match.id,
      code: match.code ?? '',
      description: match.description ?? '',
    };

    return true;
  } catch (e) {
    console.error('Failed to resolve procedure by code', e);
    procedure.value = null;
    return false;
  }
}

/* ---------- REST OF YOUR CODE ---------- */

const submitted = ref(false);
const toast = useToast();

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

async function onSubmit() {
  submitted.value = true;

  // Normalize dates (in case user typed them)
  const normalizedDate = parseDateInput(date.value as any);
  const normalizedReferralDate = parseDateInput(referralDate.value as any);

  date.value = normalizedDate;
  referralDate.value = normalizedReferralDate;

  // Normalize diagnosis / procedure if user typed only the code
  const diagnosisOk = await ensureDiagnosisSelected();
  const procedureOk = await ensureProcedureSelected();

  if (!date.value || !diagnosisOk || !procedureOk || !referralDate.value) {
    toast.add({
      severity: 'warn',
      summary: 'Chýbajúce alebo neplatné údaje',
      detail:
        'Skontrolujte dátumy, kódy diagnóz a výkonov. Kódy musia existovať v databáze.',
      life: 4000,
    });
    return;
  }

  const newId =
    records.value.length > 0
      ? Math.max(...records.value.map((r) => r.id)) + 1
      : 1;

  const payload: RecordEntry = {
    id: newId,
    date: date.value,
    diagnosis: diagnosis.value,
    procedure: procedure.value,
    referralDate: referralDate.value,
  };

  records.value.push(payload);
  emit('submit', payload);

  toast.add({
    severity: 'success',
    summary: 'Uložené',
    detail: 'Záznam bol pridaný.',
    life: 3000,
  });

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
  const idsToDelete = new Set(selectedRecords.value.map((r) => r.id));
  records.value = records.value.filter((r) => !idsToDelete.has(r.id));
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

  const baseId =
    records.value.length > 0
      ? Math.max(...records.value.map((r) => r.id))
      : 0;

  let nextId = baseId + 1;

  const clones = selectedRecords.value.map((r) => ({
    ...r,
    id: nextId++,
    date: today,
    referralDate: today,
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

          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Diagnóza</label>
              <AutoComplete
              v-model="diagnosis"
              :suggestions="filteredDiagnoses"
              optionLabel="code"
              :minLength="1"
              @complete="searchDiagnoses"
              class="w-full"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
              >
                <template #option="slotProps">
                  <div class="flex flex-col">
                    <span>{{ slotProps.option.code }} – {{ slotProps.option.description }}</span>
                  </div>
                </template>
              </AutoComplete>
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
              optionLabel="code"
              :minLength="1"
              @complete="searchProcedures"
              class="w-full"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
              >
                  <template #option="slotProps">
                    <div class="flex flex-col">
                      <span>{{ slotProps.option.code }} – {{ slotProps.option.description }}</span>
                    </div>
                  </template>
                </AutoComplete>
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
              {{ slotProps.data.diagnosis.code }} – {{ slotProps.data.diagnosis.description }}
            </span>
          </template>
        </Column>

        <Column field="procedure" header="Výkon" sortable>
          <template #body="slotProps">
            <span v-if="slotProps.data.procedure">
              {{ slotProps.data.procedure.code }} – {{ slotProps.data.procedure.description }}
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
