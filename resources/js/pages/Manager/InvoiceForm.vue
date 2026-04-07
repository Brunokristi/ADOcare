<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'

type InsuranceCompanyOption = {
  id: number
  name: string
}

type InvoicePayload = {
  id?: number
  insurance_company_id: number | null
  period: Date | null
  type: string | null
  total?: number
  amount: number | null
  related_invoice_id: number | null
}

type RelatedInvoiceOption = {
  id: number
  invoice_number: string
  period: string | null
  insurance_company_id: number | null
}

const invoiceTypes = [
  { id: 'procedures', name: 'Výkonová' },
  { id: 'transport', name: 'Dopravná' },
  { id: 'credit_note', name: 'Dobropis' },
  { id: 'debit_note', name: 'Ťarchopis' },
]

const props = defineProps<{ invoice?: Partial<InvoicePayload> | null; modalResolve?: (value?: any) => void }>()
const emits = defineEmits(['save', 'close'])

const toast = useToast()

const insuranceCompanies = ref<InsuranceCompanyOption[]>([])
const relatedInvoices = ref<RelatedInvoiceOption[]>([])
const saving = ref(false)
const loadingOptions = ref(false)
const local = ref<InvoicePayload>({
  insurance_company_id: null,
  period: null,
  type: null,
  amount: null,
  related_invoice_id: null,
})

const isNoteType = computed(() => local.value.type === 'credit_note' || local.value.type === 'debit_note')
const isDebitNote = computed(() => local.value.type === 'debit_note')
const selectedRelatedInvoice = computed(() => {
  if (!local.value.related_invoice_id) return null
  return relatedInvoices.value.find((item) => item.id === local.value.related_invoice_id) ?? null
})


watch(
  () => props.invoice,
  (v) => {
    local.value = {
      id: v?.id,
      insurance_company_id: v?.insurance_company_id ?? null,
      period: v?.period ? parsePeriod(v.period) : null,
      type: v?.type ?? null,
      amount: typeof v?.total === 'number' ? v.total : null,
      related_invoice_id: (v as any)?.related_invoice_id ?? null,
    }
  },
  { immediate: true }
)

void loadInsuranceCompanies()
void loadRelatedInvoices()

function parsePeriod(value: unknown): Date | null {
  if (typeof value !== 'string') return null
  const [year, month] = value.split('-').map(Number)
  if (!year || !month) return null
  return new Date(year, month - 1, 1)
}

function toApiMonth(date: Date | null): string | null {
  if (!date) return null
  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  return `${year}-${month}`
}

async function loadInsuranceCompanies() {
  try {
    loadingOptions.value = true
    const response = await api.get('/v1/insurance-companies', { params: { paginate: 0 } })
    const payload = response.data?.data
    insuranceCompanies.value = (payload?.items ?? payload ?? []) as InsuranceCompanyOption[]
  } catch (err) {
    console.error('Failed to load insurance companies', err)
    insuranceCompanies.value = []
  } finally {
    loadingOptions.value = false
  }
}

async function loadRelatedInvoices() {
  try {
    const response = await api.get('/v1/invoices', { params: { paginate: 0 } })
    const payload = response.data?.data
    const items = (payload?.items ?? payload ?? []) as any[]

    relatedInvoices.value = items
      .filter((item) => item?.id && !['credit_note', 'debit_note'].includes(item?.type))
      .sort((a, b) => Number(b.id) - Number(a.id))
      .map((item) => ({
        id: Number(item.id),
        invoice_number: item.invoice_number ?? `#${item.id}`,
        period: item.period ?? null,
        insurance_company_id: item.insurance_company_id ?? null,
      }))
  } catch (err) {
    console.error('Failed to load related invoices', err)
    relatedInvoices.value = []
  }
}

watch(
  () => [isNoteType.value, local.value.related_invoice_id],
  () => {
    if (!isNoteType.value) return

    const related = selectedRelatedInvoice.value
    if (!related) return

    local.value.period = related.period ? parsePeriod(related.period) : null
    if (related.insurance_company_id) {
      local.value.insurance_company_id = related.insurance_company_id
    }
  },
  { immediate: true }
)

