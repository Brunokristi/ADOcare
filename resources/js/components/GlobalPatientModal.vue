<!-- src/components/ModalProvider.vue (add this whole file or merge into yours) -->
<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useUiModalsStore } from '@/stores/uiModals'

// PrimeVue components assumed globally registered in your app
import AutoComplete from 'primevue/autocomplete'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const toast = useToast()
const authStore = useAuthStore()
const uiModals = useUiModalsStore()

const branchId = computed(() => authStore.currentBranch?.id ?? null)

const visible = computed({
  get: () => uiModals.patientEditVisible,
  set: (v) => {
    if (!v) uiModals.closePatientEdit()
  },
})

const patientId = computed(() => uiModals.patientEditId)

// -------------------- FORM STATE --------------------
const submitted = ref(false)
const saving = ref(false)
const loading = ref(false)

const patient = ref({
  id: null as number | null,
  firstName: '',
  lastName: '',
  title: '',
  birthNumber: '',
  gender: null as 'M' | 'F' | null,
  contact: '',
  doctorId: null as number | null,
  insuranceCompanyId: null as number | null,
  street: '',
  city: '',
  zip: '',
  latitude: null as number | null,
  longitude: null as number | null,
})

const errors = ref<Record<string, string>>({})

const genderOptions = [
  { label: 'Muž', value: 'M' },
  { label: 'Žena', value: 'F' },
]

const doctorOptions = ref<{ id: number; name: string }[]>([])
const insuranceOptions = ref<{ id: number; name: string; code: string }[]>([])

// -------------------- VALIDATION --------------------
function sanitizeZip(value: any) {
  return String(value ?? '').replace(/\D/g, '').slice(0, 5)
}

function validateForm() {
  const e: Record<string, string> = {}

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

  if (!patient.value.street?.trim()) e.street = 'Ulica je povinná.'
  if (!patient.value.city?.trim()) e.city = 'Mesto je povinné.'

  const zip = sanitizeZip(patient.value.zip)
  if (!zip) e.zip = 'PSČ je povinné.'
  else if (!/^\d{5}$/.test(zip)) e.zip = 'PSČ musí mať presne 5 číslic.'
  patient.value.zip = zip

  if (patient.value.latitude == null || patient.value.longitude == null) {
    e.coordinates = 'Vyberte adresu zo zoznamu, aby sa uložila poloha.'
  }

  errors.value = e
  return Object.keys(e).length === 0
}

// -------------------- OPTIONS LOADERS --------------------
async function loadDoctorsOptions() {
  if (!branchId.value) return
  try {
    const res = await api.get('/v1/doctors', {
      params: { branch_id: branchId.value, favourites: 1, paginate: 0, limit: 500 },
    })
    const items = res.data?.data?.items ?? res.data?.data ?? []
    doctorOptions.value = (items ?? []).map((d: any) => ({
      id: d.id,
      name: `${d.title ? d.title + ' ' : ''}${d.first_name ?? ''} ${d.last_name ?? ''}`.trim(),
    }))
  } catch (e) {
    console.error(e)
    doctorOptions.value = []
  }
}

