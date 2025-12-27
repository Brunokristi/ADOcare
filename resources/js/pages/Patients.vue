<script setup>
import { ref, computed, nextTick, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { usePatientStore } from '@/stores/patientStore'
import { useAuthStore } from '@/stores/auth'
import SecondaryNavbar from '@/components/SecondaryNavbar.vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

import AutoComplete from 'primevue/autocomplete'

import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const router = useRouter()
const toast = useToast()
const patientStore = usePatientStore()
const authStore = useAuthStore()

const branchId = computed(() => authStore.currentBranch?.id ?? null)

const dt = ref(null)
const selectedPatients = ref([])
const submitted = ref(false)

const patients = ref([])
const search = ref('')
const first = ref(0)
const perPage = ref(50)
const totalRecords = ref(0)
const sortField = ref(null)
const sortOrder = ref(null)

const patientDialog = ref(false)
const deletePatientsDialog = ref(false)

const patient = ref({
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
})

const errors = ref({})

const genderOptions = [
  { label: 'Muž', value: 'M' },
  { label: 'Žena', value: 'F' },
]

const doctorOptions = ref([])
const insuranceOptions = ref([])

// -------------------- VALIDATION --------------------
function validateForm() {
  const e = {}

  if (!patient.value.firstName?.trim()) e.firstName = 'Meno je povinné.'
  if (!patient.value.lastName?.trim()) e.lastName = 'Priezvisko je povinné.'

  if (!patient.value.birthNumber?.trim()) {
    e.birthNumber = 'Rodné číslo je povinné.'
  } else if (!/^\d{9,10}$/.test(patient.value.birthNumber)) {
    e.birthNumber = 'Rodné číslo musí mať 9 alebo 10 číslic a obsahovať iba čísla.'
  }

  if (!patient.value.gender) e.gender = 'Pohlavie je povinné.'
  if (!patient.value.doctorId) e.doctorId = 'Lekár je povinný.'
  if (!patient.value.insuranceCompanyId) e.insuranceCompanyId = 'Poisťovňa je povinná.'

  // address fields required (street is set ONLY by suggestion select)
  if (!patient.value.street?.trim()) e.street = 'Ulica je povinná.'
  if (!patient.value.city?.trim()) e.city = 'Mesto je povinné.'
  const zip = sanitizeZip(patient.value.zip)
  if (!zip) {
    e.zip = 'PSČ je povinné.'
  } else if (!/^\d{5}$/.test(zip)) {
    e.zip = 'PSČ musí mať presne 5 číslic.'
  }
  patient.value.zip = zip

  // ✅ Coordinates required ONLY if missing.
  // Existing patients with coords in DB can be saved without re-select.
  if (patient.value.latitude == null || patient.value.longitude == null) {
    e.coordinates = 'Vyberte adresu zo zoznamu, aby sa uložila poloha.'
  }

  errors.value = e
  return Object.keys(e).length === 0
}

// -------------------- Doctors / Insurance --------------------
async function loadDoctorsOptions() {
  if (!branchId.value) return

  const res = await api.get('/v1/doctors', {
    params: { branch_id: branchId.value, favourites: 1, paginate: 0, limit: 500 },
  })

  const items = res.data?.data?.items ?? res.data?.data ?? []
  doctorOptions.value = (items ?? []).map((d) => ({
    id: d.id,
    name: `${d.title ? d.title + ' ' : ''}${d.first_name ?? ''} ${d.last_name ?? ''}`.trim(),
  }))
}

async function loadInsuranceOptions() {
  try {
    const res = await api.get('/v1/insurance-companies')

    const payload = res.data?.data ?? res.data
    const items = payload?.items ?? payload?.data ?? (Array.isArray(payload) ? payload : [])

    insuranceOptions.value = (items ?? []).map((c) => ({
      id: c.id,
      name: c.name ?? c.title ?? String(c.code),
      code: String(c.code ?? ''),
    }))
  } catch (err) {
    console.error('loadInsuranceOptions error:', err)
    insuranceOptions.value = []
    toast.add({
      severity: 'error',
      summary: 'Chyba pri načítaní',
      detail: 'Poisťovne sa nepodarilo načítať.',
      life: 4000,
    })
  }
}

// -------------------- MAP --------------------
const map = ref(null)
const marker = ref(null)

function destroyMap() {
  if (map.value) {
    map.value.remove()
    map.value = null
    marker.value = null
  }
}

function initMap() {
  const el = document.getElementById('patient-map')
  if (!el) return

  destroyMap()

  map.value = L.map(el).setView([48.1486, 17.1077], 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map.value)
}

function setMarker(lat, lng) {
  if (!map.value || lat == null || lng == null) return
  const latLng = [lat, lng]
  if (marker.value) marker.value.remove()
  marker.value = L.marker(latLng).addTo(map.value)
  map.value.setView(latLng, 16)
}

function setMarkerFromPatient() {
  if (!map.value) return
  if (patient.value.latitude == null || patient.value.longitude == null) return
  setMarker(patient.value.latitude, patient.value.longitude)
}

// -------------------- Address Autocomplete (select-only) --------------------
const addressQuery = ref('')
const addressSuggestions = ref([])

// show stored address in input
function syncAddressQueryFromPatient() {
  const s = patient.value.street || ''
  const c = patient.value.city || ''
  const z = patient.value.zip || ''
  addressQuery.value = [s, c, z].filter(Boolean).join(', ')
}

// if user clears input, clear stored address+coords (so validation blocks until selected again)
function onAddressClear() {
  patient.value.street = ''
  patient.value.latitude = null
  patient.value.longitude = null
  // keep city/zip as-is (user might still want them), but they are required anyway
}

watch(addressQuery, (val) => {
  if (!patientDialog.value) return
  if (!val || String(val).trim() === '') {
    onAddressClear()
  }
})

// If user typed but didn't select, revert back to stored address on blur
function onAddressBlur() {
  syncAddressQueryFromPatient()
}

async function searchAddress(e) {
  try {
    const q = (e?.query ?? '').trim()
    if (!q) {
      addressSuggestions.value = []
      return
    }

    const res = await api.get('/v1/geocode/autocomplete', { params: { text: q } })
    const features = res.data?.features ?? []

    addressSuggestions.value = features.map((f) => {
      const p = f.properties ?? {}
      const coords = f.geometry?.coordinates ?? []

      const street =
        [p.street, p.housenumber].filter(Boolean).join(' ').trim() ||
        p.name ||
        ''

      const city = p.locality || p.county || p.region || ''
      const zip = p.postalcode || ''

      return {
        label: p.label || `${street}${city ? ', ' + city : ''}${zip ? ', ' + zip : ''}`,
        street,
        city,
        zip,
        lng: coords[0] ?? null,
        lat: coords[1] ?? null,
      }
    })
  } catch (err) {
    console.error('searchAddress error:', err)
    addressSuggestions.value = []
  }
}

function onAddressSelect(event) {
  const sel = event?.value
  if (!sel) return

  patient.value.street = sel.street || ''
  // city+zip should be filled (auto) but user can still edit them after
  patient.value.city = sel.city || patient.value.city || ''
  patient.value.zip = sel.zip || patient.value.zip || ''

  patient.value.latitude = sel.lat ?? null
  patient.value.longitude = sel.lng ?? null

  addressQuery.value = sel.label || patient.value.street

  delete errors.value.street
  delete errors.value.city
  delete errors.value.zip
  delete errors.value.coordinates

  nextTick(() => setMarker(patient.value.latitude, patient.value.longitude))
}

// -------------------- API ↔ UI mapping helpers --------------------
function apiToUi(p) {
  const doctorName =
    p.doctor
      ? `${p.doctor.title ? p.doctor.title + ' ' : ''}${p.doctor.first_name ?? ''} ${p.doctor.last_name ?? ''}`.trim()
      : ''

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
  }
}

