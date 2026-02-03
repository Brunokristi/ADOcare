<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import type { Doctor, InsuranceCompany, Patient } from '@/types/models'
import { LMap, LMarker, LTileLayer } from '@vue-leaflet/vue-leaflet'
import type { AutoCompleteCompleteEvent, AutoCompleteOptionSelectEvent } from 'primevue/autocomplete'

const props = defineProps<{
  patient: Patient
  submitted?: boolean
  errors?: { [key: string]: string } | null
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:patient', patient: Patient): void
  (e: 'clear-error', key: string): void
}>()

const submitted = computed(() => !!props.submitted)
const errors = computed(() => props.errors ?? {})

const authStore = useAuthStore()

const localPatient = ref<Patient>({ ...(props.patient ?? ({} as Patient)) })

// What the AutoComplete input displays (street + number only)
const addressQuery = ref('')

// What the AutoComplete input displays for city (city name only)
const cityQuery = ref('')
const citySuggestions = ref<any[]>([])

// Sync local patient + inputs when parent patient changes
watch(
  () => props.patient,
  (p) => {
    const next = { ...(p ?? ({} as Patient)) }
    localPatient.value = next

    // show stored address (street + number)
    addressQuery.value = (next as any).address ?? ''

    // show stored city
    cityQuery.value = next.city ?? ''
  },
  { immediate: true, deep: true }
)

// propagate local changes up to parent (avoid loops)
watch(
  localPatient,
  (val) => {
    try {
      const parentVal = props.patient ?? ({} as Patient)
      if (JSON.stringify(val) !== JSON.stringify(parentVal)) {
        emit('update:patient', { ...(val as Patient) })
      }
    } catch {
      emit('update:patient', { ...(val as Patient) })
    }
  },
  { deep: true }
)

// -------------------- Doctors / Insurance --------------------
const sexOptions = [
  { label: 'Muž', value: 'M' },
  { label: 'Žena', value: 'F' },
]

const doctorOptions = ref<{ id: number; name: string }[]>([])
const insuranceOptions = ref<{ id: number; name: string }[]>([])

async function loadFavouriteDoctors() {
  const branchId = authStore.currentBranch?.id
  if (!branchId) {
    doctorOptions.value = []
    return
  }

  const doctors = await api.fetchEntities<Doctor>(`v1/branches/${branchId}/favourite-doctors`)

  doctorOptions.value = doctors.map((doc) => ({
    id: doc.id,
    name: `${doc.title ?? ''} ${doc.first_name} ${doc.last_name}`.replace(/\s+/g, ' ').trim(),
  }))

  const selectedId = localPatient.value.doctor_id as unknown as number | null | undefined
  if (selectedId && !doctorOptions.value.some((o) => o.id === selectedId)) {
    ;(localPatient.value as any).doctor_id = null
  }
}

async function loadInsuranceCompanies() {
  const insuranceCompanies = await api.fetchEntities<InsuranceCompany>('/v1/insurance-companies', { all: true })
  insuranceOptions.value = insuranceCompanies.map((ic) => ({
    id: ic.id,
    name: ic.name ?? '<Neznáma poisťovňa>',
  }))
}

watch(
  () => authStore.currentBranch?.id,
  async () => {
    await loadFavouriteDoctors()
  },
  { immediate: true }
)

onMounted(async () => {
  await loadFavouriteDoctors()
  await loadInsuranceCompanies()
  initMap()
})

// -------------------- Map --------------------
const center = ref<[number, number]>([48.1486, 17.1077])
const zoom = ref<number>(13)
const mapRef = ref<any>(null)
const lMapLayerOptions = ref<any>({
  attributionControl: false,
  maxZoom: 19,
})

function initMap() {
  const p = props.patient
  if (!p) return

  const lat = p.latitude
  const lng = p.longitude
  if (!lat || !lng) return

  center.value = [lat, lng]
  zoom.value = 15

  setTimeout(() => {
    if (mapRef.value?.leafletObject) {
      // No reverse geocoding. Map click only sets coordinates.
      mapRef.value.leafletObject.on('click', onMapClick)
    }
  }, 100)
}

async function onMapClick(e: any) {
  const lat = e.latlng?.lat ?? e.latlng?._lat
  const lng = e.latlng?.lng ?? e.latlng?._lng
  if (lat == null || lng == null) return

  localPatient.value.latitude = lat
  localPatient.value.longitude = lng

  center.value = [lat, lng]
  zoom.value = 15

  emit('clear-error', 'coordinates')
}

