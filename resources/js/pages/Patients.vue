<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { usePatientStore } from '@/stores/patientStore';
import { useAuthStore } from '@/stores/auth';
import SecondaryNavbar from '@/components/SecondaryNavbar.vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';

import AutoComplete from 'primevue/autocomplete';

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const router = useRouter();
const toast = useToast();
const patientStore = usePatientStore();
const authStore = useAuthStore();

const branchId = computed(() => authStore.currentBranch?.id ?? null);

const dt = ref(null);
const showRows = ref([]);
const submitted = ref(false);

const products = ref([]);

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const productDialog = ref(false);
const deleteProductsDialog = ref(false);

const product = ref({
  id: null,
  firstName: '',
  lastName: '',
  title: '', // optional
  birthNumber: '',
  gender: null,
  contact: '', // optional
  doctorId: null,
  insuranceCompanyId: null,

  street: '',
  city: '',
  zip: '',
  latitude: null,
  longitude: null,
});

const errors = ref({});

const genderOptions = [
  { label: 'Muž', value: 'M' },
  { label: 'Žena', value: 'F' },
];

const doctorOptions = ref([]);
const insuranceOptions = ref([]);

// -------------------- VALIDATION --------------------
function validateForm() {
  const e = {};

  if (!product.value.firstName?.trim()) e.firstName = 'Meno je povinné.';
  if (!product.value.lastName?.trim()) e.lastName = 'Priezvisko je povinné.';
  if (!product.value.birthNumber?.trim()) e.birthNumber = 'Rodné číslo je povinné.';
  if (!product.value.gender) e.gender = 'Pohlavie je povinné.';

  if (!product.value.doctorId) e.doctorId = 'Lekár je povinný.';
  if (!product.value.insuranceCompanyId) e.insuranceCompanyId = 'Poisťovňa je povinná.';

  if (!product.value.street?.trim()) e.street = 'Ulica je povinná.';
  if (!product.value.city?.trim()) e.city = 'Mesto je povinné.';
  if (!product.value.zip?.trim()) e.zip = 'PSČ je povinné.';

  if (product.value.latitude == null || product.value.longitude == null) {
    e.coordinates = 'Vyberte adresu zo zoznamu, aby sa uložila poloha.';
  }

  errors.value = e;
  return Object.keys(e).length === 0;
}

// -------------------- Doctors / Insurance --------------------
async function loadDoctorsOptions() {
  if (!branchId.value) return;

  const res = await api.get('/v1/doctors', {
    params: {
      branch_id: branchId.value,
      favourites: 1,
    },
  });

  const items = res.data?.data?.items ?? [];
  doctorOptions.value = items.map((d) => ({
    id: d.id,
    name: `${d.title ? d.title + ' ' : ''}${d.first_name ?? ''} ${d.last_name ?? ''}`.trim(),
  }));
}

async function loadInsuranceOptions() {
  const res = await api.get('/v1/insurance-companies');
  const items = Array.isArray(res.data) ? res.data : res.data?.data ?? [];

  insuranceOptions.value = items.map((c) => ({
    id: c.id,
    name: c.name,
    code: String(c.code),
  }));
}

// -------------------- MAP --------------------
const map = ref(null);
const marker = ref(null);

function destroyMap() {
  if (map.value) {
    map.value.remove();
    map.value = null;
    marker.value = null;
  }
}

function initMap() {
  const el = document.getElementById('patient-map');
  if (!el) return;

  destroyMap();

  map.value = L.map(el).setView([48.1486, 17.1077], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map.value);
}

function setMarker(lat, lng) {
  if (!map.value || lat == null || lng == null) return;

  const latLng = [lat, lng];
  if (marker.value) marker.value.remove();
  marker.value = L.marker(latLng).addTo(map.value);
  map.value.setView(latLng, 16);
}

function setMarkerFromProduct() {
  if (!map.value) return;
  if (product.value.latitude == null || product.value.longitude == null) return;
  setMarker(product.value.latitude, product.value.longitude);
}

// -------------------- Address Autocomplete --------------------
const addressQuery = ref('');
const addressSuggestions = ref([]);

async function searchAddress(e) {
  try {
    const q = (e?.query ?? '').trim();
    if (!q) {
      addressSuggestions.value = [];
      return;
    }

    const res = await api.get('/v1/geocode/autocomplete', {
      params: { text: q },
    });

    const features = res.data?.features ?? [];

    addressSuggestions.value = features.map((f) => {
      const p = f.properties ?? {};
      const coords = f.geometry?.coordinates ?? [];

      const street =
        [p.street, p.housenumber].filter(Boolean).join(' ').trim()
        || p.name
        || '';

      const city = p.locality || p.county || p.region || '';
      const zip = p.postalcode || '';

      return {
        label: p.label || `${street}${city ? ', ' + city : ''}${zip ? ', ' + zip : ''}`,
        street,
        city,
        zip,
        lng: coords[0] ?? null,
        lat: coords[1] ?? null,
      };
    });
  } catch (err) {
    console.error('searchAddress error:', err);
    addressSuggestions.value = [];
  }
}

