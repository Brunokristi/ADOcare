<script setup lang="ts">
import { ref, onMounted, computed, watch, onBeforeUnmount } from 'vue';
import api from '@/services/api';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const dt = ref<any>(null);

const patients = ref([] as any[]);
const loading = ref(false);

// Dialog state
const patientDialog = ref(false);
const deletePatientDialog = ref(false);
const deletePatientsDialog = ref(false);
const patient = ref<any>({});
const selectedPatients = ref<any[]>([]);
const submitted = ref(false);

const filters = ref<{ global: { value: string | null; matchMode: any } }>({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// pagination
const rowsPerPage = ref(10);
const rowsPerPageOptions = [10, 25, 50, 100];

const onPage = (e: any) => {
    if (!e) return;
    if (typeof e.rows === 'number') rowsPerPage.value = e.rows;
    // calculate 1-based page number
    let page = 1;
    if (typeof e.page === 'number') page = e.page + 1;
    else if (typeof e.first === 'number' && typeof e.rows === 'number') page = Math.floor(e.first / e.rows) + 1;
    loadPage(page);
};

const onSort = (e: any) => {
    // PrimeVue sends e.sortField and e.sortOrder (1 for asc, -1 for desc)
    sortField.value = e.sortField ?? null;
    sortOrder.value = e.sortOrder ?? null;
    loadPage(1);
};

// debounced search input
const searchInput = ref<string>(filters.value.global.value ?? '');
let searchTimer: number | null = null;
const totalRecords = ref<number>(0);
const sortField = ref<string | null>(null);
const sortOrder = ref<number | null>(null);

const loadPage = async (page = 1) => {
    loading.value = true;
    try {
        const params: any = { paginate: true, page, per_page: rowsPerPage.value };
        if (filters.value.global.value) params.q = filters.value.global.value;
        if (sortField.value) {
            const prefix = sortOrder.value === -1 ? '-' : '';
            params.sort = `${prefix}${sortField.value}`;
        }
        const res = await api.get('/v1/patients', { params });
        const data = res.data?.data ?? {};
        const items = data.items ?? [];
        patients.value = Array.isArray(items) ? items : [];
        totalRecords.value = data.meta?.total ?? data.total ?? patients.value.length;
    } catch (e) {
        console.error(e);
        patients.value = [];
        totalRecords.value = 0;
    } finally {
        loading.value = false;
    }
};

watch(searchInput, (val) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        filters.value.global.value = val;
        loadPage(1);
    }, 300);
});

onBeforeUnmount(() => {
    if (searchTimer) clearTimeout(searchTimer);
});

onMounted(() => loadPage(1));

const openNew = () => {
    patient.value = {
        id: null,
        first_name: '',
        last_name: '',
        personal_number: '',
        sex: '',
        contact: '',
        address: '',
        city: '',
        zip: '',
    };
    submitted.value = false;
    patientDialog.value = true;
};

const hideDialog = () => {
    patientDialog.value = false;
    submitted.value = false;
};

const savePatient = () => {
    submitted.value = true;
    if (patient.value.first_name?.toString().trim()) {
        if (patient.value.id) {
            const idx = findIndexById(patient.value.id);
            if (idx !== -1) {
                patients.value[idx] = { ...patient.value };
                toast.add({ severity: 'success', summary: 'Updated', detail: 'Patient updated', life: 3000 });
            }
        } else {
            patient.value.id = createId();
            patients.value.push({ ...patient.value });
            toast.add({ severity: 'success', summary: 'Created', detail: 'Patient created', life: 3000 });
        }
        patientDialog.value = false;
        patient.value = {};
    }
};

const editPatient = (p: any) => {
    patient.value = { ...p };
    patientDialog.value = true;
};


const deletePatient = () => {
    patients.value = patients.value.filter((v) => v.id !== patient.value.id);
    deletePatientDialog.value = false;
    patient.value = {};
    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Patient deleted', life: 3000 });
};

const confirmDeleteSelected = () => {
    deletePatientsDialog.value = true;
};

const deleteSelected = () => {
    patients.value = patients.value.filter((v) => !selectedPatients.value.includes(v));
    deletePatientsDialog.value = false;
    selectedPatients.value = [];
    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Selected patients deleted', life: 3000 });
};