// -------------------- Personal number --------------------
function onPersonalNumberInput(e: Event) {
  const input = e.target as HTMLInputElement
  const digitsOnly = input.value.replace(/\D+/g, '')
  localPatient.value.personal_number = digitsOnly
}

watch(
  () => localPatient.value.personal_number,
  (val) => {
    if (!val) return
    const clean = val.replace(/\D+/g, '')
    if (val !== clean) localPatient.value.personal_number = clean
  }
)

// -------------------- Address Autocomplete (Google) --------------------
const addressSuggestions = ref<any[]>([])

function revertAddressToStored() {
  addressQuery.value = (localPatient.value as any).address ?? ''
}

function normalizeZip(zip: string) {
  return (zip ?? '').replace(/\s+/g, '').trim()
}

function pickComp(components: any[], type: string) {
  const c = (components ?? []).find((x: any) => (x.types ?? []).includes(type))
  return (c?.long_name ?? '').trim()
}

function parseGoogleComponents(components: any[]) {
  const streetNumber = pickComp(components, 'street_number')
  const route = pickComp(components, 'route')

  const locality = pickComp(components, 'locality')
  const postalTown = pickComp(components, 'postal_town')
  const admin2 = pickComp(components, 'administrative_area_level_2')
  const city = (locality || postalTown || admin2 || '').trim()

  const zip = normalizeZip(pickComp(components, 'postal_code'))

  const streetOnly = [route, streetNumber].filter(Boolean).join(' ').trim()
  return { streetOnly, city, zip }
}

async function searchAddress(e: AutoCompleteCompleteEvent) {
  try {
    const q = (e.query ?? '').trim()

    if (q.length < 3) {
    addressSuggestions.value = []
    return
    }


    const res = await api.get('/v1/geocode/autocomplete', { params: { text: q } })
    const preds = res.data?.predictions ?? []

    addressSuggestions.value = preds.map((p: any) => ({
      label: p.description,
      place_id: p.place_id,
    }))
  } catch (err) {
    console.error('searchAddress error:', err)
    addressSuggestions.value = []
  }
}

async function onAddressSelect(event: AutoCompleteOptionSelectEvent) {
  const sel: any = event?.value
  if (!sel?.place_id) return

  try {
    const res = await api.get('/v1/geocode/details', { params: { place_id: sel.place_id } })
    const r = res.data?.result
    if (!r) return

    const components = r.address_components ?? []
    const geo = r.geometry?.location

    const parsed = parseGoogleComponents(components)

    // store only street + number
    ;(localPatient.value as any).address = parsed.streetOnly || ''
    addressQuery.value = (localPatient.value as any).address || ''

    // set city/zip from Google (user can override via your DB autocomplete)
    localPatient.value.city = parsed.city || ''
    localPatient.value.zip = parsed.zip || ''
    cityQuery.value = localPatient.value.city || ''

    // coordinates for marker
    localPatient.value.latitude = geo?.lat ?? null
    localPatient.value.longitude = geo?.lng ?? null

    center.value = [localPatient.value.latitude || 48.1486, localPatient.value.longitude || 17.1077]
    zoom.value = 15

    emit('clear-error', 'address')
    emit('clear-error', 'city')
    emit('clear-error', 'zip')
    emit('clear-error', 'coordinates')
  } catch (err) {
    console.error('onAddressSelect details error:', err)
  }
}

// -------------------- City + PSČ Autocomplete (your DB) --------------------
async function searchCity(e: AutoCompleteCompleteEvent) {
  try {
    const q = (e.query ?? '').trim()
    if (!q) {
      citySuggestions.value = []
      return
    }

    const res = await api.get('/v1/cities/suggest', { params: { q, limit: 10 } })
    citySuggestions.value = res.data?.data ?? []
  } catch (err) {
    console.error('searchCity error:', err)
    citySuggestions.value = []
  }
}

function onCitySelect(event: AutoCompleteOptionSelectEvent) {
  const sel: any = event?.value
  if (!sel) return

  localPatient.value.city = sel.name || ''
  localPatient.value.zip = normalizeZip(sel.zip || '')
  cityQuery.value = sel.name || ''

  emit('clear-error', 'city')
  emit('clear-error', 'zip')
}

