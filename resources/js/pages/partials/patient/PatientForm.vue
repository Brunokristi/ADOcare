<script setup lang="ts">
import { onMounted, ref, watch, toRef, computed } from 'vue';
import api from '@/services/api';
import useAuthStore from '@/stores/auth';
import type { Doctor, InsuranceCompany, Patient } from '@/types/models';
import type { Map } from 'leaflet';
import { LMap, LMarker, LTileLayer } from "@vue-leaflet/vue-leaflet";
import type { AutoCompleteCompleteEvent, AutoCompleteOptionSelectEvent } from 'primevue/autocomplete';

const props = defineProps<{
    patient: Patient;
    submitted?: boolean;
    errors?: { [key: string]: string } | null;
}>();

const emit = defineEmits<{
    (e: 'update:patient', patient: Patient): void;
    (e: 'clear-error', key: string): void;
}>();

const submitted = computed(() => !!props.submitted);
const errors = computed(() => props.errors ?? {});

// local editable copy of the patient so we can emit updates instead of mutating props
const localPatient = ref<Patient>({ ...(props.patient ?? {}) });
watch(() => props.patient, (p) => {
    localPatient.value = { ...(p ?? {}) };
}, { immediate: true, deep: true });

// propagate local changes up to parent
watch(localPatient, (val) => {
    try {
        const parentVal = props.patient ?? {};
        // only emit when changed to avoid recursive loops
        if (JSON.stringify(val) !== JSON.stringify(parentVal)) {
            emit('update:patient', { ...(val as Patient) });
        }
    } catch (err) {
        // fallback: still emit
        emit('update:patient', { ...(val as Patient) });
    }
}, { deep: true });

const sexOptions = [
    { label: 'Muž', value: 'M' },
    { label: 'Žena', value: 'F' },
];

const doctorOptions = ref<{ id: number; name: string }[]>([]);
const insuranceOptions = ref<{ id: number; name: string }[]>([]);

const currentBranchId = useAuthStore().currentBranch?.id || 0;





onMounted(async () => {
    if (!currentBranchId) return;
    // Fetch doctor options from API
    const doctors = await api.fetchEntities<Doctor>(`v1/doctors`, { all: true, branch_id: currentBranchId });
    const insuranceCompanies = await api.fetchEntities<InsuranceCompany>(`/v1/insurance-companies`, { all: true });

    doctorOptions.value = doctors.map(doc => ({ id: doc.id, name: `${doc.first_name} ${doc.last_name}` }));
    insuranceOptions.value = insuranceCompanies.map(ic => ({ id: ic.id, name: ic.name ?? '<Neznáma poisťovňa>' }));

    initMap()
});


// -------------------- Map & GeoJSON --------------------


const map = ref<Map | null>(null);
const center = ref<[number, number]>([48.1486, 17.1077]); // Default to Bratislava
const zoom = ref<number>(13);
const lMapLayerOptions = ref<any>({
    attributionControl: false,
    maxZoom: 19,


});

const mapLat = ref<number | null>(48.1486);
const mapLng = ref<number | null>(17.1077);

function initMap() {
    if (!props.patient) return;

    const lat = props.patient.latitude;
    const lng = props.patient.longitude;
    if (!lat || !lng) return;

    mapLat.value = lat;
    mapLng.value = lng;
    center.value = [lat, lng];
    zoom.value = 15;
}



// -------------------- Address Autocomplete --------------------
const addressSuggestions = ref([]);
const addressQuery = ref(props.patient.address || '');

async function searchAddress(e: AutoCompleteCompleteEvent) {
    try {
        const q = e.query;
        if (!q) {
            addressSuggestions.value = [];
            return;
        }

        const res = await api.get('/v1/geocode/autocomplete', {
            params: { text: q },
        });

        const features = res.data?.features ?? [];

        addressSuggestions.value = features.map((f: any) => {
            const p = f.properties ?? {};
            const coords = f.geometry?.coordinates ?? [];

            const street =
                [p.street, p.housenumber].filter(Boolean).join(' ').trim() ||
                p.name ||
                '';

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


function onAddressSelect(event: AutoCompleteOptionSelectEvent) {
    try {
        const sel = event?.value;
        if (!sel) return;

        // update local patient and emit via watcher
        // note: patient.street intentionally left to parent editing flow
        localPatient.value.city = sel.city || '';
        localPatient.value.zip = sel.zip || '';

        mapLat.value = localPatient.value.latitude = sel.lat ?? null;
        mapLng.value = localPatient.value.longitude = sel.lng ?? null;

        center.value = [localPatient.value.latitude || 48.1486, localPatient.value.longitude || 17.1077];

        addressQuery.value = sel.label;

        // tell parent to clear these validation errors
        emit('clear-error', 'street');
        emit('clear-error', 'city');
        emit('clear-error', 'zip');
        emit('clear-error', 'coordinates');

    } catch (err) {
        console.error('onAddressSelect error:', err);
    }
}


</script>



<template>
    <div class="flex flex-col gap-6">
        <!-- same inner content copied from above -->
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
                <InputText v-model.trim="localPatient.personal_number" maxlength="11" fluid :invalid="submitted && !localPatient.personal_number" />
                <small v-if="submitted && errors.personal_number" class="text-warning">{{ errors.personal_number }}</small>
            </div>

            <div class="col-span-4">
                <label class="block text-normal mb-1">Pohlavie</label>
                <Select v-model="localPatient.sex" :options="sexOptions" optionLabel="label" optionValue="value" fluid :invalid="submitted && !localPatient.sex" />
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
                <Select v-model="localPatient.doctor_id" :options="doctorOptions" optionLabel="name" optionValue="id" fluid
                    filter :invalid="submitted && !localPatient.doctor_id">
                    <template #footer>
                        <div class="p-2">
                            <Button label="Pridať" fluid variant="text" size="small" icon="bi bi-plus"
                                class="text-accent! bg-tag3! hover:bg-accent! hover:text-white!" />
                        </div>
                    </template>
                </Select>
                <small v-if="submitted && errors.doctor_id" class="text-warning">{{ errors.doctor_id }}</small>

            </div>

            <div class="col-span-6">
                <label class="block text-normal mb-1">Poisťovňa</label>
                <Select v-model="localPatient.insurance_company_id" :options="insuranceOptions" optionLabel="name"
                    optionValue="id" fluid :invalid="submitted && !localPatient.insurance_company_id" />
                <small v-if="submitted && errors.insurance_company_id" class="text-warning">{{ errors.insurance_company_id }}</small>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Adresa</label>
            </div>
            <div class="col-span-4 flex flex-col gap-4">
                <div>
                    <label class="block text-normal mb-1">Ulica</label>
                    <AutoComplete v-model="addressQuery" :suggestions="addressSuggestions" optionLabel="label"
                        @complete="searchAddress" @item-select="onAddressSelect" fluid
                        :invalid="submitted && !!errors.street" />
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
                <!-- <div id="patient-map" class="w-full h-full rounded-md overflow-hidden"></div> -->
                <LMap :center="center" :zoom="zoom" :useGlobalLeaflet="false"
                    class="w-full h-full rounded-md overflow-hidden">
                    <!-- <l-geo-json :geojson="geojson" :options="geojsonOptions" /> -->
                    <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" layer-type="base"
                        name="OpenStreetMap" v-bind="lMapLayerOptions" />
                    />
                    <LMarker v-if="localPatient.latitude && localPatient.longitude"
                        :lat-lng="[localPatient.latitude, localPatient.longitude]" />
                </LMap>
            </div>
        </div>

    </div>
</template>
