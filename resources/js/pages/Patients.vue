<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { usePatientStore } from '@/stores/patientStore';
import { useAuthStore } from '@/stores/auth';
import SecondaryNavbar from '@/components/SecondaryNavbar.vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const router = useRouter();
const ORS_API_KEY = import.meta.env.VITE_ORS_API_KEY;
const toast = useToast();
const patientStore = usePatientStore();
const authStore = useAuthStore();

const branchId = computed(() => authStore.currentBranch?.id ?? null);


const dt = ref(null);
const showRows = ref([]);
const submitted = ref(false);

const products = ref([]); // table rows (UI shape)

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const productDialog = ref(false);
const deleteProductsDialog = ref(false);

// UI form model (keep your current layout bindings)
const product = ref({
  id: null,
  firstName: '',
  lastName: '',
  title: '',
  birthNumber: '',
  gender: null,
  contact: '',
  doctorId: null,
  insuranceCode: null,
  street: '',
  city: '',
  zip: '',
});

// options (keep your current ones)
const genderOptions = [
  { label: 'Muž', value: 'M' },
  { label: 'Žena', value: 'F' }
];

const doctorOptions = ref([]);
const insuranceOptions = ref([]);

async function loadDoctorsOptions() {
  const res = await api.get('/v1/doctors');

  const items = res.data?.data?.items ?? [];

  doctorOptions.value = items.map(d => ({
    id: d.id,
    name: `${d.title ? d.title + ' ' : ''}${d.first_name} ${d.last_name}`.trim(),
  }));
}

async function loadInsuranceOptions() {
  const res = await api.get('/v1/insurance-companies'); // no paginate for dropdown
  const items = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);

  insuranceOptions.value = items.map(c => ({
    id: c.id,
    code: String(c.code),
    name: c.name,
  }));
}


// -------------------- MAP --------------------
const map = ref(null);
const routeLayer = ref(null);

function destroyMap() {
  if (map.value) {
    map.value.remove();
    map.value = null;
    routeLayer.value = null;
  }
}

function initMap() {
  const el = document.getElementById('patient-map');
  if (!el) return;

  // prevent double-init when reopening dialog
  destroyMap();

  map.value = L.map(el).setView([48.1486, 17.1077], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
  }).addTo(map.value);
}

