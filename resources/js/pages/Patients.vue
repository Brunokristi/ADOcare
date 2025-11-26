<script setup>
import { ref, computed, onMounted } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { usePatientStore } from '@/stores/patientStore';
import SecondaryNavbar from '@/components/SecondaryNavbar.vue';

import L from 'leaflet';
import 'leaflet/dist/leaflet.css'

// OpenRouteService API key
const ORS_API_KEY = import.meta.env.VITE_ORS_API_KEY;

const toast = useToast();
const patientStore = usePatientStore();

// -------------------- TABLE DATA --------------------

const dt = ref(null);
const showRows = ref([]);
const submitted = ref(false);

const rows = ref([
    { id: 1, firstname: 'Bruno', lastname: 'Kristián', personalnumber: '713482/2025', address: 'Modré zeme 21', city: 'Lučenec', doctorId: 1 },
    { id: 2, firstname: 'Laura', lastname: 'Šimková', personalnumber: '825374/2019', address: 'Javorová 12', city: 'Zvolen', doctorId: 2 },
    { id: 3, firstname: 'Samuel', lastname: 'Pavlík', personalnumber: '940215/3021', address: 'Slnečná 44', city: 'Banská Bystrica', doctorId: 3 },
    // ... keep the rest unchanged
]);

const products = ref([...rows.value]);

// -------------------- FILTER --------------------
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// -------------------- PATIENT MODEL --------------------

const productDialog = ref(false);

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

// -------------------- SELECT OPTIONS --------------------

const genderOptions = [
    { label: 'Muž', value: 'M' },
    { label: 'Žena', value: 'F' }
];

const doctorOptions = [
    { id: 1, name: 'MUDr. Viliam Džurbala' },
    { id: 2, name: 'MUDr. Jana Kováčová' },
    { id: 3, name: 'MUDr. Peter Horváth' },
    { id: 4, name: 'MUDr. Lucia Mareková' },
];

const insuranceOptions = [
    { code: '25', name: 'VšZP' },
    { code: '24', name: 'Dôvera' },
    { code: '27', name: 'Union' },
];

// -------------------- MAP --------------------

const map = ref(null);
const routeLayer = ref(null);

function initMap() {
    if (map.value) return;

    const el = document.getElementById('patient-map');
    if (!el) return;

    map.value = L.map('patient-map').setView([48.1486, 17.1077], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
    }).addTo(map.value);
}

async function loadRoute() {
    if (!ORS_API_KEY || !map.value) return;

    const body = {
        coordinates: [
            [17.1077, 48.1486],
            [17.12, 48.16]
        ]
    };

    const res = await fetch("https://api.openrouteservice.org/v2/directions/driving-car", {
        method: "POST",
        headers: {
            "Authorization": ORS_API_KEY,
            "Content-Type": "application/json"
        },
        body: JSON.stringify(body)
    });

    const data = await res.json();

    if (routeLayer.value) routeLayer.value.remove();

    routeLayer.value = L.geoJSON(data, {
        style: {
            color: "#5C9EAD",
            weight: 4,
        }
    }).addTo(map.value);

    map.value.fitBounds(routeLayer.value.getBounds(), { padding: [20, 20] });
}

onMounted(() => {
    initMap();
    loadRoute();
});

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
    product.value = { ...row };
    productDialog.value = true;

    setTimeout(() => {
        initMap();
        loadRoute();
    }, 50);
};

const saveProduct = () => {
    submitted.value = true;

    if (!product.value.firstName || !product.value.lastName) return;

    if (product.value.id) {
        const index = products.value.findIndex(p => p.id === product.value.id);
        products.value[index] = { ...product.value };
    } else {
        product.value.id = Date.now();
        products.value.push({ ...product.value });
    }

    productDialog.value = false;
};

const deleteProductsDialog = ref(false);

const deleteshowRows = () => {
    products.value = products.value.filter(val => !showRows.value.includes(val));
    deleteProductsDialog.value = false;
    showRows.value = [];
};

// -------------------- PATIENT PIN --------------------

const selectPatient = (row) => {
    patientStore.setPatient(row);
};

// -------------------- INFO LINE --------------------

const recordsInfo = computed(() => {
    if (!dt.value) return "";
    const total = products.value.length;
    const filtered = dt.value.processedData?.length;
    return `${filtered ?? total} z ${total} záznamov`;
});

function formatBirthNumber(value) {
    let digits = value.replace(/\D/g, '');

    const first = digits.slice(0, 6);
    const last = digits.slice(6, 10);

    if (last.length > 0) {
        return `${first}/${last}`;
    }

    return first;
}

</script>


<template>
    <SecondaryNavbar />

    <div>
        <!-- Toast must be rendered somewhere for useToast() to work -->
        <Toast />

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
                    <Dropdown
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
                    <Dropdown
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
                    </Dropdown>
                    
                </div>

                <div class="col-span-6">
                    <label class="block text-normal mb-1">Poisťovňa</label>
                    <Dropdown
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
            class="!bg-accent !px-md !text-white hover:!bg-darkgrey"
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
