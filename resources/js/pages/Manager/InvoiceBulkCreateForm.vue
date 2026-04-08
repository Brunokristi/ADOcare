<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'

type InsuranceCompanyOption = {
  id: number
  name: string
}

const props = defineProps<{ initialPeriod?: Date | null; modalResolve?: (value?: any) => void }>()

const toast = useToast()
const saving = ref(false)
const loadingCompanies = ref(false)
const period = ref<Date | null>(props.initialPeriod ?? null)
const insuranceCompanies = ref<InsuranceCompanyOption[]>([])

void loadInsuranceCompanies()

function toApiMonth(date: Date | null): string | null {
  if (!date) return null
  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  return `${year}-${month}`
}

function close(result?: any) {
  if (props.modalResolve) {
    try {
      props.modalResolve(result)
    } catch {
      // ignore modal resolve issues
    }
  }
}

async function loadInsuranceCompanies() {
  try {
    loadingCompanies.value = true
    const response = await api.get('/v1/insurance-companies', { params: { paginate: 0 } })
    const payload = response.data?.data
    insuranceCompanies.value = (payload?.items ?? payload ?? []) as InsuranceCompanyOption[]
  } catch (err) {
    console.error('Failed to load insurance companies', err)
    insuranceCompanies.value = []
  } finally {
    loadingCompanies.value = false
  }
}

async function submit() {
  const periodValue = toApiMonth(period.value)

  if (!periodValue) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyberte obdobie.', life: 3500 })
    return
  }

  if (!insuranceCompanies.value.length) {
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nie sú dostupné žiadne poisťovne.', life: 3500 })
    return
  }

  saving.value = true

  try {
    const invoiceTypes: Array<'procedures' | 'transport'> = ['procedures', 'transport']
    const requests: Promise<unknown>[] = []

    for (const company of insuranceCompanies.value) {
      for (const type of invoiceTypes) {
        requests.push(
          api.post('/v1/invoices', {
            insurance_company_id: company.id,
            period: periodValue,
            type,
          })
        )
      }
    }

    const results = await Promise.allSettled(requests)
    const successCount = results.filter((r) => r.status === 'fulfilled').length
    const failedCount = results.length - successCount

    if (failedCount === 0) {
      toast.add({
        severity: 'success',
        summary: 'Uložené',
        detail: `Vytvorených faktúr: ${successCount}.`,
        life: 3500,
      })
    } else {
      toast.add({
        severity: 'warn',
        summary: 'Dokončené s chybami',
        detail: `Vytvorených faktúr: ${successCount}, neúspešných: ${failedCount}.`,
        life: 5000,
      })
    }

    close({ created: successCount, failed: failedCount })
  } catch (err) {
    console.error('Bulk invoice create failed', err)
    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa vytvoriť faktúry.', life: 4000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="grid grid-cols-12 gap-4 p-2">
    <div class="col-span-12">
      <label class="block text-normal mb-1">Obdobie</label>
      <DatePicker
        v-model="period"
        view="month"
        dateFormat="mm/yy"
        :manualInput="false"
        class="w-full"
        inputClass="w-full!"
      />
    </div>

    <div class="col-span-12 mt-4 flex items-center justify-end gap-2">
      <Button label="Zrušiť" text @click="close()" class="text-accent! px-2!" />
      <Button
        label="Vytvoriť všetky"
        :loading="saving || loadingCompanies"
        @click="submit"
        class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
      />
    </div>
  </div>
</template>