// Keep query in sync if something else sets city directly
watch(
  () => localPatient.value.city,
  (v) => {
    const next = v ?? ''
    if (cityQuery.value !== next) cityQuery.value = next
  }
)
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-12">
        <label class="block text-normal text-accent">Osobné údaje</label>
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Meno</label>
        <InputText
          :disabled="disabled"
          v-model.trim="localPatient.first_name"
          fluid
          :invalid="submitted && !localPatient.first_name"
        />
        <small v-if="submitted && errors.first_name" class="text-warning">{{ errors.first_name }}</small>
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Priezvisko</label>
        <InputText
          :disabled="disabled"
          v-model.trim="localPatient.last_name"
          fluid
          :invalid="submitted && !localPatient.last_name"
        />
        <small v-if="submitted && errors.last_name" class="text-warning">{{ errors.last_name }}</small>
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Titul</label>
        <InputText :disabled="disabled" v-model.trim="localPatient.title" fluid />
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Rodné číslo</label>
        <InputText
          :disabled="disabled"
          v-model="localPatient.personal_number"
          maxlength="11"
          inputmode="numeric"
          pattern="[0-9]*"
          fluid
          :invalid="submitted && !localPatient.personal_number"
          @input="onPersonalNumberInput"
        />
        <small v-if="submitted && errors.personal_number" class="text-warning">{{ errors.personal_number }}</small>
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Pohlavie</label>
        <Select
          :disabled="disabled"
          v-model="localPatient.sex"
          :options="sexOptions"
          optionLabel="label"
          optionValue="value"
          fluid
          :invalid="submitted && !localPatient.sex"
        />
        <small v-if="submitted && errors.sex" class="text-warning">{{ errors.sex }}</small>
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Kontakt</label>
        <InputText :disabled="disabled" v-model.trim="localPatient.contact" fluid />
        <small v-if="submitted && errors.contact" class="text-warning">{{ errors.contact }}</small>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-12">
        <label class="block text-normal text-accent">Zdravotné detaily</label>
      </div>

      <div class="col-span-6">
        <label class="block text-normal mb-1">Lekár</label>
        <Select
          :disabled="disabled"
          v-model="localPatient.doctor_id"
          :options="doctorOptions"
          optionLabel="name"
          optionValue="id"
          fluid
          filter
          :invalid="submitted && !localPatient.doctor_id"
        />
        <small v-if="submitted && errors.doctor_id" class="text-warning">{{ errors.doctor_id }}</small>
      </div>

      <div class="col-span-6">
        <label class="block text-normal mb-1">Poisťovňa</label>
        <Select
          :disabled="disabled"
          v-model="localPatient.insurance_company_id"
          :options="insuranceOptions"
          optionLabel="name"
          optionValue="id"
          fluid
          :invalid="submitted && !localPatient.insurance_company_id"
        />
        <small v-if="submitted && errors.insurance_company_id" class="text-warning">
          {{ errors.insurance_company_id }}
        </small>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-12">
        <label class="block text-normal text-accent">Adresa</label>
      </div>

      <div class="col-span-4 flex flex-col gap-4">
        <div>
          <label class="block text-normal mb-1">Ulica</label>
          <AutoComplete
            :disabled="disabled"
            v-model="addressQuery"
            :suggestions="addressSuggestions"
            optionLabel="label"
            @complete="searchAddress"
            @item-select="onAddressSelect"
            @blur="revertAddressToStored"
            :minLength="3"
            fluid
            :invalid="submitted && !!errors.street"
          />
          <small v-if="submitted && errors.street" class="text-warning">{{ errors.street }}</small>
        </div>

        <div>
          <label class="block text-normal mb-1">Mesto</label>
          <AutoComplete
            :disabled="disabled"
            v-model="cityQuery"
            :suggestions="citySuggestions"
            optionLabel="label"
            @complete="searchCity"
            @item-select="onCitySelect"
            fluid
            :invalid="submitted && !!errors.city"
          />
          <small v-if="submitted && errors.city" class="text-warning">{{ errors.city }}</small>
        </div>

        <div>
          <label class="block text-normal mb-1">PSČ</label>
          <InputText :disabled="disabled" v-model.trim="localPatient.zip" fluid :invalid="submitted && !!errors.zip" />
          <small v-if="submitted && errors.zip" class="text-warning">{{ errors.zip }}</small>
        </div>

        <small v-if="submitted && errors.coordinates" class="text-warning">
          {{ errors.coordinates }}
        </small>
      </div>

      <div class="col-span-8">
        <LMap
          ref="mapRef"
          :center="center"
          :zoom="zoom"
          :useGlobalLeaflet="false"
          class="w-full h-full rounded-md overflow-hidden"
        >
          <l-tile-layer
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            layer-type="base"
            name="OpenStreetMap"
            v-bind="lMapLayerOptions"
          />
          <LMarker v-if="localPatient.latitude && localPatient.longitude" :lat-lng="[localPatient.latitude, localPatient.longitude]" />
        </LMap>
      </div>
    </div>
  </div>
</template>