function uiToApiPayload(ui) {
  return {
    branch_id: branchId.value,
    first_name: ui.firstName,
    last_name: ui.lastName,
    title: ui.title || null,
    personal_number: ui.birthNumber || null,
    sex: ui.gender || null,
    contact: ui.contact || null,
    doctor_id: ui.doctorId || null,
    insurance_company_id: ui.insuranceCompanyId || null,

    address: ui.street || null,
    city: ui.city || null,
    zip: ui.zip || null,

    latitude: ui.latitude ?? null,
    longitude: ui.longitude ?? null,

    reference_date: null,
  }
}

function setFormFromApi(apiPatient) {
  patient.value = {
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
  }

  syncAddressQueryFromPatient()
  errors.value = {}
}

// -------------------- LIST (server-side) --------------------
const loading = ref(false)

function uiFieldToApiField(field) {
  const map = {
    firstname: 'first_name',
    lastname: 'last_name',
    personalnumber: 'personal_number',
    address: 'address',
    city: 'city',
    doctor: 'doctor',
  }
  return map[field] ?? field
}

function buildSortParam() {
  if (!sortField.value || !sortOrder.value) return undefined
  const apiField = uiFieldToApiField(sortField.value)
  return sortOrder.value === -1 ? `-${apiField}` : apiField
}

