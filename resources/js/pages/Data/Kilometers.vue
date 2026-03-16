<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import api from '@/services/api';
import type { Patient as PatientModel, InsuranceCompany } from '@/types/models';
import { useAuthStore } from '@/stores/auth';
import LoadingOverlay from '@/components/LoadingOverlay.vue';
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import type { DataTableOptions } from '@/types/datatable'

const authStore = useAuthStore();
const toast = useToast();
const branchId = computed(() => authStore.currentBranch?.id ?? null);


type BatchType = {
  code: string;
  name: string;
};

type Insurance = {
  id: number;
  code: string | null;
  name: string;
};

type Patient = {
  id: number;
  name: string;
  personalNumber: string;
};

const router = useRouter();

const ROUTES_TOAST_GROUP = 'kilometers-routes-toast'

const batchNumber = ref<string | null>(null);
const batchType = ref<BatchType | null>(null);
const insurance = ref<Insurance | null>(null);
const now = new Date()
const dates = ref<Date | null>(new Date(now.getFullYear(), now.getMonth() - 1, 1));
const allPatients = ref<Patient[]>([]);
const filteredPatients = ref<Patient[]>([]);
const selectedPatients = ref<Patient[]>([]);

const submitted = ref(false);
const loading = ref(false);
const patientsLoading = ref(false);

const batchTypes = ref<BatchType[]>([
  { code: 'N', name: 'Nová dávka' },
  { code: 'O', name: 'Opravná dávka' },
]);

const insurances = ref<Insurance[]>([]);

const isCorrectionBatch = computed(() => batchType.value?.code === 'O');

function mapInsuranceCompanyToOption(company: InsuranceCompany): Insurance {
  const displayName = company.name ?? '';

  return {
    id: company.id,
    code: company.code,
    name: displayName ? `${displayName}` : displayName || `#${company.id}`,
  };
}

