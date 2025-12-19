<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';

import api from '@/services/api';
import { toApiDate } from '@/utils/dateUtils';
import { usePatientStore } from '@/stores/patientStore';
import { useAuthStore } from '@/stores/auth';
import type { Diagnosis, Procedure, Patient } from '@/types/models';

/* -------------------------------------------------------------------------- */
/*  Types                                                                     */
/* -------------------------------------------------------------------------- */

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
  quantity: number | null; 
};

type PatientPointApi = {
  id: number;
  date: string | null;
  patient_personal_number: string | null;
  patient_name: string | null;
  patient_id: number;
  diagnosis_code: string | null;
  diagnosis_id: number | null;
  procedure_code: string | null;
  procedure_id: number | null;
  reference_date: string | null;
  user_id: number;
  branch_id: number;
  quantity: number | null; 
};

/* -------------------------------------------------------------------------- */
/*  Stores & Refs                                                             */
/* -------------------------------------------------------------------------- */

const patientStore = usePatientStore();
patientStore.loadFromStorage();

const authStore = useAuthStore();

const { current: currentPatient } = storeToRefs(patientStore);
const { user, currentBranch } = storeToRefs(authStore);

console.log('currentPatient', currentPatient.value);

const emit = defineEmits<{
  (e: 'submit', payload: RecordEntry): void;
}>();

const toast = useToast();

const isLoading = ref(false);

const date = ref<Date | null>(new Date());
const referralDate = ref<Date | null>(null);

const diagnosis = ref<Option | null>(null);
const filteredDiagnoses = ref<Option[]>([]);

const procedure = ref<Option | null>(null);
const filteredProcedures = ref<Option[]>([]);

const quantity = ref<number | null>(1);

const submitted = ref(false);

const records = ref<RecordEntry[]>([]);
const selectedRecords = ref<RecordEntry[]>([]);
const deleteRecordsDialog = ref(false);

const pointDialog = ref(false);
const editSubmitted = ref(false);
const editPoint = ref<RecordEntry | null>(null);

/* -------------------------------------------------------------------------- */
/*  Table helpers                                                             */
/* -------------------------------------------------------------------------- */

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




/* -------------------------------------------------------------------------- */
/*  API: Load existing patient points                                         */
/* -------------------------------------------------------------------------- */

async function loadRecordsForPatient() {
  if (!currentPatient.value) {
    records.value = [];
    return;
  }

  isLoading.value = true;

  try {
    const { data } = await api.get<PatientPointApi[]>('/v1/patient-points', {
      params: {
        patient_id: currentPatient.value.id,
        paginate: false,
      },
    });

    records.value = data.map((row) => ({
      id: row.id,
      date: row.date ? new Date(row.date) : null,
      diagnosis: row.diagnosis_id
        ? {
            id: row.diagnosis_id,
            code: row.diagnosis_code ?? '',
            description: '',
          }
        : null,
      procedure: row.procedure_id
        ? {
            id: row.procedure_id,
            code: row.procedure_code ?? '',
            description: '',
          }
        : null,
      referralDate: row.reference_date ? new Date(row.reference_date) : null,
      quantity: row.quantity ?? null,
    }));

  } catch (e) {
    console.error('Failed to load patient points', e);
    toast.add({
      severity: 'error',
      summary: 'Chyba načítania',
      detail: 'Nepodarilo sa načítať body pacienta.',
      life: 4000,
    });
    records.value = [];
  } finally {
    isLoading.value = false;
  }
}


/* -------------------------------------------------------------------------- */
/*  Fetch referal date from database                                          */
/* -------------------------------------------------------------------------- */

function initReferenceDateFromPatient() {
  if (!currentPatient.value || !currentPatient.value.reference_date) {
    referralDate.value = null;
    return;
  }

  // currentPatient.reference_date is assumed YYYY-MM-DD
  const d = new Date(currentPatient.value.reference_date);
  referralDate.value = isNaN(d.getTime()) ? null : d;
}


/* -------------------------------------------------------------------------- */
/*  Lookup: Diagnoses & Procedures                                            */
/* -------------------------------------------------------------------------- */

async function searchDiagnoses(event: { query: string }) {
  try {
    const q = event.query?.trim() ?? '';

    if (!q || q.length < 1) {
      filteredDiagnoses.value = [];
      return;
    }

    const res = await api.get('/v1/diagnoses', { params: { q } });

    // normalize common API shapes
    const raw = res.data;
    const arr =
      Array.isArray(raw) ? raw :
      Array.isArray(raw?.data) ? raw.data :
      Array.isArray(raw?.data?.items) ? raw.data.items :
      Array.isArray(raw?.items) ? raw.items :
      [];

    filteredDiagnoses.value = (arr as Diagnosis[]).map((d) => ({
      id: d.id,
      code: d.code ?? '',
      description: d.description ?? '',
    }));
  } catch (e) {
    console.error('Failed to load diagnoses', e);
    filteredDiagnoses.value = [];
  }
}


