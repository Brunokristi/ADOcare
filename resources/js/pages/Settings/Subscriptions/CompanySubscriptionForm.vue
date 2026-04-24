<script setup lang="ts">
import { computed, markRaw, onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import type { DataTableOptions } from '@/types/datatable'
import api from '@/services/api'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'

interface SubscriptionTier {
    id: number
    name: string
    price_monthly: number | null
    users_limit: number | null
    is_active: boolean
}

interface CompanySubscription {
    id: number
    name: string
    subscription_tier_id: number | null
    subscription_price_monthly: number | null
    subscription_users_limit_override: number | null
    subscription_status: 'active' | 'trial' | 'paused' | 'cancelled'
    subscription_notes: string | null
}

interface PaymentRow {
    id: number
    received_at: Date | null
    amount: number | null
    notes: string | null
}

interface PaymentDraft {
    received_at: Date | null
    amount: number | null
    notes: string | null
}

interface CompanySubscriptionDetails extends CompanySubscription {
    payments: Array<{
        received_at: string | null
        amount: number | null
        notes: string | null
    }>
    paid_months_by_year: Array<{
        year: number
        months: number[]
    }>
}

const props = defineProps<{
    company: CompanySubscription
    modalResolve?: (value?: any) => void
}>()

const emit = defineEmits(['save', 'close'])
const toast = useToast()

const saving = ref(false)
const loadingDetails = ref(false)
const loadingTiers = ref(false)
const tiers = ref<SubscriptionTier[]>([])
const selectedYear = ref<number>(new Date().getFullYear())
const selectedMonths = ref<number[]>([])
const payments = ref<PaymentRow[]>([])
const paidMonthsByYear = ref<Array<{ year: number; months: number[] }>>([])
const nextPaymentId = ref(1)
const paymentDialogVisible = ref(false)
const editingPaymentIndex = ref<number | null>(null)
const paymentDraft = ref<PaymentDraft>({
    received_at: new Date(),
    amount: null,
    notes: null,
})

function toDateOrNull(value: string | null) {
    if (!value) return null
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? null : date
}

function toApiDate(value: Date | null) {
    if (!value) return null
    return value.toISOString().slice(0, 10)
}

function makePaymentRow(data: { received_at: Date | null; amount: number | null; notes: string | null }): PaymentRow {
    const row: PaymentRow = {
        id: nextPaymentId.value++,
        received_at: data.received_at,
        amount: data.amount,
        notes: data.notes,
    }

    return row
}

const statusOptions = [
    { label: 'Aktívne', value: 'active' },
    { label: 'Trial', value: 'trial' },
    { label: 'Pozastavené', value: 'paused' },
    { label: 'Zrušené', value: 'cancelled' },
]

const monthOptions = [
    { label: 'Január', value: 1 },
    { label: 'Február', value: 2 },
    { label: 'Marec', value: 3 },
    { label: 'Apríl', value: 4 },
    { label: 'Máj', value: 5 },
    { label: 'Jún', value: 6 },
    { label: 'Júl', value: 7 },
    { label: 'August', value: 8 },
    { label: 'September', value: 9 },
    { label: 'Október', value: 10 },
    { label: 'November', value: 11 },
    { label: 'December', value: 12 },
]

const local = ref<CompanySubscription>({
    ...props.company,
})

const selectedTier = computed(() => {
    return tiers.value.find((tier) => tier.id === local.value.subscription_tier_id) || null
})

const effectiveMonthlyPrice = computed(() => {
    if (local.value.subscription_price_monthly !== null && local.value.subscription_price_monthly !== undefined) {
        return local.value.subscription_price_monthly
    }

    return selectedTier.value?.price_monthly ?? null
})

const effectiveUsersLimit = computed(() => {
    if (
        local.value.subscription_users_limit_override !== null &&
        local.value.subscription_users_limit_override !== undefined
    ) {
        return local.value.subscription_users_limit_override
    }

    return selectedTier.value?.users_limit ?? null
})

const selectedStatusLabel = computed(() => {
    return statusOptions.find((item) => item.value === local.value.subscription_status)?.label ?? '—'
})

const yearOptions = computed(() => {
    const current = new Date().getFullYear()
    const years = new Set<number>([current, current - 1, current - 2, current + 1])

    paidMonthsByYear.value.forEach((item) => years.add(item.year))

    return Array.from(years)
        .sort((a, b) => b - a)
        .map((year) => ({ label: String(year), value: year }))
})

onMounted(async () => {
    loadingTiers.value = true
    loadingDetails.value = true

    try {
        const [tiersData, details] = await Promise.all([
            api.fetchEntities<SubscriptionTier>('v1/subscription-tiers', {
                filter: { is_active: 1 },
                sort: 'sort_order,name',
            }),
            api.fetchEntity<CompanySubscriptionDetails>(`v1/companies/${props.company.id}/subscription-details`),
        ])

        tiers.value = tiersData

        local.value = {
            id: details.id,
            name: details.name,
            subscription_tier_id: details.subscription_tier_id,
            subscription_price_monthly: details.subscription_price_monthly,
            subscription_users_limit_override: details.subscription_users_limit_override,
            subscription_status: details.subscription_status,
            subscription_notes: details.subscription_notes,
        }

        paidMonthsByYear.value = details.paid_months_by_year ?? []

        const selected = paidMonthsByYear.value.find((x) => Number(x.year) === Number(selectedYear.value))
        selectedMonths.value = selected ? [...selected.months] : []

        payments.value = (details.payments ?? []).map((payment) => makePaymentRow({
            received_at: toDateOrNull(payment.received_at),
            amount: payment.amount,
            notes: payment.notes,
        }))

        if (payments.value.length === 0) {
            payments.value.push(makePaymentRow({
                received_at: new Date(),
                amount: null,
                notes: null,
            }))
        }
    } catch (error) {
        console.error(error)
        tiers.value = []
    } finally {
        loadingTiers.value = false
        loadingDetails.value = false
    }
})

function onYearChange() {
    const selected = paidMonthsByYear.value.find((x) => Number(x.year) === Number(selectedYear.value))
    selectedMonths.value = selected ? [...selected.months] : []
}

function toggleMonth(month: number) {
    if (selectedMonths.value.includes(month)) {
        selectedMonths.value = selectedMonths.value.filter((item) => item !== month)
        return
    }

    selectedMonths.value = [...selectedMonths.value, month].sort((a, b) => a - b)
}

function addPaymentRow() {
    editingPaymentIndex.value = null
    paymentDraft.value = {
        received_at: new Date(),
        amount: null,
        notes: null,
    }
    paymentDialogVisible.value = true
}

function removeSelectedPayments(selected: PaymentRow[]) {
    if (!selected.length) return

    const selectedIds = new Set(selected.map((row) => row.id))
    const remaining = payments.value.filter((row) => !selectedIds.has(row.id))

    if (remaining.length === 0) {
        payments.value = [makePaymentRow({
            received_at: new Date(),
            amount: null,
            notes: null,
        })]
        return
    }

    payments.value = remaining
}

function editPaymentRowById(id: number) {
    const index = payments.value.findIndex((item) => item.id === id)
    const payment = payments.value[index]
    if (!payment) return

    editingPaymentIndex.value = index
    paymentDraft.value = {
        received_at: payment.received_at,
        amount: payment.amount,
        notes: payment.notes,
    }
    paymentDialogVisible.value = true
}

function savePaymentDraft() {
    if (!paymentDraft.value.received_at || paymentDraft.value.amount === null) return

    if (editingPaymentIndex.value === null) {
        payments.value.push(makePaymentRow({
            received_at: paymentDraft.value.received_at,
            amount: paymentDraft.value.amount,
            notes: paymentDraft.value.notes,
        }))
    } else {
        const existing = payments.value[editingPaymentIndex.value]
        if (!existing) return

        payments.value.splice(editingPaymentIndex.value, 1, {
            id: existing.id,
            received_at: paymentDraft.value.received_at,
            amount: paymentDraft.value.amount,
            notes: paymentDraft.value.notes,
        })
    }

    paymentDialogVisible.value = false
}

function closePaymentDialog() {
    paymentDialogVisible.value = false
}

const paymentTableOptions = computed<DataTableOptions<PaymentRow>>(() => ({
    rowKey: 'id',
    endpointUrl: '',
    localItems: payments.value,
    defaultPageSize: 10,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    actions: [
        {
            key: 'delete-payment',
            label: '',
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            tooltip: 'Odstrániť vybrané platby',
            confirm: 'Naozaj chcete odstrániť vybrané platby?',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            handler: ({ selectedRows }) => removeSelectedPayments(selectedRows as PaymentRow[]),
        },
        {
            key: 'add-payment',
            label: '',
            icon: 'bi bi-plus',
            tooltip: 'Pridať platbu',
            class: 'bg-accent!',
            handler: () => addPaymentRow(),
        },
    ],
    columns: [
        {
            field: 'received_at',
            header: 'Dátum prijatia',
            width: '28%',
            sortable: true,
            render: (value) => {
                const date = value as Date | null
                return date ? date.toLocaleDateString('sk-SK') : '—'
            },
        },
        {
            field: 'amount',
            header: 'Suma (EUR)',
            width: '22%',
            sortable: true,
            render: (value) => formatCurrency(value as number | null),
        },
        {
            field: 'notes',
            header: 'Poznámka',
            width: '42%',
            render: (value) => (value ? String(value) : '—'),
        },
        {
            field: 'edit',
            header: '',
            width: '3rem',
            component: markRaw(ActionButtons),
            componentOptions: [
                {
                    color: 'info',
                    icon: 'bi bi-pencil',
                    tooltip: 'Upraviť platbu',
                    action: (row: PaymentRow) => editPaymentRowById(row.id),
                },
            ],
        },
    ],
}))

function formatCurrency(value: number | null) {
    if (value === null || value === undefined) return '—'
    return `${Number(value).toFixed(2)} €`
}

function close() {
    if (props.modalResolve) {
        try {
            props.modalResolve(undefined)
        } catch {}
    } else {
        emit('close')
    }
}

async function save() {
    saving.value = true

    try {
        await api.put(`/v1/companies/${local.value.id}/subscription`, {
            subscription_tier_id: local.value.subscription_tier_id,
            subscription_price_monthly: local.value.subscription_price_monthly,
            subscription_users_limit_override: local.value.subscription_users_limit_override,
            subscription_status: local.value.subscription_status,
            subscription_notes: local.value.subscription_notes,
            paid_months_year: selectedYear.value,
            paid_months: [...selectedMonths.value].sort((a, b) => a - b),
            payments: payments.value
                .filter((p) => p.received_at && p.amount !== null)
                .map((p) => ({
                    received_at: toApiDate(p.received_at),
                    amount: p.amount,
                    notes: p.notes,
                })),
        })

        toast.add({
            severity: 'success',
            summary: 'Uložené',
            detail: 'Predplatné bolo aktualizované.',
            life: 2500,
        })

        if (props.modalResolve) {
            props.modalResolve({ changed: true })
        } else {
            emit('save')
        }
    } catch (error) {
        console.error(error)
        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Nepodarilo sa uložiť predplatné.',
            life: 3500,
        })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="flex flex-col gap-5 p-2">
        <h1 class="text-heading-accent">{{ local.name }}</h1>
        <section class="">
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 md:col-span-6 xl:col-span-3">
                    <div class="rounded-md bg-darkgrey p-4 h-full">
                        <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Balík</div>
                        <div class="text-heading text-white">{{ selectedTier?.name || 'Bez balíka' }}</div>
                    </div>
                </div>

                <div class="col-span-12 md:col-span-6 xl:col-span-3">
                    <div class="rounded-md bg-darkgrey p-4 h-full">
                        <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Používatelia</div>
                        <div class="text-heading text-white">{{ effectiveUsersLimit ?? 'Neobmedzene' }}</div>
                    </div>
                </div>

                <div class="col-span-12 md:col-span-6 xl:col-span-3">
                    <div class="rounded-md bg-darkgrey p-4 h-full">
                        <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Cena / mesiac</div>
                        <div class="text-heading text-white">{{ formatCurrency(effectiveMonthlyPrice) }}</div>
                    </div>
                </div>

                <div class="col-span-12 md:col-span-6 xl:col-span-3">
                    <div class="rounded-md bg-darkgrey p-4 h-full">
                        <div class="text-mini uppercase tracking-wide text-lightgrey mb-2">Stav</div>
                        <div class="text-heading text-white">{{ selectedStatusLabel }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-tag3 rounded-md p-5">
            <div class="mb-4">
                <h3 class="text-sm text-accent">Nastavenie predplatného</h3>
            </div>

            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 md:col-span-6">
                    <label class="block text-normal mb-1">Balík</label>
                    <Select
                        v-model="local.subscription_tier_id"
                        :options="tiers"
                        optionLabel="name"
                        optionValue="id"
                        :loading="loadingTiers"
                        placeholder="Vyber balík"
                        showClear
                        fluid
                        class="border-0!"
                    />
                </div>

                <div class="col-span-12 md:col-span-6">
                    <label class="block text-normal mb-1">Stav predplatného</label>
                    <Select
                        v-model="local.subscription_status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        fluid
                        class="border-0!"
                    />
                </div>
            </div>
        </section>

        <section class="bg-tag3 rounded-md p-5">
            <div class="mb-4">
                <h3 class="text-sm text-accent">Aktívne mesiace</h3>
            </div>

            <div class="grid grid-cols-12 gap-4 mb-4">
                <div class="col-span-12 md:col-span-4">
                    <label class="block text-normal mb-1">Rok</label>
                    <Select
                        v-model="selectedYear"
                        :options="yearOptions"
                        optionLabel="label"
                        optionValue="value"
                        :disabled="loadingDetails"
                        @change="onYearChange"
                        fluid
                        class="border-0!"
                    />
                </div>
            </div>

            <div class="grid grid-cols-12 gap-3">
                <div
                    v-for="month in monthOptions"
                    :key="month.value"
                    class="col-span-6 md:col-span-4 xl:col-span-2"
                >
                    <button
                        type="button"
                        class="w-full rounded-md p-3 text-left transition-colors cursor-pointer"
                        :class="selectedMonths.includes(month.value)
                            ? 'bg-accent text-white border-accent'
                            : 'bg-white text-darkgrey border-darkgrey hover:bg-accent hover:text-white'"
                        @click="toggleMonth(month.value)"
                    >
                        <div class="text-normal">{{ month.label }}</div>
                    </button>
                </div>
            </div>
        </section>

        <section class="bg-tag3 rounded-md p-5">
            <div class="mb-4">
                <h3 class="text-sm text-accent">História platieb</h3>
            </div>

            <UniversalDataTable :options="paymentTableOptions" />

            <Dialog
                v-model:visible="paymentDialogVisible"
                modal
                :draggable="false"
                :style="{ width: 'min(640px, 92vw)' }"
                :header="editingPaymentIndex === null ? 'Pridať platbu' : 'Upraviť platbu'"
            >
                <div class="grid grid-cols-12 gap-4 pt-2">
                    <div class="col-span-12 md:col-span-6">
                        <label class="block text-normal mb-1">Dátum prijatia</label>
                        <DatePicker
                            v-model="paymentDraft.received_at"
                            fluid
                        />
                    </div>

                    <div class="col-span-12 md:col-span-6">
                        <label class="block text-normal mb-1">Suma (EUR)</label>
                        <InputNumber
                            v-model="paymentDraft.amount"
                            :min="0"
                            :minFractionDigits="2"
                            :maxFractionDigits="2"
                            :useGrouping="false"
                            locale="en-US"
                            fluid
                        />
                    </div>

                    <div class="col-span-12">
                        <label class="block text-normal mb-1">Poznámka</label>
                        <InputText
                            v-model="paymentDraft.notes"
                            fluid
                        />
                    </div>
                </div>

                <template #footer>
                    <div class="flex justify-end gap-2">
                        <Button label="Zrušiť" text class="text-accent! px-2!" @click="closePaymentDialog" />
                        <Button
                            label="Uložiť"
                            class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
                            :disabled="!paymentDraft.received_at || paymentDraft.amount === null"
                            @click="savePaymentDraft"
                        />
                    </div>
                </template>
            </Dialog>
        </section>

        <div class="col-span-12 mt-2 flex justify-end gap-2">
            <Button label="Zrušiť" text class="text-accent! px-2!" @click="close" />
            <Button
                label="Uložiť"
                :loading="saving"
                @click="save"
                class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
            />
        </div>
    </div>
</template>