function currentPage() {
  return Math.floor(first.value / perPage.value) + 1
}

async function loadPatients(page = 1) {
  if (!branchId.value) return

  loading.value = true
  try {
    const res = await api.get('/v1/patients', {
      params: {
        branch_id: branchId.value,
        q: search.value?.trim() || undefined,
        page,
        per_page: perPage.value,
        sort: buildSortParam(),
      },
    })

    const payload = res.data?.data ?? {}
    const items = payload.items ?? payload.data ?? []
    const total = payload.total ?? payload.meta?.total ?? (Array.isArray(items) ? items.length : 0)

    patients.value = (items ?? []).map(apiToUi)
    totalRecords.value = Number(total) || 0
  } catch (e) {
    console.error(e)
    toast.add({
      severity: 'error',
      summary: 'Chyba pri načítaní',
      detail: 'Pacientov sa nepodarilo načítať.',
      life: 4000,
    })
  } finally {
    loading.value = false
  }
}

// debounce search
let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    first.value = 0
    loadPatients(1)
  }, 250)
})

const onPage = (e) => {
  first.value = e.first
  loadPatients(currentPage())
}

const onSort = (e) => {
  sortField.value = e.sortField
  sortOrder.value = e.sortOrder
  first.value = 0
  loadPatients(1)
}

// -------------------- CRUD --------------------
function resetForm() {
  patient.value = {
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
  }

  addressQuery.value = ''
  addressSuggestions.value = []
  errors.value = {}
  submitted.value = false
}

const openNew = () => {
  resetForm()
  patientDialog.value = true

  setTimeout(() => {
    initMap()
  }, 50)
}

const editPatient = (row) => {
  const apiPatient = row._api
  if (apiPatient) setFormFromApi(apiPatient)

  submitted.value = false
  patientDialog.value = true

  setTimeout(() => {
    initMap()
    setMarkerFromPatient()
  }, 50)
}

const savePatient = async () => {
  submitted.value = true
  if (!validateForm()) return

  const payload = uiToApiPayload(patient.value)

  try {
    if (patient.value.id) {
      await api.patch(`/v1/patients/${patient.value.id}`, payload)
      await loadPatients(currentPage())

      toast.add({
        severity: 'success',
        summary: 'Pacient upravený',
        detail: 'Zmeny boli úspešne uložené.',
        life: 2500,
      })
    } else {
      await api.post('/v1/patients', payload)
      await loadPatients(1)

      toast.add({
        severity: 'success',
        summary: 'Pacient uložený',
        detail: 'Nový pacient bol úspešne vytvorený.',
        life: 2500,
      })
    }

    patientDialog.value = false
  } catch (e) {
    const status = e?.response?.status
    if (status === 422) {
      toast.add({
        severity: 'warn',
        summary: 'Neplatné údaje',
        detail: 'Skontrolujte, prosím, povinné polia.',
        life: 3500,
      })
    } else {
      toast.add({
        severity: 'error',
        summary: 'Chyba pri ukladaní',
        detail: 'Pacienta sa nepodarilo uložiť. Skúste to znova.',
        life: 4000,
      })
    }
    console.error(e)
  }
}

