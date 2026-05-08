<script setup lang="ts">
import { ref, computed, onMounted, watch, watchEffect, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import api from '@/services/api';
import { toApiDate } from '@/utils/dateUtils';
import type { Patient as PatientModel, InsuranceCompany } from '@/types/models';
import { useAuthStore } from '@/stores/auth';
import { useUiOverlayStore } from '@/stores/uiOverlay';
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import useEmailDocumentsDialog from '@/composables/useEmailDocumentsDialog'
import type { DataTableOptions } from '@/types/datatable'

const authStore = useAuthStore();
const uiOverlayStore = useUiOverlayStore();
const toast = useToast();
const { openEmailDocumentsDialog } = useEmailDocumentsDialog()
const branchId = computed(() => authStore.currentBranch?.id ?? null);
const TIMELINE_CALC_TOAST_GROUP = 'timeline-calculation-toast';


type BatchType = {
    code: string;
    name: string;
};

type Insurance = {
    id: number;
    code: string | null;
    name: string;
};

type Patient = {
    id: number;
    name: string;
    personalNumber: string;
};

const router = useRouter();

const batchNumber = ref<string | null>(null);
const batchType = ref<BatchType | null>(null);
const insurance = ref<Insurance | null>(null);
const now = new Date()
const dates = ref<Date | null>(new Date(now.getFullYear(), now.getMonth() - 1, 1));
const allPatients = ref<Patient[]>([]);
const filteredPatients = ref<Patient[]>([]);
const selectedPatients = ref<Patient[]>([]);

const submitted = ref(false);
const loading = ref(false);
const patientsLoading = ref(false);

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value);
});

onBeforeUnmount(() => {
    uiOverlayStore.setContentLoading(false);
});

const batchTypes = ref<BatchType[]>([
    { code: 'N', name: 'Nová dávka' },
    { code: 'E', name: 'Nová dávka občania EU' },
    { code: 'O', name: 'Opravná dávka' },
    { code: 'F', name: 'Opravná dávka občania EU' },
    { code: 'I', name: 'Dávka cudzinci mimo EU, bezdomovci' },
]);

const insurances = ref<Insurance[]>([]);

const shouldShowPatients = computed(() => {
    const code = batchType.value?.code;
    return code === 'O' || code === 'I' || code === 'F';
});

function mapInsuranceCompanyToOption(company: InsuranceCompany): Insurance {
    const displayName = company.name ?? '';

    return {
        id: company.id,
        code: company.code,
        name: displayName ? `${displayName}` : displayName || `#${company.id}`,
    };
}