function onAddressSelect(event) {
  try {
    const sel = event?.value;
    if (!sel) return;

    product.value.street = sel.street || '';
    product.value.city = sel.city || '';
    product.value.zip = sel.zip || '';

    product.value.latitude = sel.lat ?? null;
    product.value.longitude = sel.lng ?? null;

    addressQuery.value = sel.label || product.value.street;

    // clear address-related errors as soon as user selects
    if (errors.value.street) delete errors.value.street;
    if (errors.value.city) delete errors.value.city;
    if (errors.value.zip) delete errors.value.zip;
    if (errors.value.coordinates) delete errors.value.coordinates;

    nextTick(() => setMarker(product.value.latitude, product.value.longitude));
  } catch (err) {
    console.error('onAddressSelect error:', err);
  }
}

function syncAddressQueryFromProduct() {
  const s = product.value.street || '';
  const c = product.value.city || '';
  const z = product.value.zip || '';
  addressQuery.value = [s, c, z].filter(Boolean).join(', ');
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
    _api: p,
  };
}

function uiToApiPayload(ui) {
  return {
    branch_id: branchId.value,
    first_name: ui.firstName,
    last_name: ui.lastName,
    title: ui.title || null, // optional
    personal_number: ui.birthNumber || null,
    sex: ui.gender || null,
    contact: ui.contact || null, // optional
    doctor_id: ui.doctorId || null,
    insurance_company_id: ui.insuranceCompanyId || null,

    address: ui.street || null,
    city: ui.city || null,
    zip: ui.zip || null,

    latitude: ui.latitude ?? null,
    longitude: ui.longitude ?? null,

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
    insuranceCompanyId: apiPatient.insurance_company_id ?? null,

    street: apiPatient.address ?? '',
    city: apiPatient.city ?? '',
    zip: apiPatient.zip ?? '',
    latitude: apiPatient.latitude ?? null,
    longitude: apiPatient.longitude ?? null,
  };

  syncAddressQueryFromProduct();
  errors.value = {};
}

// -------------------- LOAD LIST --------------------
async function loadPatients() {
  if (!branchId.value) return;

  const res = await api.get('/v1/patients', {
    params: { branch_id: branchId.value },
  });

  const items = res.data?.data?.items ?? [];
  products.value = items.map(apiToUi);
}

onMounted(loadPatients);

// -------------------- CRUD --------------------
function resetForm() {
  product.value = {
    id: null,
    firstName: '',
    lastName: '',
    title: '',
    birthNumber: '',
    gender: null,
    contact: '',
    doctorId: null,
    insuranceCompanyId: null,
    street: '',
    city: '',
    zip: '',
    latitude: null,
    longitude: null,
  };

  addressQuery.value = '';
  addressSuggestions.value = [];
  errors.value = {};
  submitted.value = false;
}

const openNew = () => {
  resetForm();
  productDialog.value = true;

  setTimeout(() => {
    initMap();
  }, 50);
};

const editProduct = (row) => {
  const apiPatient = row._api;
  if (apiPatient) setFormFromApi(apiPatient);

  submitted.value = false;
  productDialog.value = true;

  setTimeout(() => {
    initMap();
    setMarkerFromProduct();
  }, 50);
};

const saveProduct = async () => {
  submitted.value = true;
  if (!validateForm()) return;

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
  patientStore.setPatient(row._api ?? row);
  router.push('patient/points');
};

// -------------------- INFO LINE --------------------
const recordsInfo = computed(() => {
  if (!dt.value) return '';
  const total = products.value.length;
  const filtered = dt.value.processedData?.length;
  return `${filtered ?? total} z ${total} záznamov`;
});

function goToDoctorsSettings() {
  nextTick(() => router.push('/settings/doctors'));
}

watch(
  () => branchId.value,
  async (id) => {
    if (!id) return;
    await Promise.all([loadPatients(), loadDoctorsOptions(), loadInsuranceOptions()]);
  },
  { immediate: true }
);
</script>

