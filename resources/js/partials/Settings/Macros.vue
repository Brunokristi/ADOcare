<script setup>
import { ref, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const dt = ref(null);
const isEditing = computed(() => !!product.value.id)

const rows = ref([
    { id: 1, name: 'Malá rana', abbreviation: 'MR', text: 'Bla bla bla' },
    { id: 2, name: 'Veľká rana', abbreviation: 'VR', text: 'Silný zásah spôsobujúci poškodenie tkaniva.' },
    { id: 3, name: 'Povrchové zranenie', abbreviation: 'PZ', text: 'Ľahké poranenie pokožky bez výrazného krvácania.' },
    { id: 4, name: 'Hlbočná rana', abbreviation: 'HR', text: 'Poranenie zasahujúce hlbšie vrstvy tkaniva.' },
    { id: 5, name: 'Roztrhnuté tkanivo', abbreviation: 'RT', text: 'Nerovnomerné roztrhnutie spôsobené ostrým predmetom.' },
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

</script>

<template>
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
            <Column field="name" header="Názov" sortable />
            <Column field="abbreviation" header="Skratka" sortable />
            <Column field="text" header="Text" sortable disabled />
            <Column :exportable="false" style="width: 3rem">
                <template #body="slotProps">
                    <Button icon="bi bi-pencil" @click="editProduct(slotProps.data)" variant="text" class="text-darkgrey! hover:bg-transparent! p-0!" />
                </template>
            </Column>
        </DataTable>

        <div class="text-mini text-accent flex justify-end w-full py-2">
            {{ recordsInfo }}
        </div>

        <Dialog v-model:visible="productDialog" :style="{ width: '600px' }" header="Makro" :modal="true">
            <div class="flex flex-col gap-6">

                <!-- Prices -->
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-6">
                        <label :class="['block text-normal mb-1']">Názov</label>
                    <InputText
                        v-model.trim="product.code"
                        fluid
                        :invalid="submitted && !product.code"
                        class="disabled:!bg-white disabled:!text-lightgrey disabled:!border-lightgrey disabled:!cursor-not-allowed"

                    />
                    <small v-if="submitted && !product.code" class="text-warning">
                        Kód je povinný.
                    </small>
                    </div>

                    <div class="col-span-6">
                        <label class="block text-normal mb-1">Skratka</label>
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

                </div>

                <!-- Description -->
                <div>
                    <label :class="['block text-normal mb-1']">Popis</label>
                    <Textarea
                        v-model.trim="product.description"
                        rows="3"
                        fluid
                        :invalid="submitted && !product.description"
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