async function loadInsuranceOptions() {
  try {
    const res = await api.get('/v1/insurance-companies', { params: { paginate: 0, limit: 1000 } })
    const payload = res.data?.data ?? res.data
    const items = payload?.items ?? payload?.data ?? (Array.isArray(payload) ? payload : [])
    insuranceOptions.value = (items ?? []).map((c: any) => ({
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
const map = ref<any>(null)
const marker = ref<any>(null)

function destroyMap() {
  if (map.value) {
    map.value.remove()
    map.value = null
    marker.value = null
  }
}

function initMap() {
  const el = document.getElementById('patient-map-global')
  if (!el) return

  destroyMap()

  map.value = L.map(el).setView([48.1486, 17.1077], 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map.value)
}

function setMarker(lat: any, lng: any) {
  if (!map.value || lat == null || lng == null) return
  const latLng: [number, number] = [lat, lng]
  if (marker.value) marker.value.remove()
  marker.value = L.marker(latLng).addTo(map.value)
  map.value.setView(latLng, 16)
}

// -------------------- ADDRESS AUTOCOMPLETE (select-only) --------------------
const addressQuery = ref('')
const addressSuggestions = ref<any[]>([])

function syncAddressQueryFromPatient() {
  const s = patient.value.street || ''
  const c = patient.value.city || ''
  const z = patient.value.zip || ''
  addressQuery.value = [s, c, z].filter(Boolean).join(', ')
}

function onAddressClear() {
  patient.value.street = ''
  patient.value.latitude = null
  patient.value.longitude = null
}

watch(addressQuery, (val) => {
  if (!visible.value) return
  if (!val || String(val).trim() === '') onAddressClear()
})

function onAddressBlur() {
  syncAddressQueryFromPatient()
}

async function searchAddress(e: any) {
  try {
    const q = (e?.query ?? '').trim()
    if (!q) {
      addressSuggestions.value = []
      return
    }

    const res = await api.get('/v1/geocode/autocomplete', { params: { text: q } })
    const features = res.data?.features ?? []

    addressSuggestions.value = features.map((f: any) => {
      const p = f.properties ?? {}
      const coords = f.geometry?.coordinates ?? []

      const street = [p.street, p.housenumber].filter(Boolean).join(' ').trim() || p.name || ''
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

function onAddressSelect(event: any) {
  const sel = event?.value
  if (!sel) return

  patient.value.street = sel.street || ''
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

// -------------------- API ↔ UI --------------------
function setFormFromApi(p: any) {
  patient.value = {
    id: p.id ?? null,
    firstName: p.first_name ?? '',
    lastName: p.last_name ?? '',
    title: p.title ?? '',
    birthNumber: p.personal_number ?? '',
    gender: p.sex ?? null,
    contact: p.contact ?? '',
    doctorId: p.doctor_id ?? null,
    insuranceCompanyId: p.insurance_company_id ?? null,
    street: p.address ?? '',
    city: p.city ?? '',
    zip: p.zip ?? '',
    latitude: p.latitude ?? null,
    longitude: p.longitude ?? null,
  }
  syncAddressQueryFromPatient()
  errors.value = {}
}

function uiToApiPayload(ui: any) {
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

async function loadPatientById(id: number) {
  loading.value = true
  try {
    const res = await api.get(`/v1/patients/${id}`)
    const apiPatient = res.data?.data ?? res.data
    setFormFromApi(apiPatient)

    await nextTick()
    setTimeout(() => {
      initMap()
      setMarker(patient.value.latitude, patient.value.longitude)
    }, 50)
  } catch (e) {
    console.error(e)
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Pacienta sa nepodarilo načítať.', life: 4000 })
    uiModals.closePatientEdit()
  } finally {
    loading.value = false
  }
}

async function savePatient() {
  submitted.value = true
  if (!validateForm()) return
  if (!patient.value.id) return

  saving.value = true
  try {
    await api.patch(`/v1/patients/${patient.value.id}`, uiToApiPayload(patient.value))

    toast.add({
      severity: 'success',
      summary: 'Pacient upravený',
      detail: 'Zmeny boli úspešne uložené.',
      life: 2500,
    })

    uiModals.closePatientEdit()
  } catch (e: any) {
    console.error(e)
    toast.add({
      severity: 'error',
      summary: 'Chyba pri ukladaní',
      detail: 'Pacienta sa nepodarilo uložiť. Skúste to znova.',
      life: 4000,
    })
  } finally {
    saving.value = false
  }
}

// -------------------- OPEN/CLOSE LIFECYCLE --------------------
watch(
  () => uiModals.patientEditVisible,
  async (v) => {
    if (v) {
      // load dropdowns when opening
      await Promise.all([loadDoctorsOptions(), loadInsuranceOptions()])

      // load patient
      if (patientId.value) await loadPatientById(patientId.value)
    } else {
      destroyMap()
      submitted.value = false
      errors.value = {}
      addressSuggestions.value = []
    }
  }
)
</script>

<template>
  <Dialog v-model:visible="visible" :style="{ width: '90%' }" header="Pacient" :modal="true">
    <div v-if="loading" class="p-4">Načítavam...</div>

    <div v-else class="flex flex-col gap-6">
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
          />
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
          <div id="patient-map-global" class="w-full h-full rounded-md overflow-hidden"></div>
        </div>
      </div>
    </div>

    <template #footer>
      <Button
        label="Uložiť"
        class="!bg-accent !px-md !text-white hover:!bg-darkgrey !border-0"
        :loading="saving"
        @click="savePatient"
      />
    </template>
  </Dialog>
</template>