<template>
  <SecondaryNavbar />

  <div>
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
    >
      <Column selectionMode="multiple" style="width: 3rem" :exportable="false" />
      <Column field="firstname" header="Meno" sortable />
      <Column field="lastname" header="Priezvisko" sortable />
      <Column field="personalnumber" header="Rodné číslo" sortable disabled />
      <Column field="address" header="Adresa" sortable />
      <Column field="city" header="Mesto" sortable />
      <Column field="doctor" header="Ošetrujúci lekár" sortable />

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
  </div>

  <Dialog v-model:visible="productDialog" :style="{ width: '90%' }" header="Pacient" :modal="true">
    <div class="flex flex-col gap-6">
      <!-- Personal -->
      <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12">
          <label class="block text-normal text-accent">Osobné údaje</label>
        </div>

        <div class="col-span-4">
          <label class="block text-normal mb-1">Meno</label>
          <InputText v-model.trim="product.firstName" fluid :invalid="submitted && !!errors.firstName" />
          <small v-if="submitted && errors.firstName" class="text-warning">{{ errors.firstName }}</small>
        </div>

        <div class="col-span-4">
          <label class="block text-normal mb-1">Priezvisko</label>
          <InputText v-model.trim="product.lastName" fluid :invalid="submitted && !!errors.lastName" />
          <small v-if="submitted && errors.lastName" class="text-warning">{{ errors.lastName }}</small>
        </div>

        <div class="col-span-4">
          <label class="block text-normal mb-1">Titul</label>
          <InputText v-model.trim="product.title" fluid />
        </div>

        <div class="col-span-4">
          <label class="block text-normal mb-1">Rodné číslo</label>
          <InputText v-model.trim="product.birthNumber" maxlength="11" fluid :invalid="submitted && !!errors.birthNumber" />
          <small v-if="submitted && errors.birthNumber" class="text-warning">{{ errors.birthNumber }}</small>
        </div>

        <div class="col-span-4">
          <label class="block text-normal mb-1">Pohlavie</label>
          <Select
            v-model="product.gender"
            :options="genderOptions"
            optionLabel="label"
            optionValue="value"
            fluid
            :invalid="submitted && !!errors.gender"
          />
          <small v-if="submitted && errors.gender" class="text-warning">{{ errors.gender }}</small>
        </div>

        <div class="col-span-4">
          <label class="block text-normal mb-1">Kontakt</label>
          <InputText v-model.trim="product.contact" fluid />
        </div>
      </div>

      <!-- Medical -->
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
            :invalid="submitted && !!errors.doctorId"
          >
            <template #footer>
              <div class="p-2">
                <Button
                  label="Pridať"
                  fluid
                  variant="text"
                  size="small"
                  icon="bi bi-plus"
                  class="!text-accent !bg-tag3 hover:!bg-accent hover:!text-white"
                  @click="goToDoctorsSettings"
                />
              </div>
            </template>
          </Select>
          <small v-if="submitted && errors.doctorId" class="text-warning">{{ errors.doctorId }}</small>
        </div>

        <div class="col-span-6">
          <label class="block text-normal mb-1">Poisťovňa</label>
          <Select
            v-model="product.insuranceCompanyId"
            :options="insuranceOptions"
            optionLabel="name"
            optionValue="id"
            fluid
            :invalid="submitted && !!errors.insuranceCompanyId"
          />
          <small v-if="submitted && errors.insuranceCompanyId" class="text-warning">
            {{ errors.insuranceCompanyId }}
          </small>
        </div>
      </div>

      <!-- Address -->
      <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12">
          <label class="block text-normal text-accent">Adresa</label>
        </div>

        <div class="col-span-4 flex flex-col gap-4">
          <div>
            <label class="block text-normal mb-1">Ulica</label>
            <AutoComplete
              v-model="addressQuery"
              :suggestions="addressSuggestions"
              optionLabel="label"
              @complete="searchAddress"
              @item-select="onAddressSelect"
              fluid
              forceSelection=""
              :invalid="submitted && !!errors.street"
            />
            <small v-if="submitted && errors.street" class="text-warning">{{ errors.street }}</small>
          </div>

          <div>
            <label class="block text-normal mb-1">Mesto</label>
            <InputText v-model.trim="product.city" fluid :invalid="submitted && !!errors.city" />
            <small v-if="submitted && errors.city" class="text-warning">{{ errors.city }}</small>
          </div>

          <div>
            <label class="block text-normal mb-1">PSČ</label>
            <InputText v-model.trim="product.zip" fluid :invalid="submitted && !!errors.zip" />
            <small v-if="submitted && errors.zip" class="text-warning">{{ errors.zip }}</small>
          </div>

          <small v-if="submitted && errors.coordinates" class="text-warning">
            {{ errors.coordinates }}
          </small>
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
        <Button label="Áno" text @click="deleteshowRows" class="!bg-warning !px-md !text-white" />
      </div>
    </div>
  </Dialog>
</template>