async function searchProcedures(event: { query: string }) {
  try {
    const q = event.query?.trim() ?? '';

    if (!q || q.length < 1) {
      filteredProcedures.value = [];
      return;
    }

    const res = await api.get('/v1/procedures', { params: { q } });

    // normalize common API shapes
    const raw = res.data;
    const arr =
      Array.isArray(raw) ? raw :
      Array.isArray(raw?.data) ? raw.data :
      Array.isArray(raw?.data?.items) ? raw.data.items :
      Array.isArray(raw?.items) ? raw.items :
      [];

    filteredProcedures.value = (arr as Procedure[]).map((p) => ({
      id: p.id,
      code: p.code ?? '',
      description: p.description ?? '',
    }));
  } catch (e) {
    console.error('Failed to load procedures', e);
    filteredProcedures.value = [];
  }
}


/* -------------------------------------------------------------------------- */
/*  Normalization helpers                                                     */
/* -------------------------------------------------------------------------- */

function parseDateInput(raw: unknown): Date | null {
  if (raw instanceof Date) {
    return isNaN(raw.getTime()) ? null : raw;
  }

  if (typeof raw !== 'string') return null;

  const value = raw.trim();
  if (!value) return null;

  const match = value.match(/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/);
  if (!match) return null;

  const [, dStr, mStr, yStr] = match as RegExpMatchArray;

  if (!dStr || !mStr || !yStr) return null;

  const day = Number(dStr);
  const month = Number(mStr);
  let year = Number(yStr);

  if (yStr.length === 2) {
    year += 2000;
  }

  if (month < 1 || month > 12 || day < 1 || day > 31) return null;

  const result = new Date(year, month - 1, day);

  if (
    result.getFullYear() !== year ||
    result.getMonth() !== month - 1 ||
    result.getDate() !== day
  ) {
    return null;
  }

  return result;
}

