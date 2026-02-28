<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'
import DatePicker from 'primevue/datepicker'
import type { IModalContentProps } from '@/types/ui'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import type { User } from '@/types/models'
import { formatUserFullName } from '@/utils/formatUtils'

const props = defineProps<IModalContentProps & { month?: string; totalId?: number }>()

const toast = useToast()
const isEdit = ref(false)

interface Total {
  id?: number
  user_id?: number
  branch_id?: number
  insurance_company_id?: number
  month: string
  points_total: number
  kilometers_total: number
}

interface Branch {
  id: number
  address: string
}

interface InsuranceCompany {
  id: number
  name: string
}

interface InsuranceCompanyTotal {
  insurance_company_id: number
  points_total: number
  kilometers_total: number
}

const total = ref<Total>({
  month: props.month || '',
  points_total: 0,
  kilometers_total: 0,
} as Total)

const insuranceCompanyTotals = ref<InsuranceCompanyTotal[]>([])
const selectedMonth = ref<Date | null>(null)
const users = ref<User[]>([])
const branches = ref<Branch[]>([])
const insuranceCompanies = ref<InsuranceCompany[]>([])

const toMonthParam = (d: Date) => {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  return `${y}-${m}`
}

const toDateFromMonthParam = (month: string) => {
  const parts = month.split('-')
  const year = Number(parts[0]) || new Date().getFullYear()
  const monthNum = Number(parts[1]) || 1
  return new Date(year, monthNum - 1, 1)
}

const isCurrentIcRow = (ict: InsuranceCompanyTotal) =>
  !isEdit.value || ict.insurance_company_id === total.value.insurance_company_id

const currentIcTotals = computed(() =>
  insuranceCompanyTotals.value.find(
    (x) => x.insurance_company_id === total.value.insurance_company_id
  )
)



onMounted(async () => {
  try {
    const auth = useAuthStore()
    const url = auth.isSuperadmin && router.currentRoute.value.params.companyId
      ? `v1/companies/${Number(router.currentRoute.value.params.companyId)}/branches`
      : 'v1/my-company/branches'
    branches.value = await api.fetchEntities<Branch>(url)
    console.log('Loaded branches:', branches.value)
    
    insuranceCompanies.value = await api.fetchEntities<InsuranceCompany>('v1/insurance-companies')
    console.log('Loaded insurance companies:', insuranceCompanies.value)

    if (props.totalId) {
      isEdit.value = true
      const response = await api.get(`v1/totals/${props.totalId}`)
      const data = response.data.data
      
      // Load users for this branch FIRST
      if (data.branch_id) {
        await updateUserOptions(data.branch_id)
      }
      
      // Then set the total data including user_id
      total.value = {
        id: Number(data.id),
        user_id: data.user_id != null ? Number(data.user_id) : undefined,
        branch_id: data.branch_id != null ? Number(data.branch_id) : undefined,
        insurance_company_id: data.insurance_company_id != null ? Number(data.insurance_company_id) : undefined,
        month: data.month,
        points_total: parseFloat(data.points_total),
        kilometers_total: parseFloat(data.kilometers_total),
      }

      selectedMonth.value = toDateFromMonthParam(data.month)
    } else {
      // Set default to previous month
      const prevDate = new Date()
      prevDate.setMonth(prevDate.getMonth() - 1)
      selectedMonth.value = new Date(prevDate.getFullYear(), prevDate.getMonth(), 1)
      total.value.month = toMonthParam(prevDate)
    }

    // Initialize insurance company totals - one row per company
    insuranceCompanyTotals.value = insuranceCompanies.value.map((ic) => ({
      insurance_company_id: ic.id,
      points_total: 0,
      kilometers_total: 0,
    }))

    // If editing, populate the existing insurance company's data
    if (isEdit.value && total.value.insurance_company_id) {
      const idx = insuranceCompanyTotals.value.findIndex(
        (ict) => ict.insurance_company_id === total.value.insurance_company_id
      )
      if (idx !== -1 && insuranceCompanyTotals.value[idx]) {
        insuranceCompanyTotals.value[idx].points_total = total.value.points_total
        insuranceCompanyTotals.value[idx].kilometers_total = total.value.kilometers_total
      }
    }
  } catch (e) {
    console.error('Failed to fetch data', e)
  }
})

const updateUserOptions = async (branchId: number) => {
  try {
    const usersResponse = await api.get(`v1/branches/${branchId}/nurses`)
    console.log('Nurses data structure:', usersResponse.data.data)
    
    // API returns {items: [...], count: N, sql: '...', meta: {...}}
    const usersData = usersResponse.data.data?.items || []
    
    users.value = usersData
    console.log('Loaded users for branch:', users.value)
  } catch (e) {
    console.error('Failed to fetch users for branch', e)
    users.value = []
  }
}

const onMonthSelect = (event: any) => {
  if (event) {
    total.value.month = toMonthParam(event)
  }
}

const userDisplayName = (userId: number | undefined) => {
  if (!userId) return ''
  const user = users.value.find(u => u.id === userId)
  return user ? formatUserFullName(user) : ''
}

watch(
  () => total.value.branch_id,
  async (newBranchId, oldBranchId) => {
    if (isEdit.value) return

    if (!newBranchId || newBranchId === oldBranchId) return

    total.value.user_id = undefined
    await updateUserOptions(newBranchId)
  }
)