const findIndexById = (id: any) => {
    return patients.value.findIndex((p) => p.id === id);
};

const createId = () => {
    return Math.floor(Math.random() * 1000000);
};

const recordsInfo = computed(() => {
    if (!dt.value) return '';
    const filtered = dt.value.processedData?.length ?? patients.value.length;
    const total = totalRecords.value ?? patients.value.length;
    return `${filtered} z ${total} záznamov`;
});
</script>

<template>
    <div class="p-6">
        <!-- Toast must be rendered somewhere for useToast() to work -->
        <Toast />

        <h2 class="text-lg font-bold mb-4">Patients</h2>

        <Toolbar class="!bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between">
            <template #end>
                <div class="flex items-center gap-2 ">
                    <div class="flex items-center bg-white rounded px-2 py-1">
                        <i class="bi bi-search text-darkgrey mr-2" />
                        <InputText v-model="searchInput" placeholder="Search…" />
                    </div>

                    <Button icon="bi bi-plus" @click="openNew" class="!bg-accent !border-accent" />

                    <Button icon="bi bi-eraser" @click="confirmDeleteSelected"
                        :disabled="!selectedPatients || !selectedPatients.length" class="!bg-warning !border-warning" />
                </div>
            </template>
        </Toolbar>

        <DataTable ref="dt" v-model:selection="selectedPatients" :value="patients" dataKey="id" :filters="filters"
            stripedRows removableSort class="mt-4" paginator :rows="rowsPerPage"
            :rowsPerPageOptions="rowsPerPageOptions"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
            currentPageReportTemplate="{first} - {last} z {totalRecords}" @page="onPage" @sort="onSort"
            :sortField="sortField ?? undefined" :sortOrder="sortOrder ?? undefined" :totalRecords="totalRecords"
            :lazy="true" :loading="loading">
            <Column selectionMode="multiple" style="width: 3rem" :exportable="false" />
            <Column field="first_name" header="First name" sortable />
            <Column field="last_name" header="Last name" sortable />
            <Column field="personal_number" header="Personal No." />
            <Column field="sex" header="Sex" />
            <Column field="city" header="City" />
            <Column :exportable="false" style="width: 3rem">
                <template #body="slotProps">
                    <Button icon="bi bi-pencil" @click="editPatient(slotProps.data)"
                        class="!text-darkgrey hover:!bg-transparent" />
                </template>
            </Column>
        </DataTable>

        <div class="text-mini text-accent flex justify-end w-full py-2">{{ recordsInfo }}</div>

        <Dialog v-model:visible="patientDialog" :style="{ width: '600px' }" header="Patient Details" :modal="true">
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold mb-2">First name</label>
                        <InputText v-model="patient.first_name" />
                    </div>
                    <div>
                        <label class="block font-bold mb-2">Last name</label>
                        <InputText v-model="patient.last_name" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold mb-2">Personal No.</label>
                        <InputText v-model="patient.personal_number" />
                    </div>
                    <div>
                        <label class="block font-bold mb-2">Sex</label>
                        <InputText v-model="patient.sex" />
                    </div>
                    <div>
                        <label class="block font-bold mb-2">Contact</label>
                        <InputText v-model="patient.contact" />
                    </div>
                </div>

                <div>
                    <label class="block font-bold mb-2">Address</label>
                    <InputText v-model="patient.address" />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold mb-2">City</label>
                        <InputText v-model="patient.city" />
                    </div>
                    <div>
                        <label class="block font-bold mb-2">ZIP</label>
                        <InputText v-model="patient.zip" />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Save" icon="pi pi-check" @click="savePatient" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deletePatientDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="patient">Are you sure you want to delete <b>{{ patient.first_name }} {{ patient.last_name
                        }}</b>?</span>
            </div>
            <template #footer>
                <Button label="No" icon="pi pi-times" text @click="deletePatientDialog = false" />
                <Button label="Yes" icon="pi pi-check" @click="deletePatient" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deletePatientsDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span>Are you sure you want to delete the selected patients?</span>
            </div>
            <template #footer>
                <Button label="No" icon="pi pi-times" text @click="deletePatientsDialog = false" />
                <Button label="Yes" icon="pi pi-check" text @click="deleteSelected" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped></style>