async function loadRoute() {
  if (!ORS_API_KEY || !map.value) return;

  const body = {
    coordinates: [
      [17.1077, 48.1486],
      [17.12, 48.16],
    ],
  };

  const res = await fetch('https://api.openrouteservice.org/v2/directions/driving-car', {
    method: 'POST',
    headers: {
      Authorization: ORS_API_KEY,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  });

  const data = await res.json();

  if (routeLayer.value) routeLayer.value.remove();

  routeLayer.value = L.geoJSON(data, {
    style: { color: '#5C9EAD', weight: 4 },
  }).addTo(map.value);

  const bounds = routeLayer.value.getBounds();
  if (bounds.isValid()) {
    map.value.fitBounds(bounds, { padding: [20, 20] });
  }
}

// -------------------- API ↔ UI mapping helpers --------------------
function apiToUi(p) {
  const doctorName =
    p.doctor
      ? `${p.doctor.title ? p.doctor.title + ' ' : ''}${p.doctor.first_name ?? ''} ${p.doctor.last_name ?? ''}`.trim()
      : '';

  return {
    id: p.id,
    firstname: p.first_name ?? '',
    lastname: p.last_name ?? '',
    personalnumber: p.personal_number ?? '',
    address: p.address ?? '',
    city: p.city ?? '',
    doctorId: p.doctor_id ?? null,
    doctor: doctorName || (p.doctor_id ? String(p.doctor_id) : ''),

    // keep full original api record for editing (non-visual)
    _api: p,
  };
}

function uiToApiPayload(ui) {
  // insurance in your UI is "code", but backend expects insurance_company_id (integer).
  // If your insuranceOptions are only codes, this will send null by default to avoid validation errors.
  // Replace this mapping once you have real insurance companies with ids in frontend.
  const insurance_company_id = null;

  return {
    branch_id: branchId.value,

    first_name: ui.firstName,
    last_name: ui.lastName,
    title: ui.title || null,
    personal_number: ui.birthNumber || null,
    sex: ui.gender || null,
    contact: ui.contact || null,

    doctor_id: ui.doctorId || null,
    insurance_company_id,

    address: ui.street || null,
    city: ui.city || null,
    zip: ui.zip || null,

    latitude: null,
    longitude: null,

    reference_date: null,
  };
}

function setFormFromApi(apiPatient) {
  product.value = {
    id: apiPatient.id ?? null,
    firstName: apiPatient.first_name ?? '',
    lastName: apiPatient.last_name ?? '',
    title: apiPatient.title ?? '',
    birthNumber: apiPatient.personal_number ?? '',
    gender: apiPatient.sex ?? null,
    contact: apiPatient.contact ?? '',
    doctorId: apiPatient.doctor_id ?? null,
    insuranceCode: apiPatient.insurance_company?.code ?? null,
    street: apiPatient.address ?? '',
    city: apiPatient.city ?? '',
    zip: apiPatient.zip ?? '',
  };
}

// -------------------- LOAD LIST --------------------
async function loadPatients() {
  if (!branchId.value) return;

  const res = await api.get('/v1/patients', {
    params: { branch_id: branchId.value },
  });

  console.log('LOAD PATIENTS RESPONSE:', res.data);

  const items = res.data?.data?.items ?? [];
  products.value = items.map(apiToUi)
}




onMounted(loadPatients);

// -------------------- CRUD --------------------
const openNew = () => {
  product.value = {
    id: null,
    firstName: '',
    lastName: '',
    title: '',
    birthNumber: '',
    gender: null,
    contact: '',
    doctorId: null,
    insuranceCode: null,
    street: '',
    city: '',
    zip: '',
  };

  submitted.value = false;
  productDialog.value = true;

  setTimeout(() => {
    initMap();
    loadRoute();
  }, 50);
};

const editProduct = (row) => {
  // row is UI shape; prefer using stored API record if present
  const apiPatient = row._api;
  if (apiPatient) setFormFromApi(apiPatient);
  else {
    product.value = {
      id: row.id ?? null,
      firstName: row.firstname ?? '',
      lastName: row.lastname ?? '',
      title: '',
      birthNumber: row.personalnumber ?? '',
      gender: null,
      contact: '',
      doctorId: row.doctorId ?? null,
      insuranceCode: null,
      street: row.address ?? '',
      city: row.city ?? '',
      zip: '',
    };
  }

  submitted.value = false;
  productDialog.value = true;

  setTimeout(() => {
    initMap();
    loadRoute();
  }, 50);
};

const saveProduct = async () => {
  submitted.value = true;

  if (!product.value.firstName || !product.value.lastName) return;

  const payload = uiToApiPayload(product.value);

  try {
    if (product.value.id) {
      const res = await api.patch(`/v1/patients/${product.value.id}`, payload);
      const updated = res.data?.data ?? res.data;

      const uiRow = apiToUi(updated);
      const index = products.value.findIndex((p) => p.id === uiRow.id);
      if (index !== -1) products.value[index] = uiRow;

      toast.add({ severity: 'success', summary: 'OK', detail: 'Updated', life: 2000 });
    } else {
      const res = await api.post('/v1/patients', payload);
      const created = res.data?.data ?? res.data;

      products.value.unshift(apiToUi(created));
      toast.add({ severity: 'success', summary: 'OK', detail: 'Created', life: 2000 });
    }

    productDialog.value = false;
  } catch (e) {
    const status = e?.response?.status;
    if (status === 422) {
      toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please fill required fields', life: 3000 });
    } else {
      toast.add({ severity: 'error', summary: 'Error', detail: 'Save failed', life: 3000 });
    }
    console.error(e);
  }
};

const confirmDeleteSelected = () => {
  deleteProductsDialog.value = true;
};

const deleteshowRows = async () => {
  try {
    const ids = showRows.value.map((r) => r.id);
    await Promise.all(ids.map((id) => api.delete(`/v1/patients/${id}`)));

    products.value = products.value.filter((p) => !ids.includes(p.id));
    deleteProductsDialog.value = false;
    showRows.value = [];

    toast.add({ severity: 'success', summary: 'OK', detail: 'Deleted', life: 2000 });
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Error', detail: 'Delete failed', life: 3000 });
  }
};

// -------------------- PATIENT PIN --------------------
const selectPatient = (row) => {
  // store the API patient if possible (so other pages can use correct field names)
  patientStore.setPatient(row._api ?? row);
  router.push(`patient/points`);
};

// -------------------- INFO LINE --------------------
const recordsInfo = computed(() => {
  if (!dt.value) return '';
  const total = products.value.length;
  const filtered = dt.value.processedData?.length;
  return `${filtered ?? total} z ${total} záznamov`;
});

function formatBirthNumber(value) {
  const digits = value.replace(/\D/g, '');
  const first = digits.slice(0, 6);
  const last = digits.slice(6, 10);
  return last.length > 0 ? `${first}/${last}` : first;
}

watch(
  () => branchId.value,
  async (id) => {
    if (!id) return;
    await Promise.all([
      loadPatients(),
      loadDoctorsOptions(),
      loadInsuranceOptions(),
    ]);
  },
  { immediate: true }
);


</script>

<template>
    <SecondaryNavbar />

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
            <Column field="firstname" header="Meno" sortable />
            <Column field="lastname" header="Priezvisko" sortable />
            <Column field="personalnumber" header="Rodné číslo" sortable disabled />
            <Column field="address" header="Adresa" sortable />
            <Column field="city" header="Mesto" sortable/>
            <Column field="doctor" header="Ošetrujúci lekár" sortable/>
            <Column :exportable="false" style="width: 3rem">
                <template #body="slotProps">
                    <Button
                    :icon="patientStore.current?.id === slotProps.data.id ? 'bi bi-pin-fill' : 'bi bi-pin-angle'"
                    @click="selectPatient(slotProps.data)"
                    variant="text"
                    class="text-darkgrey! hover:bg-transparent! p-0!"
                    />
                </template>
            </Column>
            <Column :exportable="false" style="width: 3rem">
                <template #body="slotProps">
                    <Button icon="bi bi-pencil" @click="editProduct(slotProps.data)" variant="text" class="text-darkgrey! hover:bg-transparent! p-0!" />
                </template>
            </Column>
            
        </DataTable>

        <div class="text-mini text-accent flex justify-end w-full py-2">
            {{ recordsInfo }}
        </div>

        <Dialog v-model:visible="productDialog" :style="{ width: '90%' }" header="Pacient" :modal="true">
        <div class="flex flex-col gap-6">

            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <label class="block text-normal text-accent">Osobné údaje</label>
                </div>
                <div class="col-span-4">
                    <label class="block text-normal mb-1">Meno</label>
                    <InputText
                    v-model.trim="product.firstName"
                    fluid
                    :invalid="submitted && !product.firstName"
                    />
                    <small v-if="submitted && !product.firstName" class="text-warning">
                    Meno je povinné.
                    </small>
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Priezvisko</label>
                    <InputText
                    v-model.trim="product.lastName"
                    fluid
                    :invalid="submitted && !product.lastName"
                    />
                    <small v-if="submitted && !product.lastName" class="text-warning">
                    Priezvisko je povinné.
                    </small>
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Titul</label>
                    <InputText v-model.trim="product.title" fluid />
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Rodné číslo</label>
                    <InputText 
                        v-model.trim="product.birthNumber"
                        @input="product.birthNumber = formatBirthNumber($event.target.value)"
                        maxlength="11"
                        fluid 
                    />
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Pohlavie</label>
                    <Select
                    v-model="product.gender"
                    :options="genderOptions"
                    optionLabel="label"
                    optionValue="value"
                    fluid
                    />
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Kontakt</label>
                    <InputText v-model.trim="product.contact" fluid />
                </div>
            </div>





            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <label class="block text-normal text-accent">Zdravotné detaily</label>
                </div>
                <div class="col-span-6">
                    <label class="block text-normal mb-1">Lekár</label>
                    <Select
                    v-model="product.doctorId"
                    :options="doctorOptions"
                    optionLabel="name"
                    optionValue="id"
                    fluid
                    filter
                    >
                    <template #footer>
                        <div class="p-2">
                            <Button label="Pridať" fluid variant="text" size="small" icon="bi bi-plus" class="!text-accent !bg-tag3 hover:!bg-accent hover:!text-white" />
                        </div>
                    </template>
                </Select>
                    
                </div>

                <div class="col-span-6">
                    <label class="block text-normal mb-1">Poisťovňa</label>
                    <Select
                    v-model="product.insuranceCode"
                    :options="insuranceOptions"
                    optionLabel="name"
                    optionValue="code"
                    fluid
                    />
                </div>
            </div>





            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <label class="block text-normal text-accent">Adresa</label>
                </div>
                <div class="col-span-4 flex flex-col gap-4">
                    <div>
                    <label class="block text-normal mb-1">Ulica</label>
                    <InputText v-model.trim="product.street" fluid />
                    </div>

                    <div>
                    <label class="block text-normal mb-1">Mesto</label>
                    <InputText v-model.trim="product.city" fluid />
                    </div>

                    <div>
                    <label class="block text-normal mb-1">PSČ</label>
                    <InputText v-model.trim="product.zip" fluid />
                    </div>
                </div>

                <div class="col-span-8">
                    <div id="patient-map" class="w-full h-full rounded-md overflow-hidden"></div>
                </div>
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