<script setup lang="ts">
import { onMounted, ref } from 'vue';
import api from '@/services/api';
import useAuthStore from '@/stores/auth';
import type { Doctor, InsuranceCompany, Patient } from '@/types/models';
import type { Map } from 'leaflet';
import { LMap, LMarker, LTileLayer } from "@vue-leaflet/vue-leaflet";
import type { AutoCompleteCompleteEvent, AutoCompleteOptionSelectEvent } from 'primevue/autocomplete';

const props = defineProps<{
    patient: Patient;
}>();

const emit = defineEmits<{
    (e: 'update:patient', patient: Patient): void;
}>();

const submitted = true;
const errors = ref<{ [key: string]: string }>({});

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


        // props.patient.street = sel.street || '';
        props.patient.city = sel.city || '';
        props.patient.zip = sel.zip || '';

        mapLat.value = props.patient.latitude = sel.lat ?? null;
        mapLng.value = props.patient.longitude = sel.lng ?? null;

        center.value = [props.patient.latitude || 48.1486, props.patient.longitude || 17.1077];

        addressQuery.value = sel.label;

        if (errors.value.street) delete errors.value.street;
        if (errors.value.city) delete errors.value.city;
        if (errors.value.zip) delete errors.value.zip;
        if (errors.value.coordinates) delete errors.value.coordinates;


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
                <InputText v-model.trim="patient.first_name" fluid :invalid="submitted && !patient.first_name" />
                <small v-if="submitted && !patient.first_name" class="text-warning">
                    Meno je povinné.
                </small>
            </div>

            <div class="col-span-4">
                <label class="block text-normal mb-1">Priezvisko</label>
                <InputText v-model.trim="patient.last_name" fluid :invalid="submitted && !patient.last_name" />
                <small v-if="submitted && !patient.last_name" class="text-warning">
                    Priezvisko je povinné.
                </small>
            </div>

            <div class="col-span-4">
                <label class="block text-normal mb-1">Titul</label>
                <InputText v-model.trim="patient.title" fluid />
            </div>

            <div class="col-span-4">
                <label class="block text-normal mb-1">Rodné číslo</label>
                <InputText v-model.trim="patient.personal_number" maxlength="11" fluid />
            </div>

            <div class="col-span-4">
                <label class="block text-normal mb-1">Pohlavie</label>
                <Select v-model="patient.sex" :options="sexOptions" optionLabel="label" optionValue="value" fluid />
            </div>

            <div class="col-span-4">
                <label class="block text-normal mb-1">Kontakt</label>
                <InputText v-model.trim="patient.contact" fluid />
            </div>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                <label class="block text-normal text-accent">Zdravotné detaily</label>
            </div>
            <div class="col-span-6">
                <label class="block text-normal mb-1">Lekár</label>
                <Select v-model="patient.doctor_id" :options="doctorOptions" optionLabel="name" optionValue="id" fluid
                    filter>
                    <template #footer>
                        <div class="p-2">
                            <Button label="Pridať" fluid variant="text" size="small" icon="bi bi-plus"
                                class="text-accent! bg-tag3! hover:bg-accent! hover:text-white!" />
                        </div>
                    </template>
                </Select>

            </div>

            <div class="col-span-6">
                <label class="block text-normal mb-1">Poisťovňa</label>
                <Select v-model="patient.insurance_company_id" :options="insuranceOptions" optionLabel="name"
                    optionValue="id" fluid />
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
                    <InputText v-model.trim="patient.city" fluid :invalid="submitted && !!errors.city" />
                    <small v-if="submitted && errors.city" class="text-warning">{{ errors.city }}</small>
                </div>

                <div>
                    <label class="block text-normal mb-1">PSČ</label>
                    <InputText v-model.trim="patient.zip" fluid :invalid="submitted && !!errors.zip" />
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
                    <LMarker v-if="patient.latitude && patient.longitude"
                        :lat-lng="[patient.latitude, patient.longitude]" />
                </LMap>
            </div>
        </div>

    </div>
</template>
