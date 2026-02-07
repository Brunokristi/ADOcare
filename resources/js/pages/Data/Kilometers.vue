<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import type { Patient as PatientModel, InsuranceCompany } from '@/types/models';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
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

const batchNumber = ref<string | null>(null);
const batchType = ref<BatchType | null>(null);
const insurance = ref<Insurance | null>(null);
const dates = ref<Date | null>(null);

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

function onBatchNumberInput(event: Event) {
  const input = event.target as HTMLInputElement;
  batchNumber.value = input.value.replace(/[^0-9]/g, '');
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

  loading.value = true;

  try {
    const res = await api.post('/v1/batches/kilometers/preview', {
      batchNumber: batchNumber.value,
      batchType: { code: batchType.value.code },
      insurance: { id: insurance.value.id },
      period: [periodFrom.toISOString(), periodTo.toISOString()],
      user: { id: authStore.user?.id },
      branch: { id: authStore.currentBranch?.id},
      company: { id: authStore.currentBranch?.company_id},
      patients: selectedPatients.value.map(p => ({ id: p.id })),
    });

    const sheet = res.data?.data?.sheet;

    if (!sheet) {
      console.error('Missing sheet in response:', res.data);
      return;
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
    console.error('Preview or navigation failed', error);
  } finally {
    loading.value = false;
  }
}

watch(branchId, (id) => {
  if (!id) return;
  loadAllPatients();
}, { immediate: true });

onMounted(() => {
  loadInsurances();
});

</script>


<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-12 gap-4">
          <!-- Číslo dávky -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Číslo dávky</label>
            <InputText
              v-model="batchNumber"
              @input="onBatchNumberInput"
              inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
              class="border-none!"
              fluid
            />
            <small
              v-if="submitted && !batchNumber"
              class="text-warning"
            >
              Číslo dávky je povinné.
            </small>
          </div>

          <!-- Typ dávky -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Typ dávky</label>
            <Select
              v-model="batchType"
              :options="batchTypes"
              optionLabel="name"
              fluid
              class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
            />
            <small v-if="submitted && !batchType" class="text-warning">
              Typ dávky je povinný.
            </small>
          </div>

          <!-- Poisťovňa -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Poisťovňa</label>
            <Select
              v-model="insurance"
              :options="insurances"
              optionLabel="name"
              fluid
              class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
            />
            <small v-if="submitted && !insurance" class="text-warning">
              Poisťovňa je povinná.
            </small>
          </div>

          <!-- Obdobie (month) -->
          <div class="col-span-12 md:col-span-3">
            <label class="block text-normal mb-1">Obdobie</label>
            <DatePicker
              v-model="dates"
              view="month"
              dateFormat="MM yy"
              :manualInput="false"
              inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
              fluid
            />

            <small
              v-if="submitted && !dates"
              class="text-warning"
            >
              Obdobie je povinné.
            </small>
          </div>

          <!-- Pacienti pre opravnu dávku -->
          <div
            v-if="isCorrectionBatch"
            class="col-span-12"
          >
            <label class="block text-normal mb-2">
              Vyhľadajte pacienta
            </label>

            <AutoComplete
              v-model="selectedPatients"
              :suggestions="filteredPatients"
              multiple
              optionLabel="name"
              :minLength="1"
              @complete="searchPatients"
              :loading="patientsLoading"
              fluid
              class="w-full"
            >
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
                <div
                  class="
                    inline-flex items-center gap-2
                    bg-darkgrey text-lightgrey
                    px-3 py-1 rounded-md
                    text-xs sm:text-sm
                  "
                >
                  <span class="pr-2 border-r border-lightgrey truncate max-w-32 sm:max-w-40">
                    {{ slotProps.value.name }}
                  </span>
                  <span class="px-1 sm:px-2 whitespace-nowrap">
                    {{ slotProps.value.personalNumber }}
                  </span>
                  <i
                    class="bi bi-x-lg cursor-pointer text-[0.6rem] sm:text-[0.7rem]"
                    @click.stop="removePatient(slotProps.value)"
                  ></i>
                </div>
              </template>
            </AutoComplete>

            <small
              v-if="submitted && isCorrectionBatch && !selectedPatients.length"
              class="text-warning block mt-1"
            >
              Pri opravnej dávke je potrebné vybrať aspoň jedného pacienta.
            </small>
          </div>
        </div>
      </section>

        <div class="flex justify-end">
        <Button
          type="submit"
          class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white w-100"
        >
          Vygenerovať
          <i
            class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent"
          />
        </Button>
      </div>
    </form>

    <!-- Loader Overlay -->
    <div v-if="loading" class="fixed inset-0 flex items-center justify-center bg-tag2/50 z-50">
      <div class="p-8 flex flex-col items-center gap-4 w-90">
        <i class="bi bi-flower2 animate-spin text-2xl text-white"></i>
        <p class="text-white text-normal">Dáta sa generujú, počkajte prosím.</p>
      </div>
    </div>
  </div>
</template>