function mapPatients(items: PatientModel[]): Patient[] {
  return items.map(p => ({
    id: p.id,
    name: `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim(),
    personalNumber: p.personal_number ?? '',
  }));
}

async function loadInsurances() {
  try {
    const res = await api.get('/v1/insurance-companies', {
      params: { paginate: 0 },
    })

    const payload = res.data?.data

    const items =
      (payload?.items as InsuranceCompany[] | undefined) ??
      (Array.isArray(payload) ? (payload as InsuranceCompany[]) : []) ??
      []

    insurances.value = items.map(mapInsuranceCompanyToOption)
  } catch (e) {
    console.error('Failed to load insurance companies', e)
    insurances.value = []
  }
}


async function loadAllPatients() {
  const id = branchId.value;

  try {
    patientsLoading.value = true;

    const res = await api.get(`/v1/branches/${id}/patients`, {
      params: {
        paginate: 0,
      },
    });

    const data = res.data?.data;
    const items = ((Array.isArray(data) ? data : data?.items) as PatientModel[]) ?? [];

    allPatients.value = mapPatients(items);
  } catch (e) {
    console.error('Failed to load patients', e);
    allPatients.value = [];
  } finally {
    patientsLoading.value = false;
  }
}



function searchPatients(event: { query: string }) {
  const q = (event.query ?? '').toLowerCase().trim();

  if (!q) {
    filteredPatients.value = [];
    return;
  }

  filteredPatients.value = allPatients.value.filter(p =>
    p.name.toLowerCase().includes(q) ||
    p.personalNumber.toLowerCase().includes(q),
  );
}

function removePatient(patient: Patient) {
  selectedPatients.value = selectedPatients.value.filter(
    (p) => p.id !== patient.id,
  );
}

function onBatchNumberKeydown(e: KeyboardEvent) {
    const allowedKeys = [
        'Backspace',
        'Delete',
        'ArrowLeft',
        'ArrowRight',
        'Tab'
    ]

    if (allowedKeys.includes(e.key)) {
        return
    }

    if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault()
    }
}

function showRoutesGeneratedToast() {
    toast.add({
        group: ROUTES_TOAST_GROUP,
        severity: 'success',
        summary: 'Úspech',
        detail: 'Cestovný príkaz a Denný záznam ciest boli úspešne vygenerované.',
        life: 10000,
    })
}

async function onSubmit() {
  submitted.value = true;

  const hasPeriod = !!dates.value;
  const needsPatients = isCorrectionBatch.value;

  if (
    !batchNumber.value ||
    !batchType.value ||
    !insurance.value ||
    !hasPeriod ||
    (needsPatients && !selectedPatients.value.length)
  ) {
    return;
  }

  if (!dates.value) {
    return;
  }

  // Convert selected month into range
  const monthDate = dates.value as Date;
  const year = monthDate.getFullYear();
  const month = monthDate.getMonth();
  const periodFrom = new Date(year, month, 1);
  const periodTo = new Date(year, month + 1, 0);
  const periodFromLocal = formatLocalDate(periodFrom)
  const periodToLocal = formatLocalDate(periodTo)
  const selectedBranchId = authStore.currentBranch?.id

  loading.value = true;

  try {
    const res = await api.post('/v1/batches/kilometers/preview', {
      batchNumber: batchNumber.value,
      batchType: { code: batchType.value.code },
      insurance: { id: insurance.value.id },
      period: [periodFrom.toISOString(), periodTo.toISOString()],
      user: { id: authStore.user?.id },
      branch: { id: authStore.currentBranch?.id },
      company: { id: authStore.currentBranch?.company_id },
      patients: selectedPatients.value.map(p => ({ id: p.id })),
    });

    const sheet = res.data?.data?.sheet;

    if (!sheet) {
      console.error('Missing sheet in response:', res.data);
      return;
    }

    if (selectedBranchId) {
      void Promise.allSettled([
        api.post('/v1/cps', {
          start: periodFromLocal,
          end: periodToLocal,
          branch_id: selectedBranchId,
        }),
        api.post('/v1/dzcs', {
          start: periodFromLocal,
          end: periodToLocal,
          branch_id: selectedBranchId,
        }),
      ]).then(([cpResult, dzcResult]) => {
        if (cpResult.status === 'fulfilled' && dzcResult.status === 'fulfilled') {
          showRoutesGeneratedToast()
          return
        }

        toast.add({
          severity: 'warn',
          summary: 'Čiastočný úspech',
          detail: 'Kilometre boli vytvorené, ale CP alebo DZC sa nepodarilo vygenerovať.',
          life: 4500,
        })
      })
    } else {
      toast.add({
        severity: 'info',
        summary: 'Informácia',
        detail: 'Kilometre boli vytvorené, ale CP/DZC sa negenerovali, pretože chýba pobočka.',
        life: 4000,
      })
    }

    await router.push({
      path: '/documents/kilometers',
      query: {
        batchNumber: sheet.batchNumber,
        fileName: sheet.fileName,
        amount: sheet.amount,
        kilometers: sheet.kilometers,
        periodFrom: sheet.periodFrom,
        periodTo: sheet.periodTo,
        performedBy: sheet.performedBy,
        performedDate: sheet.performedDate,
        companyName: sheet.companyName,
        branchName: sheet.branchName,
        insuranceId: insurance.value.id,
        batchTypeCode: batchType.value.code,
        period0: periodFrom.toISOString(),
        period1: periodTo.toISOString(),
        insuranceName: sheet.insuranceName,
        patientIds: JSON.stringify(sheet.patients ?? []),
      },
    });
  } catch (error) {
    console.error('Generation failed', error);
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa vygenerovať kilometre.',
      life: 4000,
    })
  } finally {
    loading.value = false;
  }
}

const formatLocalDate = (date: Date) => {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

watch(branchId, (id) => {
  if (!id) return;
  loadAllPatients();
}, { immediate: true });

onMounted(() => {
  loadInsurances();
});

type DocRow = {
  id: number
  name: string
  type: string
  subtype?: 'N' | 'O' | string
  period?: string
  created_at?: string
  insurance_company_name?: string
}

const formatSubtype = (code?: string) => {
  if (code === 'N') return 'Nová dávka'
  if (code === 'O') return 'Opravná dávka'
  return code ?? ''
}

const formatDateWithTime = (dateStr?: string) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const datePart = date.toLocaleDateString('sk-SK')
  const timePart = date.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
  return `${datePart} ${timePart}`
}

const openKilometersDoc = (doc: DocRow) => {
  const url = router.resolve(`/documents/kilometers/${doc.id}`).href
  window.open(url, '_blank', 'noopener,noreferrer')
}

const options = computed<DataTableOptions<DocRow>>(() => ({
  rowKey: 'id',
  endpointUrl: 'v1/kilometers-batches',
  extraParams: {
    ...(branchId.value ? { branch_id: branchId.value } : {}),
  },
  dateRangeFilter: {
    mode: 'single',
    param: 'period',
    view: 'month',
    dateFormat: 'mm/yy',
    value: dates.value,
  },
  defaultPageSize: 25,
  pageSizeOptions: [10, 25, 50],
  selectable: true,

  columns: [
    {
      field: 'insurance_company_name',
      header: 'Poisťovňa',
      sortable: true,
      render: (v?: string) => {
        if (!v) return ''
        return v.trim().split(/\s+/)[0] ?? ''
      }
    },
    {
      field: 'subtype',
      header: 'Druh dávky',
      sortable: true,
      render: (v?: string) => formatSubtype(v),
    },
    { field: 'period', header: 'Obdobie', sortable: true },
    {
        field: 'name',
        header: 'Číslo dávky',
        sortable: true,
        render: (v?: string) => {
            if (!v) return ''
            const parts = v.split('_')
            return parts[3] ?? ''
        }
    },
    {
      field: 'updated_at',
      header: 'Naposledy upravené',
      sortable: true,
      render: (v?: string) => formatDateWithTime(v),
    },
    {
      field: 'preview',
      header: '',
      width: '3rem',
      component: ActionButtons,
      componentOptions: [
        {
          icon: 'bi bi-eye',
          color: 'info',
          tooltip: 'Zobraziť',
          action: (row: DocRow) => openKilometersDoc(row),
        },
      ],
    },
  ],

  actions: [
    {
      key: 'delete',
      disabled: ({ selectedRows }: { selectedRows: DocRow[] }) => selectedRows.length === 0,
      icon: 'bi bi-eraser',
      class: 'bg-danger!',
      confirm: 'Naozaj chcete zmazať vybrané dokumenty?',
      handler: async ({ selectedRows, remote }: { selectedRows: DocRow[]; remote: any }) => {
        await api.delete('/v1/documents', { data: { ids: selectedRows.map(r => r.id) } })
        await remote.loadPage(remote.page)
      },
    },
  ],
}))

</script>


<template>
  <LoadingOverlay :show="loading" text="" />
  <div class="flex flex-col gap-6 relative">
    <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-12 gap-4">
          <!-- Číslo dávky -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Číslo dávky</label>
            <InputText
                v-model="batchNumber"
                @keydown="onBatchNumberKeydown"
                maxlength="6"
                inputmode="numeric"
                inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                class="border-none!"
                fluid
            />
            <small v-if="submitted && !batchNumber" class="text-danger">
              Číslo dávky je povinné.
            </small>
          </div>

          <!-- Typ dávky -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Typ dávky</label>
            <Select v-model="batchType" :options="batchTypes" optionLabel="name" fluid
              class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!" />
            <small v-if="submitted && !batchType" class="text-danger">
              Typ dávky je povinný.
            </small>
          </div>

          <!-- Poisťovňa -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Poisťovňa</label>
            <Select v-model="insurance" :options="insurances" optionLabel="name" fluid
              class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!" />
            <small v-if="submitted && !insurance" class="text-danger">
              Poisťovňa je povinná.
            </small>
          </div>

          <!-- Obdobie (month) -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Obdobie</label>
            <DatePicker v-model="dates" view="month" dateFormat="MM yy" :manualInput="false"
              inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!" fluid />

            <small v-if="submitted && !dates" class="text-danger">
              Obdobie je povinné.
            </small>
          </div>

          <!-- Pacienti pre opravnu dávku -->
          <div v-if="isCorrectionBatch" class="col-span-12">
            <label class="block text-normal mb-2">
              Vyhľadajte pacienta
            </label>

            <AutoComplete v-model="selectedPatients" :suggestions="filteredPatients" multiple optionLabel="name"
              :minLength="1" @complete="searchPatients" :loading="patientsLoading" fluid class="w-full">
              <!-- suggestion option -->
              <template #option="slotProps">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-normal text-darkgrey">
                    {{ slotProps.option.name }}
                  </span>
                  <span class="bg-darkgrey rounded-md text-mini text-white px-2 py-0.5">
                    {{ slotProps.option.personalNumber }}
                  </span>
                </div>
              </template>

              <!-- chip template -->
              <template #chip="slotProps">
                <div class="
                    inline-flex items-center gap-2
                    bg-darkgrey text-lightgrey
                    px-3 py-1 rounded-md
                    text-xs sm:text-sm
                  ">
                  <span class="pr-2 border-r border-lightgrey truncate max-w-32 sm:max-w-40">
                    {{ slotProps.value.name }}
                  </span>
                  <span class="px-1 sm:px-2 whitespace-nowrap">
                    {{ slotProps.value.personalNumber }}
                  </span>
                  <i class="bi bi-x-lg cursor-pointer text-[0.6rem] sm:text-[0.7rem]"
                    @click.stop="removePatient(slotProps.value)"></i>
                </div>
              </template>
            </AutoComplete>

            <small v-if="submitted && isCorrectionBatch && !selectedPatients.length" class="text-danger block mt-1">
              Pri opravnej dávke je potrebné vybrať aspoň jedného pacienta.
            </small>
          </div>
        </div>
      </section>

      <div class="flex justify-end">
        <Button type="submit"
          class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white w-100">
          Vygenerovať
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>

    <section>
      <UniversalDataTable :options="options" />
    </section>
  </div>
</template>