const confirmDeleteSelected = () => {
  deletePatientsDialog.value = true
}

const deleteSelectedPatients = async () => {
  try {
    const ids = selectedPatients.value.map((r) => r.id)
    await Promise.all(ids.map((id) => api.delete(`/v1/patients/${id}`)))

    deletePatientsDialog.value = false
    selectedPatients.value = []

    if (ids.length >= patients.value.length && first.value >= perPage.value) {
      first.value = first.value - perPage.value
    }

    await loadPatients(currentPage())

    toast.add({
      severity: 'success',
      summary: 'Záznamy vymazané',
      detail: 'Vybrané záznamy boli úspešne odstránené.',
      life: 2500,
    })
  } catch (e) {
    console.error(e)
    toast.add({
      severity: 'error',
      summary: 'Chyba pri mazaní',
      detail: 'Záznamy sa nepodarilo vymazať.',
      life: 4000,
    })
  }
}

// -------------------- PATIENT PIN --------------------
const selectPatient = (row) => {
  patientStore.setPatient(row._api ?? row)
  router.push('patient/points')
}

// -------------------- INFO LINE --------------------
const recordsInfo = computed(() => {
  const total = totalRecords.value
  if (!total) return `0 z 0 záznamov`
  const from = first.value + 1
  const to = Math.min(first.value + perPage.value, total)
  return `${from}-${to} z ${total} záznamov`
})

function sanitizeZip(value) {
  return String(value ?? '').replace(/\D/g, '').slice(0, 5)
}

function goToDoctorsSettings() {
  nextTick(() => router.push('/settings/doctors'))
}

watch(
  () => branchId.value,
  async (id) => {
    if (!id) return
    first.value = 0
    await Promise.all([loadPatients(1), loadDoctorsOptions(), loadInsuranceOptions()])
  },
  { immediate: true }
)

</script>