watch(
  () => [local.value.type, local.value.amount],
  () => {
    // For debit notes, keep editable value positive and render minus via prefix.
    if (isDebitNote.value && local.value.amount != null && local.value.amount < 0) {
      local.value.amount = Math.abs(local.value.amount)
    }
  },
  { immediate: true }
)

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
  const derivedPeriod = isNoteType.value && selectedRelatedInvoice.value?.period
    ? selectedRelatedInvoice.value.period
    : null
  const period = derivedPeriod ?? toApiMonth(local.value.period)

  if (!local.value.type) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyberte typ faktúry.', life: 3500 })
    return
  }

  if (!period) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyberte obdobie.', life: 3500 })
    return
  }

  if (isNoteType.value && local.value.amount == null) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Zadajte sumu dokladu.', life: 3500 })
    return
  }

  if (isDebitNote.value && (local.value.amount ?? 0) <= 0) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Pri ťarchopise musí byť suma záporná.', life: 3500 })
    return
  }

  if (isNoteType.value && !local.value.related_invoice_id) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyberte súvisiacu faktúru.', life: 3500 })
    return
  }

  saving.value = true

  try {
    const payload: Record<string, unknown> = {
      period,
      type: local.value.type,
    }

    if (local.value.insurance_company_id) {
      payload.insurance_company_id = local.value.insurance_company_id
    }

    if (isNoteType.value) {
      payload.amount = isDebitNote.value
        ? -Math.abs(local.value.amount ?? 0)
        : local.value.amount ?? 0
      payload.related_invoice_id = local.value.related_invoice_id
    }

    if (local.value.id) {
      await api.post(`/v1/invoices/${local.value.id}?_method=PUT`, payload)
    } else {
      await api.post('/v1/invoices', payload)
    }

    toast.add({ severity: 'success', summary: 'Uložené', detail: 'Faktúra bola uložená.', life: 3000 })

    if (props.modalResolve) {
      props.modalResolve(local.value)
    } else {
      emits('save', local.value)
    }
  } catch (err) {
    console.error('Save invoice failed', err)
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť faktúru.', life: 4000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="grid grid-cols-12 gap-4 p-2">

    <div class="col-span-12">
      <label class="block text-normal mb-1">Poisťovňa</label>
      <Select
        v-model="local.insurance_company_id"
        :options="insuranceCompanies"
        optionLabel="name"
        optionValue="id"
        :loading="loadingOptions"
        fluid
        dropdownIcon="bi bi-chevron-down"
      />
    </div>

    <div class="col-span-12">
      <label class="block text-normal mb-1">Typ</label>
      <Select
        v-model="local.type"
        :options="invoiceTypes"
        optionLabel="name"
        optionValue="id"
        :loading="loadingOptions"
        fluid
        dropdownIcon="bi bi-chevron-down"
      />
    </div>

    <div v-if="!isNoteType" class="col-span-12">
      <label class="block text-normal mb-1">Obdobie</label>
      <DatePicker
        v-model="local.period"
        view="month"
        dateFormat="mm/yy"
        :manualInput="false"
        class="w-full"
        inputClass="w-full!"
      />
    </div>

    <div v-if="isNoteType" class="col-span-12">
      <label class="block text-normal mb-1">Suma</label>
      <InputNumber
        v-model="local.amount"
        mode="decimal"
        :prefix="isDebitNote ? '- ' : undefined"
        :min="isDebitNote ? 0.01 : undefined"
        :minFractionDigits="2"
        :maxFractionDigits="2"
        :useGrouping="false"
        fluid
      />
    </div>

    <div v-if="isNoteType" class="col-span-12">
      <label class="block text-normal mb-1">K faktúre</label>
      <Select
        v-model="local.related_invoice_id"
        :options="relatedInvoices"
        optionLabel="invoice_number"
        optionValue="id"
        filter
        filterPlaceholder="Hľadať faktúru"
        fluid
        dropdownIcon="bi bi-chevron-down"
      />
    </div>

    <div class="col-span-12 mt-4 flex items-center justify-end gap-2">
      <Button label="Zrušiť" text @click="close" class="text-accent! px-2!" />
      <Button
        :label="local.id ? 'Upraviť' : 'Vytvoriť'"
        :loading="saving"
        @click="save"
        class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
      />
    </div>
  </div>
</template>
