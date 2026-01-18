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

// Sync local patient + input when parent patient changes
watch(
  () => props.patient,
  (p) => {
    const next = { ...(p ?? ({} as Patient)) }
    localPatient.value = next

    // ✅ show only stored address (street + number)
    addressQuery.value = (next as any).address ?? ''
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

// -------------------- Doctors / Insurance (unchanged) --------------------
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

  const doctors = await api.fetchEntities<Doctor>('v1/doctors', {
    branch_id: branchId,
    mark_favourites_for_branch_id: branchId,
    filter: { is_favourite: 1 },
  })

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

  // Attach click listener to the Leaflet map after it's initialized
  setTimeout(() => {
    if (mapRef.value?.leafletObject) {
      mapRef.value.leafletObject.on('click', onMapClick)
    }
  }, 100)
}

function onPersonalNumberInput(e: Event) {
  const input = e.target as HTMLInputElement

  // remove EVERYTHING except digits
  const digitsOnly = input.value.replace(/\D+/g, '')

  // update model as STRING
  localPatient.value.personal_number = digitsOnly
}

watch(
  () => localPatient.value.personal_number,
  (val) => {
    if (!val) return
    const clean = val.replace(/\D+/g, '')
    if (val !== clean) {
      localPatient.value.personal_number = clean
    }
  }
)



// -------------------- Address Autocomplete --------------------
const addressSuggestions = ref<any[]>([])

// enforce "select or keep stored"
function revertAddressToStored() {
  addressQuery.value = (localPatient.value as any).address ?? ''
}

async function searchAddress(e: AutoCompleteCompleteEvent) {
  try {
    const q = e.query
    if (!q) {
      addressSuggestions.value = []
      return
    }

    const res = await api.get('/v1/geocode/autocomplete', {
      params: { text: q },
    })

    const features = res.data?.features ?? []

    addressSuggestions.value = features.map((f: any) => {
      const p = f.properties ?? {}
      const coords = f.geometry?.coordinates ?? []

      const streetOnly = [p.street, p.housenumber].filter(Boolean).join(' ').trim() || p.name || ''
      const city = p.locality || p.county || p.region || ''
      const zip = p.postalcode || ''

      return {
        // PrimeVue shows suggestions using optionLabel="label"
        // ✅ show full label in dropdown if you want, but we will NOT put this into the input on select
        label: p.label || `${streetOnly}${city ? ', ' + city : ''}${zip ? ', ' + zip : ''}`,

        // ✅ what we store + show in the input
        streetOnly,

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

function onAddressSelect(event: AutoCompleteOptionSelectEvent) {
  try {
    const sel: any = event?.value
    if (!sel) return

    // ✅ store only address (street + number)
    ;(localPatient.value as any).address = sel.streetOnly || ''

    // keep these too
    localPatient.value.city = sel.city || ''
    localPatient.value.zip = sel.zip || ''
    localPatient.value.latitude = sel.lat ?? null
    localPatient.value.longitude = sel.lng ?? null

    center.value = [localPatient.value.latitude || 48.1486, localPatient.value.longitude || 17.1077]
    zoom.value = 15

    addressQuery.value = sel.streetOnly || ''

    emit('clear-error', 'address')
    emit('clear-error', 'city')
    emit('clear-error', 'zip')
    emit('clear-error', 'coordinates')
  } catch (err) {
    console.error('onAddressSelect error:', err)
  }
}

// -------------------- Reverse Geocoding (Map Click) --------------------
async function onMapClick(e: any) {
  try {
    console.log('Map click event received:', e)
    
    // Leaflet click event has latlng object
    const lat = e.latlng?.lat ?? e.latlng?._lat
    const lng = e.latlng?.lng ?? e.latlng?._lng
    
    console.log('Extracted coordinates - lat:', lat, 'lng:', lng)
    
    if (lat === undefined || lng === undefined || lat === null || lng === null) {
      console.warn('Invalid coordinates from click event')
      return
    }
    
    console.log('Map clicked at:', lat, lng)
    
    // Update marker position
    localPatient.value.latitude = lat
    localPatient.value.longitude = lng
    
    console.log('Updated patient lat/lng:', localPatient.value.latitude, localPatient.value.longitude)
    
    // Reverse geocode to get address details
    const res = await api.get('/v1/geocode/reverse', {
      params: { lat, lon: lng },
    })
    
    console.log('Reverse geocoding response:', res.data)
    
    const features = res.data?.features ?? []
    if (features.length === 0) {
      console.warn('No reverse geocoding results found')
      return
    }
    
    const feature = features[0]
    const p = feature.properties ?? {}
    
    // Extract address components
    const streetOnly = [p.street, p.housenumber].filter(Boolean).join(' ').trim() || p.name || ''
    const city = p.locality || p.county || p.region || ''
    const zip = p.postalcode || ''
    
    console.log('Parsed address components:', { streetOnly, city, zip })
    
    // Update form fields
    ;(localPatient.value as any).address = streetOnly || ''
    localPatient.value.city = city || ''
    localPatient.value.zip = zip || ''
    addressQuery.value = streetOnly || ''
    
    center.value = [lat, lng]
    zoom.value = 15
    
    emit('clear-error', 'address')
    emit('clear-error', 'city')
    emit('clear-error', 'zip')
    emit('clear-error', 'coordinates')
    
    console.log('Reverse geocoding result:', { streetOnly, city, zip, lat, lng })
  } catch (err) {
    console.error('Reverse geocoding error:', err)
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-12">
        <label class="block text-normal text-accent">Osobné údaje</label>
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Meno</label>
        <InputText v-model.trim="localPatient.first_name" fluid :invalid="submitted && !localPatient.first_name" />
        <small v-if="submitted && errors.first_name" class="text-warning">{{ errors.first_name }}</small>
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Priezvisko</label>
        <InputText v-model.trim="localPatient.last_name" fluid :invalid="submitted && !localPatient.last_name" />
        <small v-if="submitted && errors.last_name" class="text-warning">{{ errors.last_name }}</small>
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Titul</label>
        <InputText v-model.trim="localPatient.title" fluid />
      </div>

      <div class="col-span-4">
        <label class="block text-normal mb-1">Rodné číslo</label>
        <InputText
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
        <InputText v-model.trim="localPatient.contact" fluid />
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
            v-model="addressQuery"
            :suggestions="addressSuggestions"
            optionLabel="label"
            @complete="searchAddress"
            @item-select="onAddressSelect"
            @blur="revertAddressToStored"
            fluid
            :invalid="submitted && !!errors.street"
          />
          <small v-if="submitted && errors.street" class="text-warning">{{ errors.street }}</small>
        </div>

        <div>
          <label class="block text-normal mb-1">Mesto</label>
          <InputText v-model.trim="localPatient.city" fluid :invalid="submitted && !!errors.city" />
          <small v-if="submitted && errors.city" class="text-warning">{{ errors.city }}</small>
        </div>

        <div>
          <label class="block text-normal mb-1">PSČ</label>
          <InputText v-model.trim="localPatient.zip" fluid :invalid="submitted && !!errors.zip" />
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
          <LMarker
            v-if="localPatient.latitude && localPatient.longitude"
            :lat-lng="[localPatient.latitude, localPatient.longitude]"
          />
        </LMap>
      </div>
    </div>
  </div>
</template>