<template>
  <div class="h-full flex flex-col overflow-hidden">
    <SecondaryNavbar class="flex-none" />
    <div class="flex-1 overflow-hidden">
      <div class="h-full flex flex-col overflow-hidden min-h-0">
        <Toolbar class="!bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between">
          <template #end>
            <div class="flex items-center gap-2">
              <IconField>
                <InputText v-model="search" placeholder="Hľadať..." />
                <InputIcon>
                  <i class="bi bi-search text-darkgrey" />
                </InputIcon>
              </IconField>

              <Button
                icon="bi bi-plus"
                @click="openNew"
                class="!bg-accent !border-accent hover:!bg-darkgrey hover:!border-darkgrey !text-white !h-7"
              />

              <Button
                icon="bi bi-eraser"
                @click="confirmDeleteSelected"
                :disabled="!selectedPatients || !selectedPatients.length"
                class="!bg-warning !border-warning !text-white !h-7"
              />
            </div>
          </template>
        </Toolbar>

        <div class="flex-1 overflow-hidden min-h-0">
          <DataTable
            ref="dt"
            v-model:selection="selectedPatients"
            :value="patients"
            dataKey="id"
            stripedRows
            removableSort
            :loading="loading"
            lazy
            paginator
            :first="first"
            :rows="perPage"
            :totalRecords="totalRecords"
            @page="onPage"
            sortMode="single"
            @sort="onSort"
            scrollable
            scrollHeight="flex"
            tableLayout="fixed"
            class="h-full"
          >
            <Column selectionMode="multiple" style="width: 3rem" :exportable="false" />

            <Column field="firstname" header="Meno" sortable style="width: 12rem" />
            <Column field="lastname" header="Priezvisko" sortable style="width: 14rem" />
            <Column field="personalnumber" header="Rodné číslo" sortable style="width: 10rem" />

            <Column field="address" header="Adresa" sortable style="width: 16rem">
              <template #body="{ data }">
                <div class="truncate max-w-full">{{ data.address }}</div>
              </template>
            </Column>

            <Column field="city" header="Mesto" sortable style="width: 10rem" />
            <Column field="doctor" header="Ošetrujúci lekár" sortable style="width: auto">
              <template #body="{ data }">
                <div class="truncate max-w-full">{{ data.doctor }}</div>
              </template>
            </Column>

            <Column :exportable="false" style="width: 3rem">
              <template #body="{ data }">
                <Button
                  :icon="patientStore.current?.id === data.id ? 'bi bi-pin-fill' : 'bi bi-pin-angle'"
                  @click.stop="selectPatient(data)"
                  variant="text"
                  class="!text-darkgrey hover:!bg-transparent !p-0"
                />
              </template>
            </Column>

            <Column :exportable="false" style="width: 3rem">
              <template #body="{ data }">
                <Button
                  icon="bi bi-pencil"
                  @click.stop="editPatient(data)"
                  variant="text"
                  class="!text-darkgrey hover:!bg-transparent !p-0"
                />
              </template>
            </Column>
          </DataTable>
        </div>

        <div class="flex-none text-mini text-accent flex justify-end w-full py-2">
          {{ recordsInfo }}
        </div>
      </div>

      <Dialog v-model:visible="patientDialog" :style="{ width: '90%' }" header="Pacient" :modal="true">
        <div class="flex flex-col gap-6">
          <!-- Personal -->
          <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
              <label class="block text-normal text-accent">Osobné údaje</label>
            </div>

            <div class="col-span-4">
              <label class="block text-normal mb-1">Meno</label>
              <InputText v-model.trim="patient.firstName" fluid :invalid="submitted && !!errors.firstName" />
              <small v-if="submitted && errors.firstName" class="text-warning">{{ errors.firstName }}</small>
            </div>

            <div class="col-span-4">
              <label class="block text-normal mb-1">Priezvisko</label>
              <InputText v-model.trim="patient.lastName" fluid :invalid="submitted && !!errors.lastName" />
              <small v-if="submitted && errors.lastName" class="text-warning">{{ errors.lastName }}</small>
            </div>

            <div class="col-span-4">
              <label class="block text-normal mb-1">Titul</label>
              <InputText v-model.trim="patient.title" fluid />
            </div>

            <div class="col-span-4">
              <label class="block text-normal mb-1">Rodné číslo</label>
              <InputText
                v-model.trim="patient.birthNumber"
                maxlength="10"
                fluid
                @input="patient.birthNumber = patient.birthNumber.replace(/\\D/g, '')"
                :invalid="submitted && !!errors.birthNumber"
              />
              <small v-if="submitted && errors.birthNumber" class="text-warning">{{ errors.birthNumber }}</small>
            </div>

            <div class="col-span-4">
              <label class="block text-normal mb-1">Pohlavie</label>
              <Select
                v-model="patient.gender"
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
              <InputText v-model.trim="patient.contact" fluid />
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
                v-model="patient.doctorId"
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
                v-model="patient.insuranceCompanyId"
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
                  @blur="onAddressBlur"
                  forceSelection
                  fluid
                  :invalid="submitted && !!errors.street"
                />
                <small v-if="submitted && errors.street" class="text-warning">{{ errors.street }}</small>
              </div>

              <div>
                <label class="block text-normal mb-1">Mesto</label>
                <InputText v-model.trim="patient.city" fluid :invalid="submitted && !!errors.city" />
                <small v-if="submitted && errors.city" class="text-warning">{{ errors.city }}</small>
              </div>

              <div>
                <label class="block text-normal mb-1">PSČ</label>
                <InputText
                  v-model="patient.zip"
                  inputmode="numeric"
                  maxlength="5"
                  fluid
                  :invalid="submitted && !!errors.zip"
                  @input="patient.zip = sanitizeZip(patient.zip)"
                />                
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
            @click="savePatient"
          />
        </template>
      </Dialog>

      <Dialog
        v-model:visible="deletePatientsDialog"
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
              @click="deletePatientsDialog = false"
              class="!bg-accent !px-md !text-white hover:!bg-darkgrey !border-0"
            />
            <Button label="Áno" text @click="deleteSelectedPatients" class="!bg-warning !px-md !text-white" />
          </div>
        </div>
      </Dialog>
    </div>
  </div>
</template>