async function ensureDiagnosisSelected(): Promise<boolean> {
  const value = diagnosis.value as unknown;

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

/* -------------------------------------------------------------------------- */
/*  Payload builders                                                          */
/* -------------------------------------------------------------------------- */

function buildPatientPointPayload() {
  if (!currentPatient.value) {
    throw new Error('No patient selected');
  }

  const patient = currentPatient.value;
  const doctor = patient.doctor;

  const fullName = `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim();

  return {
    date: toApiDate(date.value),
    patient_personal_number: patient.personal_number,
    patient_name: fullName,
    patient_id: patient.id,

    diagnosis_code: diagnosis.value!.code,
    diagnosis_id: diagnosis.value!.id,

    procedure_code: procedure.value!.code,
    procedure_id: procedure.value!.id,

    doctor_pzs: doctor?.pzs ?? null,
    doctor_zpr: doctor?.zpr ?? null,
    doctor_id: doctor?.id ?? null,

    reference_date: toApiDate(referralDate.value),
    user_id: user.value?.id ?? null,
    branch_id: currentBranch.value?.id ?? null,
    quantity: quantity.value,
  };
}

function buildPayloadFromRow(row: RecordEntry, dateOverride: Date) {
  if (!currentPatient.value) {
    throw new Error('No patient selected');
  }

  const patient = currentPatient.value;
  const fullName = `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim();

  const doctorRel = (patient as any).doctor ?? null;
  const doctorId = doctorRel?.id ?? patient.doctor_id ?? null;

  return {
    date: toApiDate(dateOverride),

    patient_personal_number: patient.personal_number,
    patient_name: fullName,
    patient_id: patient.id,

    diagnosis_code: row.diagnosis?.code ?? '',
    diagnosis_id: row.diagnosis?.id ?? null,

    procedure_code: row.procedure?.code ?? '',
    procedure_id: row.procedure?.id ?? null,

    doctor_pzs: doctorRel?.pzs ?? null,
    doctor_zpr: doctorRel?.zpr ?? null,
    doctor_id: doctorId,

    reference_date: toApiDate(dateOverride),
    user_id: user.value?.id ?? null,
    branch_id: currentBranch.value!.id,
    quantity: quantity.value,
  };
}

/* -------------------------------------------------------------------------- */
/*  Form submit (create)                                                      */
/* -------------------------------------------------------------------------- */

async function onSubmit() {
  submitted.value = true;

  // normalize dates
  date.value = parseDateInput(date.value as any);
  referralDate.value = parseDateInput(referralDate.value as any);

  const diagnosisOk = await ensureDiagnosisSelected();
  const procedureOk = await ensureProcedureSelected();

  // just let <small> validation messages show
  if (!date.value || !diagnosisOk || !procedureOk || !referralDate.value || !quantity.value || quantity.value! <= 0) {
    return;
  }

  if (!currentPatient.value) {
    toast.add({
      severity: 'error',
      summary: 'Chýbajúci pacient',
      detail: 'Najprv vyberte pacienta.',
      life: 3000,
    });
    return;
  }

  let apiPayload;
  try {
    apiPayload = buildPatientPointPayload();
  } catch (e: any) {
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: e.message,
      life: 3000,
    });
    return;
  }

  console.log('Sending payload:', apiPayload);

  try {
    if (currentPatient.value && referralDate.value) {
      const refDate = toApiDate(referralDate.value);

      await api.put(`/v1/patients/${currentPatient.value.id}`, {
        reference_date: refDate,
      });

      const updatedPatient: Patient = {
        ...(currentPatient.value as Patient),
        reference_date: refDate,
      };

      patientStore.setPatient(updatedPatient); // 👈 this writes to localStorage
    }



    await api.post('/v1/patient-points', apiPayload);
    const newId =
      records.value.length > 0
        ? Math.max(...records.value.map((r) => r.id)) + 1
        : 1;

    const entry: RecordEntry = {
      id: newId,
      date: date.value,
      diagnosis: diagnosis.value,
      procedure: procedure.value,
      referralDate: referralDate.value,
      quantity: quantity.value,
    };

    records.value.push(entry);
    emit('submit', entry);

    toast.add({
      severity: 'success',
      summary: 'Uložené',
      detail: 'Záznam bol uložený.',
      life: 3000,
    });

    // reset form
    date.value = new Date();
    diagnosis.value = null;
    procedure.value = null;
    quantity.value = 1;
    submitted.value = false;
  } catch (error: any) {
    console.error('422 error:', error.response?.data);

    const msg =
      error.response?.data?.errors
        ? Object.values(error.response.data.errors).flat()[0]
        : error.response?.data?.message;

    toast.add({
      severity: 'error',
      summary: 'Neuložené',
      detail: msg ?? 'Záznam sa nepodarilo uložiť.',
      life: 6000,
    });
  }
}


/* -------------------------------------------------------------------------- */
/*  Edit dialog                                                               */
/* -------------------------------------------------------------------------- */

function editRecord(row: RecordEntry) {
  editSubmitted.value = false;

  editPoint.value = {
    ...row,
    date: row.date ? new Date(row.date) : null,
    referralDate: row.referralDate ? new Date(row.referralDate) : null,
    diagnosis: row.diagnosis ? { ...row.diagnosis } : null,
    procedure: row.procedure ? { ...row.procedure } : null,
    quantity: row.quantity ?? null,
  };

  pointDialog.value = true;
}

async function savePoint() {
  if (!editPoint.value) return;

  editSubmitted.value = true;

  const p = editPoint.value;

  // normalize dates coming from the dialog (Calendar or text)
  const normalizedDate = parseDateInput(p.date as any);
  const normalizedReferral = parseDateInput(p.referralDate as any);

  p.date = normalizedDate;
  p.referralDate = normalizedReferral;

  // validation – no toast, just inline messages
  if (!p.date || !p.diagnosis || !p.procedure || !p.referralDate || !p.quantity || p.quantity! <= 0) {
    return;
  }

  try {
    await api.put(`/v1/patient-points/${p.id}`, {
      date: toApiDate(p.date),
      diagnosis_code: p.diagnosis.code,
      diagnosis_id: p.diagnosis.id,
      procedure_code: p.procedure.code,
      procedure_id: p.procedure.id,
      reference_date: toApiDate(p.referralDate),
      quantity: p.quantity,
    });

    const idx = records.value.findIndex((r) => r.id === p.id);
    if (idx !== -1) {
      // make sure we store the normalized dates
      records.value[idx] = { ...p };
    }

    toast.add({
      severity: 'success',
      summary: 'Uložené',
      detail: 'Záznam bol upravený.',
      life: 3000,
    });

    pointDialog.value = false;
  } catch (error: any) {
    console.error('Failed to update point', error);

    const msg =
      error?.response?.data?.errors
        ? (Object.values(error.response.data.errors).flat() as string[])[0]
        : error?.response?.data?.message ?? 'Záznam sa nepodarilo upraviť.';

    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: msg,
      life: 5000,
    });
  }
}



