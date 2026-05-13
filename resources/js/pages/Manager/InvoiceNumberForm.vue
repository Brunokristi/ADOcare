<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'

type InvoiceRow = {
    id: number
    invoice_number?: string | null
    period?: string | null
}

type ExistingInvoice = {
    id: number
    invoice_number?: string | number | null
    period?: string | null
}

const props = defineProps<{ invoice?: Partial<InvoiceRow> | null; modalResolve?: (value?: any) => void }>()
const emits = defineEmits(['save', 'close'])

const toast = useToast()
const saving = ref(false)
const existingInvoices = ref<ExistingInvoice[]>([])

const local = ref<{ id?: number; invoice_number: number | null; period: string | null }>({
    id: undefined,
    invoice_number: null,
    period: null,
})

const invoiceYear = computed(() => {
    if (!local.value.period) return null

    const parsed = new Date(`${local.value.period}-01`)

    return Number.isNaN(parsed.getTime()) ? null : parsed.getFullYear()
})

const normalizedInvoiceNumber = computed(() => {
    if (local.value.invoice_number === null || local.value.invoice_number === undefined) return null

    const value = Number(local.value.invoice_number)

    return Number.isFinite(value) ? value : null
})

const invoiceNumberWarning = computed(() => {
    if (!normalizedInvoiceNumber.value) return ''

    if (!invoiceYear.value) {
        return 'Najprv vyberte obdobie faktúry.'
    }

    const conflict = existingInvoices.value.find((item) => {
        if (!item.period || item.id === local.value.id) return false

        const year = new Date(`${item.period}-01`).getFullYear()

        return year === invoiceYear.value && Number(item.invoice_number) === normalizedInvoiceNumber.value
    })

    if (conflict) {
        return `Číslo faktúry ${normalizedInvoiceNumber.value} je už použité v roku ${invoiceYear.value}.`
    }

    return ''
})

const canSubmit = computed(() => {
    return Boolean(local.value.id) && Boolean(normalizedInvoiceNumber.value) && !invoiceNumberWarning.value
})

watch(
    () => props.invoice,
    (value) => {
        local.value = {
            id: value?.id,
            invoice_number: null,
            period: value?.period ?? null,
        }
    },
    { immediate: true },
)

void loadExistingInvoices()

async function loadExistingInvoices() {
    try {
        const response = await api.get('/v1/invoices', {
            params: { paginate: 0 },
        })

        const payload = response.data?.data
        const items = (payload?.items ?? payload ?? []) as ExistingInvoice[]

        existingInvoices.value = items.filter((item) => item?.id)
    } catch (err) {
        console.error('Failed to load invoices for validation', err)
        existingInvoices.value = []
    }
}

function onInvoiceNumberInput(event: { value?: string | number | null }) {
    const numValue = typeof event.value === 'string' ? parseFloat(event.value) : event.value
    local.value.invoice_number = numValue ?? null
}

function close() {
    if (props.modalResolve) {
        try {
            props.modalResolve(undefined)
        } catch {
            // ignore modal resolve issues
        }
    } else {
        emits('close')
    }
}

async function save() {
    if (!local.value.id) return

    if (!normalizedInvoiceNumber.value || normalizedInvoiceNumber.value < 1) {
        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Zadajte číslo faktúry.',
            life: 3500,
        })
        return
    }

    if (invoiceNumberWarning.value) {
        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: invoiceNumberWarning.value,
            life: 3500,
        })
        return
    }

    saving.value = true

    try {
        await api.post(`/v1/invoices/${local.value.id}?_method=PUT`, {
            invoice_number: normalizedInvoiceNumber.value,
        })

        toast.add({
            severity: 'success',
            summary: 'Uložené',
            detail: 'Číslo faktúry bolo priradené.',
            life: 3000,
        })

        if (props.modalResolve) {
            props.modalResolve(local.value)
        } else {
            emits('save', local.value)
        }
    } catch (err) {
        console.error('Save invoice number failed', err)

        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Nepodarilo sa priradiť číslo faktúry.',
            life: 4000,
        })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="grid grid-cols-12 gap-4 p-2">
        <div class="col-span-12">
            <label class="block text-normal mb-1">Číslo faktúry</label>

            <InputNumber
                v-model="local.invoice_number"
                :min="1"
                :useGrouping="false"
                fluid
                placeholder="Zadajte číslo faktúry"
                @input="onInvoiceNumberInput"
            />

            <small
                v-if="invoiceNumberWarning"
                class="text-danger"
            >
                {{ invoiceNumberWarning }}
            </small>
        </div>

        <div class="col-span-12 mt-4 flex items-center justify-end gap-2">
            <Button
                label="Zrušiť"
                text
                @click="close"
                class="text-accent! px-2!"
            />

            <Button
                label="Priradiť"
                :loading="saving"
                :disabled="!canSubmit"
                @click="save"
                class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
            />
        </div>
    </div>
</template>