const save = async () => {
  try {
    if (!total.value.user_id || !total.value.branch_id || !total.value.month) {
      toast.add({
        severity: 'warn',
        summary: 'Upozornenie',
        detail: 'Vyplňte všetky povinné položky',
        life: 3000,
      })
      return
    }

    try {
      if (isEdit.value && total.value.id) {
        const row = currentIcTotals.value
        if (!row) return

        await api.patch(`v1/totals/${total.value.id}`, {
          points_total: row.points_total,
          kilometers_total: row.kilometers_total,
        })
        toast.add({
          severity: 'success',
          summary: 'Uložené',
          detail: 'Hodnota aktualizovaná',
          life: 3000,
        })
      }
      else {
        // On create, create records for each insurance company with non-zero values
        const recordsToCreate = insuranceCompanyTotals.value.filter(
          (ict) => ict.points_total > 0 || ict.kilometers_total > 0
        )

        if (recordsToCreate.length === 0) {
          toast.add({
            severity: 'warn',
            summary: 'Upozornenie',
            detail: 'Zadajte aspoň jednu hodnotu',
            life: 3000,
          })
          return
        }

        for (const ict of recordsToCreate) {
          await api.post('v1/totals', {
            user_id: total.value.user_id,
            branch_id: total.value.branch_id,
            month: total.value.month,
            insurance_company_id: ict.insurance_company_id,
            points_total: ict.points_total,
            kilometers_total: ict.kilometers_total,
          })
        }

        toast.add({
          severity: 'success',
          summary: 'Vytvorené',
          detail: 'Hodnoty pridané do totálov',
          life: 3000,
        })
      }

      if (props.modalResolve) props.modalResolve({ success: true })
    } catch (err) {
      console.error('Failed to save total', err)
      toast.add({
        severity: 'error',
        summary: 'Chyba',
        detail: 'Nepodarilo sa uložiť hodnotu',
        life: 3000,
      })
    }
  } catch (err) {
    console.error('Failed to save total', err)
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa uložiť hodnotu',
      life: 3000,
    })
  }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div>
            <label class="block text-sm mb-1">Mesiac</label>
            <DatePicker 
              v-model="selectedMonth" 
              view="month" 
              dateFormat="MM yy"
              :disabled="isEdit"
              @update:modelValue="onMonthSelect"
              fluid
              :class="{ 'opacity-50!': isEdit }"

            />
        </div>

        <div>
            <label class="block text-sm mb-1">Pobočka</label>
            <Select 
              v-model="total.branch_id" 
              :options="branches"
              optionLabel="address"
              optionValue="id"
              :disabled="isEdit"
              placeholder="Vybrať pobočku"
            >
                <template #value="slotProps">
                    <span v-if="slotProps.value">
                        {{ branches.find(b => b.id === slotProps.value)?.address }}</span>
                    <span v-else>Vybrať pobočku</span>
                </template>
            </Select>
        </div>

        <div>
            <label class="block text-sm mb-1">Používateľ</label>
            <Select 
              v-model="total.user_id" 
              :options="users"
              optionLabel="first_name"
              optionValue="id"
              :disabled="isEdit || users.length === 0"
              placeholder="Vybrať používateľa"
            >
                <template #value="slotProps">
                    <span v-if="slotProps.value">
                        {{ userDisplayName(slotProps.value) }}
                    </span>
                    <span v-else>Vybrať používateľa</span>
                </template>
                <template #option="slotProps">
                    <span v-if="slotProps.option">
                        {{ formatUserFullName(slotProps.option) }}
                    </span>
                </template>
            </Select>
        </div>

        <div>
            <div class="overflow-auto rounded">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="">
                            <th class="p-2 pl-3 text-left text-normal">Poisťovňa</th>
                            <th class="p-2 text-left text-normal">Body</th>
                            <th class="p-2 pr-3 text-left text-normal">Kilometre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(ict, idx) in insuranceCompanyTotals" :key="ict.insurance_company_id" class="border-t border-lightgrey">
                            <td class="p-2 pl-3 text-sm">
                                {{ insuranceCompanies.find(ic => ic.id === ict.insurance_company_id)?.name.split(' ')[0] || 'ID ' + ict.insurance_company_id }}
                            </td>
                            <td class="p-2">
                                <InputNumber 
                                  v-model="(insuranceCompanyTotals[idx] as InsuranceCompanyTotal).points_total"
                                  :min="0" 
                                  :step="0.01"
                                  mode="decimal"
                                  :useGrouping="false"
                                  :minFractionDigits="0"
                                  :maxFractionDigits="2"
                                  class="w-full"
                                  :disabled="!isCurrentIcRow(ict)"
                                  :class="{ 'opacity-50!': !isCurrentIcRow(ict) }"
                                />
                            </td>
                            <td class="p-2 pr-3 flex justify-end">
                                <InputNumber 
                                  v-model="(insuranceCompanyTotals[idx] as InsuranceCompanyTotal).kilometers_total" 
                                  :min="0" 
                                  :step="0.01" 
                                  mode="decimal"
                                  :useGrouping="false"
                                  :minFractionDigits="0"
                                  :maxFractionDigits="2"
                                  class="w-full"
                                  :disabled="!isCurrentIcRow(ict)"
                                  :class="{ 'opacity-50!': !isCurrentIcRow(ict) }"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <Button label="Zrušiť" text @click="() => { if (props.modalResolve) props.modalResolve(null) }" class="text-accent! px-2!" />
            <Button label="Uložiť" class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!" @click="save" />
        </div>
    </div>
</template>
