<script setup>
import { ref, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const dt = ref(null);
const isEditing = computed(() => !!product.value.id)

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
    {
        id: 4,
        code: '174829',
        price25: '174829',
        price24: '174829',
        price27: '174829',
        description: 'Random description 4',
    },
    {
        id: 5,
        code: '285736',
        price25: '285736',
        price24: '285736',
        price27: '285736',
        description: 'Random description 5',
    },
    {
        id: 6,
        code: '396154',
        price25: '396154',
        price24: '396154',
        price27: '396154',
        description: 'Random description 6',
    },
    {
        id: 7,
        code: '417263',
        price25: '417263',
        price24: '417263',
        price27: '417263',
        description: 'Random description 7',
    },
    {
        id: 8,
        code: '528971',
        price25: '528971',
        price24: '528971',
        price27: '528971',
        description: 'Random description 8',
    },
    {
        id: 9,
        code: '639845',
        price25: '639845',
        price24: '639845',
        price27: '639845',
        description: 'Random description 9',
    },
    {
        id: 10,
        code: '741236',
        price25: '741236',
        price24: '741236',
        price27: '741236',
        description: 'Random description 10',
    },
    {
        id: 11,
        code: '852147',
        price25: '852147',
        price24: '852147',
        price27: '852147',
        description: 'Random description 11',
    },
    {
        id: 12,
        code: '963741',
        price25: '963741',
        price24: '963741',
        price27: '963741',
        description: 'Random description 12',
    },
    {
        id: 13,
        code: '184957',
        price25: '184957',
        price24: '184957',
        price27: '184957',
        description: 'Random description 13',
    },
    {
        id: 14,
        code: '295864',
        price25: '295864',
        price24: '295864',
        price27: '295864',
        description: 'Random description 14',
    },
    {
        id: 15,
        code: '316479',
        price25: '316479',
        price24: '316479',
        price27: '316479',
        description: 'Random description 15',
    },
    {
        id: 16,
        code: '427158',
        price25: '427158',
        price24: '427158',
        price27: '427158',
        description: 'Random description 16',
    },
    {
        id: 17,
        code: '538296',
        price25: '538296',
        price24: '538296',
        price27: '538296',
        description: 'Random description 17',
    },
    {
        id: 18,
        code: '649713',
        price25: '649713',
        price24: '649713',
        price27: '649713',
        description: 'Random description 18',
    },
    {
        id: 19,
        code: '751894',
        price25: '751894',
        price24: '751894',
        price27: '751894',
        description: 'Random description 19',
    },
    {
        id: 20,
        code: '862531',
        price25: '862531',
        price24: '862531',
        price27: '862531',
        description: 'Random description 20',
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
            <Column field="code" header="Kód" sortable />
            <Column field="price25" header="Cena poisťovňa 25" sortable />
            <Column field="price24" header="Cena poisťovňa 24" sortable disabled />
            <Column field="price27" header="Cena poisťovňa 27" sortable />
            <Column field="description" header="Popis" />
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