function mapPatients(items: PatientModel[]): Patient[] {
    return items.map(p => ({
        id: p.id,
        name: `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim(),
        personalNumber: p.personal_number ?? '',
    }));
}

async function loadInsurances() {
    try {
        const res = await api.get('/v1/insurance-companies', {
            params: { paginate: 0 },
        })

        const payload = res.data?.data

        const items =
            (payload?.items as InsuranceCompany[] | undefined) ??
            (Array.isArray(payload) ? (payload as InsuranceCompany[]) : []) ??
            []

        insurances.value = items.map(mapInsuranceCompanyToOption)
    } catch (e) {
        console.error('Failed to load insurance companies', e)
        insurances.value = []
    }
}


async function loadAllPatients() {
    const id = branchId.value;

    try {
        patientsLoading.value = true;

        const res = await api.get(`/v1/branches/${id}/patients`, {
            params: {
                paginate: 0,
            },
        });

        const data = res.data?.data;
        const items = ((Array.isArray(data) ? data : data?.items) as PatientModel[]) ?? [];

        allPatients.value = mapPatients(items);
    } catch (e) {
        console.error('Failed to load patients', e);
        allPatients.value = [];
    } finally {
        patientsLoading.value = false;
    }
}



function searchPatients(event: { query: string }) {
    const q = (event.query ?? '').toLowerCase().trim();

    if (!q) {
        filteredPatients.value = [];
        return;
    }

    filteredPatients.value = allPatients.value.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.personalNumber.toLowerCase().includes(q),
    );
}

function removePatient(patient: Patient) {
    selectedPatients.value = selectedPatients.value.filter(
        (p) => p.id !== patient.id,
    );
}

function onBatchNumberKeydown(e: KeyboardEvent) {
    const allowedKeys = [
        'Backspace',
        'Delete',
        'ArrowLeft',
        'ArrowRight',
        'Tab'
    ]

    if (allowedKeys.includes(e.key)) {
        return
    }

    if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault()
    }
}

async function pollCalculationStatus(periodFrom: Date) {
    const maxAttempts = 120; // 10 minutes with 5 second interval
    let attempts = 0;

    const monthStr = toApiDate(periodFrom);
    const branchId = authStore.currentBranch?.id;
    const userId = authStore.user?.id;

    await new Promise(resolve => setTimeout(resolve, 500));

    const interval = setInterval(async () => {
        attempts++;

        try {
            const res = await api.get('/v1/visits/timeline/status', {
                params: {
                    month: monthStr,
                    branch_id: branchId,
                    user_id: userId,
                },
            });

            const status = res.data?.data?.status;

            if (status === 'completed') {
                clearInterval(interval);
                toast.removeGroup(TIMELINE_CALC_TOAST_GROUP);
                toast.add({
                    severity: 'success',
                    summary: 'Výpočet dokončený',
                    detail: 'Časová os návštev bola úspešne vypočítaná.',
                    life: 5000,
                });
            } else if (status === 'failed') {
                clearInterval(interval);
                toast.removeGroup(TIMELINE_CALC_TOAST_GROUP);
                const errorMsg = res.data?.data?.error_message || 'Neznáma chyba';
                toast.add({
                    severity: 'error',
                    summary: 'Chyba výpočtu',
                    detail: errorMsg,
                    life: 5000,
                });
            } else if (attempts >= maxAttempts) {
                clearInterval(interval);
                toast.removeGroup(TIMELINE_CALC_TOAST_GROUP);
                toast.add({
                    severity: 'warn',
                    summary: 'Časový limit',
                    detail: 'Výpočet trvá dlhšie ako obvykle. Pokračuje na pozadí.',
                    life: 5000,
                });
            }
        } catch (error) {
            console.error('Failed to check calculation status:', error);
            if (attempts >= maxAttempts) {
                clearInterval(interval);
                toast.removeGroup(TIMELINE_CALC_TOAST_GROUP);
            }
        }
    }, 5000); // Check every 5 seconds
}

async function onSubmit() {
    submitted.value = true;

    const hasPeriod = !!dates.value;
    const needsPatients = shouldShowPatients.value;

    if (
        !batchNumber.value ||
        !batchType.value ||
        !insurance.value ||
        !hasPeriod ||
        (needsPatients && !selectedPatients.value.length)
    ) {
        return;
    }

    if (!dates.value) {
        return;
    }

    const monthDate = dates.value as Date;
    const year = monthDate.getFullYear();
    const month = monthDate.getMonth();
    const periodFrom = new Date(year, month, 1);
    const periodTo = new Date(year, month + 1, 0);

    loading.value = true;

    try {
        const res = await api.post('/v1/batches/points/preview', {
            batchNumber: batchNumber.value,
            batchType: { code: batchType.value.code },
            insurance: { id: insurance.value.id },
            period: [periodFrom.toISOString(), periodTo.toISOString()],
            user: { id: authStore.user?.id },
            branch: { id: authStore.currentBranch?.id },
            company: { id: authStore.currentBranch?.company_id },
            patients: selectedPatients.value.map(p => ({ id: p.id })),
        });

        const sheet = res.data?.data?.sheet;

        if (!sheet) {
            console.error('Missing sheet in response:', res.data);
            return;
        }

        api.post('/v1/visits/timeline', {
            month: toApiDate(periodFrom),
            branch_id: authStore.currentBranch?.id,
            user_id: authStore.user?.id,
            persist: true,
        })
            .then(() => {
                toast.removeGroup(TIMELINE_CALC_TOAST_GROUP);
                toast.add({
                    group: TIMELINE_CALC_TOAST_GROUP,
                    severity: 'info',
                    summary: 'Prebieha výpočet časovej osi.',
                    detail: 'Generovanie dopravných dávok a dekurzov pacientov je počas výpočtu nedostupné, keďže závisí od jeho výsledku.',
                    life: 0,
                    closable: false,
                });

                pollCalculationStatus(periodFrom);
            })
            .catch(error => {
                console.error('Background calculation failed:', error);
                toast.removeGroup(TIMELINE_CALC_TOAST_GROUP);
                toast.add({
                    severity: 'warn',
                    summary: 'Upozornenie',
                    detail: 'Výpočet časovej osi návštev nebol spustený.',
                    life: 5000,
                });
            });

        await router.push({
            path: '/documents/points',
            query: {
                batchNumber: sheet.batchNumber,
                fileName: sheet.fileName,
                amount: sheet.amount,
                periodFrom: sheet.periodFrom,
                periodTo: sheet.periodTo,
                performedBy: sheet.performedBy,
                performedDate: sheet.performedDate,
                companyName: sheet.companyName,
                branchName: sheet.branchName,
                insuranceId: insurance.value.id,
                batchTypeCode: batchType.value.code,
                period0: periodFrom.toISOString(),
                period1: periodTo.toISOString(),
                insuranceName: insurance.value.name,
                patientIds: JSON.stringify(sheet.patients ?? []),
            },
        });
    } catch (error) {
        console.error('Preview or navigation failed', error);
    } finally {
        loading.value = false;
    }
}

watch(branchId, (id) => {
    if (!id) return;
    loadAllPatients();
}, { immediate: true });

onMounted(() => {
    loadInsurances();
});

type DocRow = {
    id: number
    name: string
    type: string
    subtype?: 'N' | 'O' | string
    period?: string
    created_at?: string
    insurance_company_name?: string
}

const batchTypeLabelByCode = computed<Record<string, string>>(() => {
    const map: Record<string, string> = {}
    for (const t of batchTypes.value) map[t.code] = t.name
    return map
})

const formatSubtype = (code?: string) => {
    if (!code) return ''
    return batchTypeLabelByCode.value[code] ?? code
}

const formatDateWithTime = (dateStr?: string) => {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    const datePart = date.toLocaleDateString('sk-SK')
    const timePart = date.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
    return `${datePart} ${timePart}`
}

const openPointsDoc = (doc: DocRow) => {
    const url = router.resolve(`/documents/points/${doc.id}`).href
    window.open(url, '_blank', 'noopener,noreferrer')
}

const options = computed<DataTableOptions<DocRow>>(() => ({
    rowKey: 'id',
    endpointUrl: 'v1/points-batches',
    extraParams: {
        ...(branchId.value ? { branch_id: branchId.value } : {}),
    },
    dateRangeFilter: {
        mode: 'single',
        param: 'period',
        view: 'month',
        dateFormat: 'mm/yy',
        value: dates.value,
    },
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,

    columns: [
        {
            field: 'name',
            header: 'Číslo dávky',
            sortable: true,
            render: (v?: string) => {
                if (!v) return ''
                const parts = v.split('_')
                return parts[3] ?? ''
            }
        },
        {
            field: 'insurance_company_name',
            header: 'Poisťovňa',
            sortable: true,
            render: (v?: string) => {
                if (!v) return ''
                return v.trim().split(/\s+/)[0] ?? ''
            }
        },
        {
            field: 'subtype',
            header: 'Druh dávky',
            sortable: true,
            render: (v?: string) => formatSubtype(v),
        },
        { field: 'period', header: 'Obdobie', sortable: true },
        {
            field: 'updated_at',
            header: 'Naposledy upravené',
            sortable: true,
            render: (v?: string) => formatDateWithTime(v),
        },
        {
            field: 'preview',
            header: '',
            width: '3rem',
            component: ActionButtons,
            componentOptions: [
                {
                    icon: 'bi bi-eye',
                    color: 'info',
                    tooltip: 'Zobraziť',
                    action: (row: DocRow) => openPointsDoc(row),
                },
            ],
        },
    ],

    actions: [
        {
            key: 'email',
            disabled: ({ selectedRows }: { selectedRows: DocRow[] }) => selectedRows.length === 0,
            icon: 'bi bi-send',
            class: 'bg-accent!',
            tooltip: 'Poslať vybrané dokumenty emailom',
            handler: async ({ selectedRows, remote }: { selectedRows: DocRow[]; remote: any }) => {
                await openEmailDocumentsDialog({
                    documents: selectedRows,
                    remote,
                })
            },
        },
        {
            key: 'delete',
            disabled: ({ selectedRows }: { selectedRows: DocRow[] }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            confirm: 'Naozaj chcete zmazať vybrané dokumenty?',
            handler: async ({ selectedRows, remote }: { selectedRows: DocRow[]; remote: any }) => {
                await api.delete('/v1/documents', { data: { ids: selectedRows.map(r => r.id) } })
                await remote.loadPage(remote.page)
            },
        },
    ],
}))


</script>


<template>
    <div class="flex flex-col gap-6 relative">
        <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
            <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
                <div class="grid grid-cols-12 gap-4">
                    <!-- Číslo dávky -->
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-normal mb-1">Číslo dávky</label>
                        <InputText v-model="batchNumber" @keydown="onBatchNumberKeydown" maxlength="6"
                            inputmode="numeric"
                            inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                            class="border-none!" fluid />
                        <small v-if="submitted && !batchNumber" class="text-danger">
                            Číslo dávky je povinné.
                        </small>
                    </div>

                    <!-- Typ dávky -->
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-normal mb-1">Typ dávky</label>
                        <Select v-model="batchType" :options="batchTypes" optionLabel="name" fluid
                            class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!" />
                        <small v-if="submitted && !batchType" class="text-danger">
                            Typ dávky je povinný.
                        </small>
                    </div>

                    <!-- Poisťovňa -->
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-normal mb-1">Poisťovňa</label>
                        <Select v-model="insurance" :options="insurances" optionLabel="name" fluid
                            class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!" />
                        <small v-if="submitted && !insurance" class="text-danger">
                            Poisťovňa je povinná.
                        </small>
                    </div>

                    <!-- Obdobie (month) -->
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-normal mb-1">Obdobie</label>
                        <DatePicker v-model="dates" view="month" dateFormat="MM yy" :manualInput="false"
                            inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
                            fluid />

                        <small v-if="submitted && !dates" class="text-danger">
                            Obdobie je povinné.
                        </small>
                    </div>

                    <!-- Pacienti pre opravnu dávku -->
                    <div v-if="shouldShowPatients" class="col-span-12">
                        <label class="block text-normal mb-2">
                            Vyhľadajte pacienta
                        </label>

                        <AutoComplete v-model="selectedPatients" :suggestions="filteredPatients" multiple
                            optionLabel="name" :minLength="1" @complete="searchPatients" :loading="patientsLoading"
                            fluid class="w-full">
                            <!-- suggestion option -->
                            <template #option="slotProps">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-normal text-darkgrey">
                                        {{ slotProps.option.name }}
                                    </span>
                                    <span class="bg-darkgrey rounded-md text-mini text-white px-2 py-0.5">
                                        {{ slotProps.option.personalNumber }}
                                    </span>
                                </div>
                            </template>

                            <!-- chip template -->
                            <template #chip="slotProps">
                                <div class="
                    inline-flex items-center gap-2
                    bg-darkgrey text-lightgrey
                    px-3 py-1 rounded-md
                    text-xs sm:text-sm
                  ">
                                    <span class="pr-2 border-r border-lightgrey truncate max-w-[8rem] sm:max-w-[10rem]">
                                        {{ slotProps.value.name }}
                                    </span>
                                    <span class="px-1 sm:px-2 whitespace-nowrap">
                                        {{ slotProps.value.personalNumber }}
                                    </span>
                                    <i class="bi bi-x-lg cursor-pointer text-[0.6rem] sm:text-[0.7rem]"
                                        @click.stop="removePatient(slotProps.value)"></i>
                                </div>
                            </template>
                        </AutoComplete>

                        <small v-if="submitted && shouldShowPatients && !selectedPatients.length"
                            class="text-danger block mt-1">
                            Pri tomto type pacienta je potrebné vybrať aspoň jedného pacienta.
                        </small>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <Button type="submit"
                    class="bg-accent! border-0! hover:bg-darkgrey! px-4! rounded-md! text-white! text-normal! h-7!">
                    Vytvoriť dávku
                </Button>
            </div>
        </form>

        <section>
            <UniversalDataTable ref="tableRef" :options="options" />
        </section>
    </div>
</template>
