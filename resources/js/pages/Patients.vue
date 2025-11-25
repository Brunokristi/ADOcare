<script setup>
import { ref, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { usePatientStore } from '@/stores/patientStore';
import SecondaryNavbar from '@/components/SecondaryNavbar.vue'


const patientStore = usePatientStore();
const toast = useToast();
const dt = ref(null);
const isEditing = computed(() => !!product.value.id)

const rows = ref([
    {
        id: 1,
        firstname: 'Bruno',
        lastname: 'Kristián',
        personalnumber: '713482/2025',
        address: 'Modré zeme 21',
        city: 'Lučenec',
        doctor: 'MUDr. Viliam Džurbala',
    },

    { id: 2, firstname: 'Laura', lastname: 'Šimková', personalnumber: '825374/2019', address: 'Javorová 12', city: 'Zvolen', doctor: 'MUDr. Peter Horváth' },
    { id: 3, firstname: 'Samuel', lastname: 'Pavlík', personalnumber: '940215/3021', address: 'Slnečná 44', city: 'Banská Bystrica', doctor: 'MUDr. Lucia Mareková' },
    { id: 4, firstname: 'Nina', lastname: 'Kováčiková', personalnumber: '013125/1045', address: 'Lipová 3', city: 'Detva', doctor: 'MUDr. Jana Kováčová' },
    { id: 5, firstname: 'Tobias', lastname: 'Urban', personalnumber: '752639/0954', address: 'Borovicová 18', city: 'Rimavská Sobota', doctor: 'MUDr. Andrej Bielik' },
    { id: 6, firstname: 'Ela', lastname: 'Farkašová', personalnumber: '561204/6032', address: 'Lúčna 9', city: 'Krupina', doctor: 'MUDr. Mária Zelená' },
    { id: 7, firstname: 'Matúš', lastname: 'Zajac', personalnumber: '381127/4521', address: 'Hviezdna 7', city: 'Lučenec', doctor: 'MUDr. Tomáš Novotný' },
    { id: 8, firstname: 'Viktória', lastname: 'Petrušová', personalnumber: '452398/7832', address: 'Čerešňová 5', city: 'Fiľakovo', doctor: 'MUDr. Simona Krajčíková' },
    { id: 9, firstname: 'Oliver', lastname: 'Moravčík', personalnumber: '624319/1187', address: 'Družstevná 22', city: 'Banská Bystrica', doctor: 'MUDr. Rastislav Urban' },
    { id: 10, firstname: 'Sofia', lastname: 'Hrivnáková', personalnumber: '270563/9903', address: 'Tichá 4', city: 'Zvolen', doctor: 'MUDr. Veronika Foltínová' },
    { id: 11, firstname: 'Leo', lastname: 'Švantner', personalnumber: '735902/6623', address: 'Topoľová 15', city: 'Detva', doctor: 'MUDr. Patrik Holub' },
    { id: 12, firstname: 'Karin', lastname: 'Hrdličková', personalnumber: '844213/5531', address: 'Ružová 2', city: 'Krupina', doctor: 'MUDr. Barbora Kalinová' },
    { id: 13, firstname: 'Alex', lastname: 'Mikula', personalnumber: '513478/2249', address: 'Strieborná 11', city: 'Rimavská Sobota', doctor: 'MUDr. Marek Škoda' },
    { id: 14, firstname: 'Tamara', lastname: 'Benčíková', personalnumber: '912475/7724', address: 'Orechová 8', city: 'Lučenec', doctor: 'MUDr. Nikola Veselá' },
    { id: 15, firstname: 'Jakub', lastname: 'Holienčin', personalnumber: '064321/3350', address: 'Brezy 19', city: 'Zvolen', doctor: 'MUDr. Adam Krajčír' },
    { id: 16, firstname: 'Rebeka', lastname: 'Dianišková', personalnumber: '154298/4123', address: 'Mostová 6', city: 'Banská Bystrica', doctor: 'MUDr. Eva Malíková' },
    { id: 17, firstname: 'Filip', lastname: 'Korec', personalnumber: '310982/9902', address: 'Parková 33', city: 'Detva', doctor: 'MUDr. Filip Konečný' },
    { id: 18, firstname: 'Stella', lastname: 'Krištofová', personalnumber: '485312/2876', address: 'Jarná 1', city: 'Fiľakovo', doctor: 'MUDr. Daniela Hrivnáková' },
    { id: 19, firstname: 'Dávid', lastname: 'Pavlus', personalnumber: '920314/6342', address: 'Gaštanová 17', city: 'Krupina', doctor: 'MUDr. Roman Bartoš' },
    { id: 20, firstname: 'Mia', lastname: 'Krajčová', personalnumber: '752630/8831', address: 'Záhradná 29', city: 'Lučenec', doctor: 'MUDr. Viliam Džurbala' },
    { id: 21, firstname: 'Erik', lastname: 'Salay', personalnumber: '190624/5501', address: 'Panenská 10', city: 'Rimavská Sobota', doctor: 'MUDr. Peter Horváth' },
]);



const products = ref([...rows.value]);

const productDialog = ref(false);
const deleteProductDialog = ref(false);
const deleteProductsDialog = ref(false);
const product = ref({});
const showRows = ref([]);
const submitted = ref(false);

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const openNew = () => {
    product.value = {
        id: null,
        code: '',
        price25: null,
        price24: null,
        price27: null,
        description: '',
    };
    submitted.value = false;
    productDialog.value = true;
};

const hideDialog = () => {
    productDialog.value = false;
    submitted.value = false;
};

const saveProduct = () => {
    submitted.value = true;

    const isValid =
    product.value.code &&
    product.value.price25 !== null &&
    product.value.price24 !== null &&
    product.value.price27 !== null &&
    product.value.description?.trim()

    if (!isValid) return

    if (product?.value.code?.toString().trim()) {
        if (product.value.id) {
            // update existing
            const index = findIndexById(product.value.id);
            if (index !== -1) {
                products.value[index] = { ...product.value };
                toast.add({
                    severity: 'success',
                    summary: 'Successful',
                    detail: 'Product Updated',
                    life: 3000,
                });
            }
        } else {
            // create new
            product.value.id = createId();
            products.value.push({ ...product.value });
            toast.add({
                severity: 'success',
                summary: 'Successful',
                detail: 'Product Created',
                life: 3000,
            });
        }

        productDialog.value = false;
        product.value = {};
    }
};

const editProduct = (prod) => {
    product.value = { ...prod };
    productDialog.value = true;
};

const confirmDeleteProduct = (prod) => {
    product.value = prod;
    deleteProductDialog.value = true;
};

const deleteProduct = () => {
    products.value = products.value.filter((val) => val.id !== product.value.id);
    deleteProductDialog.value = false;
    product.value = {};
    toast.add({
        severity: 'success',
        summary: 'Successful',
        detail: 'Product Deleted',
        life: 3000,
    });
};

const findIndexById = (id) => {
    let index = -1;
    for (let i = 0; i < products.value.length; i++) {
        if (products.value[i].id === id) {
            index = i;
            break;
        }
    }
    return index;
};

const createId = () => {
    let id = '';
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    for (let i = 0; i < 5; i++) {
        id += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return id;
};

const confirmDeleteSelected = () => {
    deleteProductsDialog.value = true;
};

const deleteshowRows = () => {
    products.value = products.value.filter((val) => !showRows.value?.includes(val));
    deleteProductsDialog.value = false;
    showRows.value = null;
    toast.add({
        severity: 'success',
        summary: 'Successful',
        detail: 'Products Deleted',
        life: 3000,
    });
};

const recordsInfo = computed(() => {
    if (!dt.value) return '';

    const total = products.value.length;
    const filtered = dt.value.processedData?.length;

    if (filtered == null) {
        return `${total} z ${total} záznamov`;
    }
    return `${filtered} z ${total} záznamov`;
});

const selectPatient = (row) => {
  patientStore.setPatient(row);
};

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

        <Dialog v-model:visible="productDialog" :style="{ width: '600px' }" header="Výkon" :modal="true">
            <div class="flex flex-col gap-6">

                <!-- Kód -->
                <div>
                    <label :class="['block text-normal mb-1', isEditing ? '!text-lightgrey' : '']">Kód</label>
                    <InputText
                        v-model.trim="product.code"
                        fluid
                        :invalid="submitted && !product.code"
                        :disabled="isEditing"
                        class="disabled:!bg-white disabled:!text-lightgrey disabled:!border-lightgrey disabled:!cursor-not-allowed"

                    />
                    <small v-if="submitted && !product.code" class="text-warning">
                        Kód je povinný.
                    </small>
                </div>

                <!-- Prices -->
                <div class="grid grid-cols-12 gap-4">

                    <div class="col-span-4">
                        <label class="block text-normal mb-1">Cena poisťovňa 25</label>
                        <InputNumber
                            v-model="product.price25"
                            mode="decimal"
                            :minFractionDigits="2"
                            :maxFractionDigits="2"
                            :useGrouping="false"
                            fluid
                            :invalid="submitted && product.price25 == null"
                        />
                        <small v-if="submitted && product.price25 === null" class="text-warning">
                            Povinné pole.
                        </small>
                    </div>

                    <div class="col-span-4">
                        <label class="block text-normal mb-1">Cena poisťovňa 24</label>
                        <InputNumber
                            v-model="product.price24"
                            mode="decimal"
                            :minFractionDigits="2"
                            :maxFractionDigits="2"
                            :useGrouping="false"
                            fluid
                            :invalid="submitted && product.price25 == null"
                        />
                        <small v-if="submitted && product.price24 === null" class="text-warning">
                            Povinné pole.
                        </small>
                    </div>

                    <div class="col-span-4">
                        <label class="block text-normal mb-1">Cena poisťovňa 27</label>
                        <InputNumber
                            v-model="product.price27"
                            mode="decimal"
                            :minFractionDigits="2"
                            :maxFractionDigits="2"
                            :useGrouping="false"
                            fluid
                            :invalid="submitted && product.price25 == null"
                        />
                        <small v-if="submitted && product.price27 === null" class="text-warning">
                            Povinné pole.
                        </small>
                    </div>

                </div>

                <!-- Description -->
                <div>
                    <label :class="['block text-normal mb-1', isEditing ? '!text-lightgrey' : '']">Popis</label>
                    <Textarea
                        v-model.trim="product.description"
                        rows="3"
                        fluid
                        :invalid="submitted && !product.description"
                        :disabled="isEditing"
                        class="disabled:!bg-white disabled:!text-lightgrey disabled:!border-lightgrey disabled:!cursor-not-allowed"

                    />
                    <small v-if="submitted && !product.description" class="text-warning">
                        Popis je povinný.
                    </small>
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