/* -------------------------------------------------------------------------- */
/*  Delete selected                                                           */
/* -------------------------------------------------------------------------- */

function confirmDeleteSelected() {
  if (!selectedRecords.value || !selectedRecords.value.length) return;
  deleteRecordsDialog.value = true;
}

async function deleteSelected() {
  if (!selectedRecords.value || !selectedRecords.value.length) return;

  const idsToDelete = selectedRecords.value.map((r) => r.id);

  try {
    await Promise.all(
      idsToDelete.map((id) => api.delete(`/v1/patient-points/${id}`)),
    );

    const deleteSet = new Set(idsToDelete);
    records.value = records.value.filter((r) => !deleteSet.has(r.id));
    selectedRecords.value = [];
    deleteRecordsDialog.value = false;

    toast.add({
      severity: 'success',
      summary: 'Vymazané',
      detail: 'Vybrané záznamy boli vymazané.',
      life: 3000,
    });
  } catch (error: any) {
    console.error('Failed to delete patient points', error);

    const msg =
      error?.response?.data?.message ??
      'Niektoré záznamy sa nepodarilo vymazať.';

    toast.add({
      severity: 'error',
      summary: 'Chyba pri mazaní',
      detail: msg,
      life: 5000,
    });
  }
}

/* -------------------------------------------------------------------------- */
/*  Duplicate selected                                                        */
/* -------------------------------------------------------------------------- */

async function duplicateSelected() {
  if (!selectedRecords.value || !selectedRecords.value.length) return;

  if (!currentPatient.value) {
    toast.add({
      severity: 'warn',
      summary: 'Chýbajúci pacient',
      detail: 'Najprv vyberte pacienta.',
      life: 4000,
    });
    return;
  }

  const today = new Date();
  const createdRows: RecordEntry[] = [];

  try {
    for (const original of selectedRecords.value) {
      const payload = buildPayloadFromRow(original, today);
      const { data } = await api.post('/v1/patient-points', payload);

      const cloned: RecordEntry = {
        id: data.id,
        date: today,
        referralDate: today,
        diagnosis: original.diagnosis,
        procedure: original.procedure,
        quantity: original.quantity,
      };

      createdRows.push(cloned);
    }

    records.value.push(...createdRows);


    toast.add({
      severity: 'success',
      summary: 'Duplikované',
      detail:
        'Vybrané záznamy boli duplikované s dnešným dátumom a uložené do databázy.',
      life: 3000,
    });
  } catch (error: any) {
    console.error('Failed to duplicate patient points', error);

    const msg =
      error?.response?.data?.errors
        ? (Object.values(error.response.data.errors).flat() as string[])[0]
        : error?.response?.data?.message ??
          'Niektoré záznamy sa nepodarilo duplikovať.';

    toast.add({
      severity: 'error',
      summary: 'Chyba pri duplikovaní',
      detail: msg,
      life: 6000,
    });
  }
}

/* -------------------------------------------------------------------------- */
/*  Lifecycle                                                                 */
/* -------------------------------------------------------------------------- */

onMounted(() => {
  if (currentPatient.value) {
    initReferenceDateFromPatient();
    loadRecordsForPatient();
  }
});

watch(currentPatient, (newPatient) => {
  if (newPatient) {
    initReferenceDateFromPatient();
    loadRecordsForPatient();
  } else {
    records.value = [];
    referralDate.value = null;
  }
});
</script>


