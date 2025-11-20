<script setup>
import { ref, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const dt = ref(null);

/**
 * Initial data
 */
const rows = ref([
    {
        id: 1,
        code: '713482',
        price25: '713482',
        price24: '713482',
        price27: '713482',
        description: 'Bla bla bla',
    },
    {
        id: 2,
        code: '852369',
        price25: '852369',
        price24: '852369',
        price27: '852369',
        description: 'Dalsi bla bla bla',
    },
    {
        id: 3,
        code: '963258',
        price25: '963258',
        price24: '963258',
        price27: '963258',
        description: 'Este dalsi bla bla bla',
    },
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

        <Toolbar class="!bg-transparent !border-0 !p-0 !py-3 !shadow-none flex items-center justify-between">
            <template #end>
                <div class="flex items-center gap-2 ">
                    <IconField>
                        <InputText v-model="filters['global'].value"  />
                        <InputIcon>
                            <i class="bi bi-search text-darkgrey" />
                        </InputIcon>
                    </IconField>

                    <Button icon="bi bi-plus" @click="openNew" class="!bg-accent !border-accent hover:!bg-darkgrey hover:!border-darkgrey"/>

                    <Button
                        icon="bi bi-eraser"
                        @click="confirmDeleteSelected"
                        :disabled="!showRows || !showRows.length"
                        class="!bg-warning !border-warning"
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
        >
            <Column selectionMode="multiple" style="width: 3rem" :exportable="false" />
            
            <Column field="code" header="Kód" sortable />
            <Column field="price25" header="Cena poisťovňa 25" sortable />
            <Column field="price24" header="Cena poisťovňa 24" sortable disabled />
            <Column field="price27" header="Cena poisťovňa 27" sortable />
            <Column field="description" header="Popis" />
            <Column :exportable="false" style="width: 3rem">
                <template #body="slotProps">
                    <Button icon="bi bi-pencil" @click="editProduct(slotProps.data)" variant="text" class="!text-darkgrey hover:!bg-transparent " />
                </template>
            </Column>
        </DataTable>

        <div class="text-mini text-accent flex justify-end w-full py-2">
            {{ recordsInfo }}
        </div>

        <Dialog v-model:visible="productDialog" :style="{ width: '450px' }" header="Product Details" :modal="true">
            <div class="flex flex-col gap-6">
                <div>
                    <label for="code" class="block font-bold mb-3">Code</label>
                    <InputText
                        id="code"
                        v-model.trim="product.code"
                        required="true"
                        autofocus
                        :invalid="submitted && !product.code"
                        fluid
                    />
                    <small v-if="submitted && !product.code" class="text-red-500">Code is required.</small>
                </div>

                <div>
                    <label for="description" class="block font-bold mb-3">Description</label>
                    <Textarea
                        id="description"
                        v-model="product.description"
                        rows="3"
                        cols="20"
                        fluid
                    />
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-4">
                        <label for="price25" class="block font-bold mb-3">Price 25</label>
                        <InputNumber
                            id="price25"
                            v-model="product.price25"
                            :useGrouping="false"
                            integeronly
                            fluid
                        />
                    </div>
                    <div class="col-span-4">
                        <label for="price24" class="block font-bold mb-3">Price 24</label>
                        <InputNumber
                            id="price24"
                            v-model="product.price24"
                            :useGrouping="false"
                            integeronly
                            fluid
                        />
                    </div>
                    <div class="col-span-4">
                        <label for="price27" class="block font-bold mb-3">Price 27</label>
                        <InputNumber
                            id="price27"
                            v-model="product.price27"
                            :useGrouping="false"
                            integeronly
                            fluid
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Save" icon="pi pi-check" @click="saveProduct" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteProductDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="product">
                    Are you sure you want to delete
                    <b>{{ product.code }}</b>?
                </span>
            </div>
            <template #footer>
                <Button
                    label="No"
                    icon="pi pi-times"
                    text
                    @click="deleteProductDialog = false"
                    severity="secondary"
                    variant="text"
                />
                <Button label="Yes" icon="pi pi-check" @click="deleteProduct" severity="danger" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteProductsDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span>Are you sure you want to delete the selected products?</span>
            </div>
            <template #footer>
                <Button
                    label="No"
                    icon="pi pi-times"
                    text
                    @click="deleteProductsDialog = false"
                    severity="secondary"
                    variant="text"
                />
                <Button
                    label="Yes"
                    icon="pi pi-check"
                    text
                    @click="deleteshowRows"
                    severity="danger"
                />
            </template>
        </Dialog>
    </div>
</template>