<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-15 gap-4">
          <!-- Dátum -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Dátum</label>
            <DatePicker
              v-model="date"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              :manualInput="false"
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="submitted && !date" class="text-warning">
              Dátum je povinný.
            </small>
          </div>

          <!-- Diagnóza -->
          <div class="col-span-12 md:col-span-4">
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
                  <span>
                    {{ slotProps.option.code }} – {{ slotProps.option.description }}
                  </span>
                </div>
              </template>
            </AutoComplete>
            <small v-if="submitted && !diagnosis" class="text-warning">
              Diagnóza je povinná.
            </small>
          </div>

          <!-- Výkon -->
          <div class="col-span-12 md:col-span-4">
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
                  <span>
                    {{ slotProps.option.code }} – {{ slotProps.option.description }}
                  </span>
                </div>
              </template>
            </AutoComplete>
            <small v-if="submitted && !procedure" class="text-warning">
              Výkon je povinný.
            </small>
          </div>

          <div class="col-span-12 md:col-span-1">
            <label class="block text-normal mb-1">Počet</label>
            <InputNumber
              v-model.number="quantity"
              class="w-full"
              :min="0" 
              :max="100"
              
              inputClass="!w-full !border-none !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="submitted && !quantity" class="text-warning">
              Počet je povinný.
            </small>
          </div>


          <!-- Dátum odporučenia -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Dátum odporučenia</label>
            <DatePicker
              v-model="referralDate"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              :manualInput="false"
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
              class="!h-7 !bg-accent !border-accent !text-white hover:!bg-darkgrey hover:!border-darkgrey"
            />

            <Button
              icon="bi bi-eraser"
              @click="confirmDeleteSelected"
              :disabled="!selectedRecords || !selectedRecords.length"
              class="!h-7 !bg-warning !border-warning !text-white"
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
        sortMode="single"
        :sortField="'date'"
        :sortOrder="-1"
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
              {{ slotProps.data.diagnosis.code }}
            </span>
          </template>
        </Column>

        <Column field="procedure" header="Výkon" sortable>
          <template #body="slotProps">
            <span v-if="slotProps.data.procedure">
              {{ slotProps.data.procedure.code }}
            </span>
          </template>
        </Column>

        <Column field="quantity" header="Počet" sortable>
          <template #body="slotProps">
            {{ slotProps.data.quantity }}
          </template>
        </Column>

        <Column field="referralDate" header="Dátum odporučenia" sortable>
          <template #body="slotProps">
            {{ formatDate(slotProps.data.referralDate) }}
          </template>
        </Column>

        <Column headerStyle="width: 3rem" :exportable="false">
          <template #body="slotProps">
            <Button
              icon="bi bi-pencil"
              text
              rounded
              @click="editRecord(slotProps.data)"
              class="text-darkgrey! hover:bg-transparent! p-0! !h-min"
            />
          </template>
        </Column>
      </DataTable>

      <div class="text-mini text-accent flex justify-end w-full py-2">
        {{ recordsInfo }}
      </div>

      <!-- Delete dialog -->
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

      <!-- Edit dialog -->
      <Dialog
        v-model:visible="pointDialog"
        :style="{ width: '600px' }"
        header="Upraviť záznam"
        :modal="true"
      >
        <div class="flex flex-col gap-6" v-if="editPoint">
          <div class="col-span-12">
            <label class="block text-normal mb-1">Dátum</label>
            <DatePicker
              v-model="editPoint.date"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="editSubmitted && !editPoint.date" class="text-warning">
              Dátum je povinný.
            </small>
          </div>

          <div class="col-span-12">
            <label class="block text-normal mb-1">Diagnóza</label>
            <AutoComplete
              v-model="editPoint.diagnosis"
              :suggestions="filteredDiagnoses"
              optionLabel="code"
              :minLength="1"
              @complete="searchDiagnoses"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            >
              <template #option="slotProps">
                <span>
                  {{ slotProps.option.code }} – {{ slotProps.option.description }}
                </span>
              </template>
            </AutoComplete>
            <small v-if="editSubmitted && !editPoint.diagnosis" class="text-warning">
              Diagnóza je povinná.
            </small>
          </div>

          <div class="col-span-12">
            <label class="block text-normal mb-1">Výkon</label>
            <AutoComplete
              v-model="editPoint.procedure"
              :suggestions="filteredProcedures"
              optionLabel="code"
              :minLength="1"
              @complete="searchProcedures"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            >
              <template #option="slotProps">
                <span>
                  {{ slotProps.option.code }} – {{ slotProps.option.description }}
                </span>
              </template>
            </AutoComplete>
            <small v-if="editSubmitted && !editPoint.procedure" class="text-warning">
              Výkon je povinný.
            </small>
          </div>

          <div class="col-span-12">
            <label class="block text-normal mb-1">Počet</label>
            <InputNumber
              :modelValue="editPoint.quantity"
              @update:modelValue="editPoint.quantity = $event ? Number($event) : null"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small v-if="editSubmitted && (!editPoint.quantity || editPoint.quantity <= 0)" class="text-warning">
              Počet je povinný.
            </small>
          </div>

          <div class="col-span-12">
            <label class="block text-normal mb-1">Dátum odporučenia</label>
            <datePicker
              v-model="editPoint.referralDate"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full !shadow-none !bg-white focus:!ring-0 focus:!shadow-none"
            />
            <small
              v-if="editSubmitted && !editPoint.referralDate"
              class="text-warning"
            >
              Dátum odporučenia je povinný.
            </small>
          </div>

        </div>

        <template #footer>
          <Button
            label="Uložiť"
            class="!bg-accent !border-0 !px-md !text-white hover:!bg-darkgrey"
            @click="savePoint"
          />
        </template>
      </Dialog>
    </div>
  </div>
</template>
