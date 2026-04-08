<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import type { DataTableOptions } from '@/types/datatable'

type Document = {
  id: number
  name: string
  type?: string
  mime_type?: string
  path?: string
  updated_at?: string
  created_by_user?: string
  created_by_branch?: string
  period?: string
}

type Branch = {
  id: number
  name: string
  address?: string | null
  city?: string | null
}

const toast = useToast()
const branches = ref<Branch[]>([])
const loading = ref(true)
const now = new Date()
const dates = ref<Date | null>(new Date(now.getFullYear(), now.getMonth() - 1, 1))
const showEmailDialog = ref(false)
const sendingEmail = ref(false)
const recipientEmail = ref('')
const selectedDocumentsForEmail = ref<Document[]>([])
const showCreateDialog = ref(false)
const creatingDocument = ref(false)
const createType = ref<'cp' | 'dzc'>('dzc')
const createPeriod = ref<Date | null>(new Date(now.getFullYear(), now.getMonth() - 1, 1))
const createBranchId = ref<number | null>(null)
const tableRemote = ref<any>(null)

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

onMounted(async () => {
  try {
    loading.value = true
    const auth = useAuthStore()
    const url = auth.isSuperadmin && router.currentRoute.value.params.companyId
      ? `/v1/companies/${Number(router.currentRoute.value.params.companyId)}/branches`
      : '/v1/my-company/branches'
    const res = await api.get(url, {
      params: { paginate: 0 },
    })
    const payload = res.data?.data
    const items =
      (payload?.items as Branch[] | undefined) ??
      (Array.isArray(payload) ? (payload as Branch[]) : []) ??
      []
    branches.value = items
  } catch (e) {
    console.error('Failed to load branches', e)
    branches.value = []
  } finally {
    loading.value = false
  }
})

const getDocumentUrl = (doc: { id: number; type?: string }) => {
  if (!doc.id) return null
  if (doc.type === 'cp') return `/documents/cp/${doc.id}`
  if (doc.type === 'dzc') return `/documents/dzc/${doc.id}`
  if (doc.type === 'kilometers') return `/documents/kilometers/${doc.id}`
  return null
}

const openDocumentInNewTab = (doc: Document) => {
  const url = getDocumentUrl(doc)
  if (!url) {
    console.warn('Unknown document type:', doc.type)
    return
  }
  window.open(url, '_blank', 'noopener,noreferrer')
}

const formatDocumentType = (type?: string) => {
  const typeMap: Record<string, string> = {
    cp: 'Cestovný príkaz',
    dzc: 'Denný záznam ciest',
    kilometers: 'Kilometráž',
  }
  return typeMap[type || ''] || type || ''
}

const formatDateWithTime = (dateStr?: string) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  if (Number.isNaN(date.getTime())) return '-'
  const datePart = date.toLocaleDateString('sk-SK')
  const timePart = date.toLocaleTimeString('sk-SK', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
  return `${datePart} ${timePart}`
}

const formatPeriod = (period?: string) => {
  if (!period) return '-'

  const [year, month] = period.split('-').map(Number)
  if (!year || !month) return period

  const date = new Date(year, month - 1, 1)
  return date.toLocaleDateString('sk-SK', {
    month: '2-digit',
    year: 'numeric',
  })
}

const openEmailDialog = (selectedRows: Document[]) => {
  selectedDocumentsForEmail.value = [...selectedRows]
  recipientEmail.value = ''
  showEmailDialog.value = true
}

const openCreateDialog = (remote?: any) => {
  tableRemote.value = remote ?? null
  createType.value = 'dzc'
  createPeriod.value = new Date(now.getFullYear(), now.getMonth() - 1, 1)
  createBranchId.value = branches.value[0]?.id ?? null
  showCreateDialog.value = true
}

const closeCreateDialog = () => {
  showCreateDialog.value = false
}

const closeEmailDialog = () => {
  showEmailDialog.value = false
  recipientEmail.value = ''
  selectedDocumentsForEmail.value = []
}

const formatYearMonth = (date: Date | null) => {
  if (!date) return null
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

const formatBranchLabel = (branch?: Branch | null) => {
  if (!branch) return ''

  const address = (branch.address || '').trim()
  const city = (branch.city || '').trim()
  const composed = [address, city].filter(Boolean).join(', ')

  if (composed) return composed
  return (branch.name || '').trim() || 'Neznáma pobočka'
}

const createTravelDocument = async () => {
  if (!createBranchId.value || !createPeriod.value) {
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Vyberte pobočku a obdobie',
      life: 3000,
    })
    return
  }

  const period = formatYearMonth(createPeriod.value)
  if (!period) {
    return
  }

  creatingDocument.value = true

  try {
    const res = await api.post('v1/documents/travel/company/create', {
      type: createType.value,
      branch_id: createBranchId.value,
      period,
    })

    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: createType.value === 'cp'
        ? 'Cestovný príkaz bol vytvorený'
        : 'Denný záznam ciest bol vytvorený',
      life: 3000,
    })

    const docId = Number(res.data?.data?.document_id ?? res.data?.document_id ?? 0)
    if (docId > 0) {
      const url = createType.value === 'cp' ? `/documents/cp/${docId}` : `/documents/dzc/${docId}`
      window.open(url, '_blank', 'noopener,noreferrer')
    }

    closeCreateDialog()

    if (tableRemote.value) {
      await tableRemote.value.loadPage(tableRemote.value.page)
    }
  } catch (err: any) {
    console.error('Error creating manager travel document:', err)
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: err?.response?.data?.message || 'Nepodarilo sa vytvoriť dokument',
      life: 4000,
    })
  } finally {
    creatingDocument.value = false
  }
}

const sendSelectedDocumentsByEmail = async () => {
  const email = recipientEmail.value.trim()

  if (!emailRegex.test(email)) {
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Neplatná emailová adresa',
      life: 3000,
    })
    return
  }

  if (selectedDocumentsForEmail.value.length === 0) {
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nie sú vybrané žiadne dokumenty',
      life: 3000,
    })
    return
  }

  sendingEmail.value = true
  try {
    await api.post('v1/documents/travel/company/email', {
      email,
      ids: selectedDocumentsForEmail.value.map((doc) => doc.id),
    })

    toast.add({
      severity: 'success',
      summary: 'Úspech',
      detail: 'Email bol odoslaný',
      life: 3000,
    })

    closeEmailDialog()
  } catch (err) {
    console.error('Error sending documents email:', err)
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: 'Nepodarilo sa odoslať email',
      life: 3000,
    })
  } finally {
    sendingEmail.value = false
  }
}

const options = computed<DataTableOptions<Document>>(() => ({
  rowKey: 'id',
  endpointUrl: 'v1/documents/travel/company',
  extraParams: branches.value.length > 0
    ? { branch_ids: branches.value.map(b => b.id).join(',') }
    : {},
  dateRangeFilter: {
    mode: 'single',
    param: 'period',
    placeholder: 'Obdobie',
    view: 'month',
    dateFormat: 'mm/yy',
    value: dates.value,
  },
  defaultPageSize: 25,
  pageSizeOptions: [10, 25, 50],
  selectable: true,

  columns: [
    {
      field: 'type',
      header: 'Typ',
      sortable: true,
      render: (v: string | undefined) => formatDocumentType(v),
    },
    {
      field: 'created_by_user',
      header: 'Používateľ',
      sortable: true,
      render: (v: string | undefined) => v || 'Neznámy',
    },
    {
      field: 'created_by_branch',
      header: 'Pobočka',
      sortable: true,
      render: (v: string | undefined) => v || 'Neznáma',
    },
    {
      field: 'period',
      header: 'Obdobie',
      sortable: true,
      render: (v: string | undefined) => formatPeriod(v),
    },
        {
      field: 'updated_at',
      header: 'Naposledy upravené',
      sortable: true,
      render: (v: string | undefined) => formatDateWithTime(v),
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
          tooltip: 'Zobraziť dokument',
          action: (row: Document) => {
            openDocumentInNewTab(row)
          },
        },
      ],
    },
  ],

  actions: [
    {
      key: 'create',
      icon: 'bi bi-plus',
      class: 'bg-accent!',
      tooltip: 'Vytvoriť cestovný dokument',
      handler: ({ remote }: { remote: any }) => {
        openCreateDialog(remote)
      },
    },
    {
      key: 'email',
      disabled: ({ selectedRows }: { selectedRows: Document[] }) => selectedRows.length === 0,
      icon: 'bi bi-send',
      class: 'bg-accent!',
      tooltip: 'Poslať vybrané dokumenty emailom',
      handler: ({ selectedRows }: { selectedRows: Document[] }) => {
        openEmailDialog(selectedRows)
      },
    },
    {
      key: 'delete',
      disabled: ({ selectedRows }: { selectedRows: Document[] }) => selectedRows.length === 0,
      icon: 'bi bi-eraser',
      class: 'bg-danger!',
      confirm: 'Naozaj chcete zmazať vybrané dokumenty?',
      handler: async ({ selectedRows, remote }: { selectedRows: Document[]; remote: any }) => {
        try {
          await api.delete('v1/documents', {
            data: {
              ids: selectedRows.map((r) => r.id),
            },
          })
          await remote.loadPage(remote.page)
          toast.add({
            severity: 'success',
            summary: 'Úspech',
            detail: 'Dokumenty boli vymazané',
            life: 3000,
          })
        } catch (err) {
          console.error('Error deleting documents:', err)
          toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Nepodarilo sa vymazať dokumenty',
            life: 3000,
          })
        }
      },
    },
  ],
}))
</script>

<template>
  <div class="flex flex-col gap-6">
    <section>
      <UniversalDataTable v-if="!loading" ref="tableRef" :options="options">
        <template #actions="{ row }">
          <button @click.stop="openDocumentInNewTab(row)" class="btn btn-sm btn-link p-0" title="Otvoriť dokument">
            <i class="bi bi-eye"></i>
          </button>
        </template>
      </UniversalDataTable>
      <div v-else class="flex items-center justify-center p-8">
        <span>Načítavam dokumenty...</span>
      </div>

      <Dialog
        v-model:visible="showCreateDialog"
        header="Vytvoriť cestovný dokument"
        :modal="true"
        :style="{ width: '34rem' }"
      >
        <div class="flex flex-col gap-4">
          <div>
            <label class="block text-sm mb-2">Typ dokumentu</label>
            <Select
              v-model="createType"
              class="w-full"
              :options="[
                { label: 'Denný záznam ciest', value: 'dzc' },
                { label: 'Cestovný príkaz', value: 'cp' },
              ]"
              optionLabel="label"
              optionValue="value"
              :disabled="creatingDocument"
            />
          </div>

          <div>
            <label class="block text-sm mb-2">Východzia pobočka</label>
            <Select
              v-model="createBranchId"
              class="w-full"
              :options="branches"
              optionValue="id"
              :disabled="creatingDocument"
              placeholder="Vyberte pobočku"
            >
              <template #option="{ option }">
                <span>{{ formatBranchLabel(option) }}</span>
              </template>
              <template #value="{ value }">
                <span v-if="value">{{ formatBranchLabel(branches.find((b) => b.id === value) || null) }}</span>
                <span v-else class="text-gray-400">Vyberte pobočku</span>
              </template>
            </Select>
          </div>

          <div>
            <label class="block text-sm mb-2">Obdobie</label>
            <DatePicker
              v-model="createPeriod"
              view="month"
              dateFormat="mm/yy"
              class="w-full!"
              inputClass="w-full!"
              :disabled="creatingDocument"
            />
          </div>

          <div class="flex justify-end gap-2 mt-2">
            <Button
              label="Zrušiť"
              text
              :disabled="creatingDocument"
              @click="closeCreateDialog"
              class="text-accent!"
            />
            <Button
              label="Vytvoriť"
              :loading="creatingDocument"
              :disabled="creatingDocument || !createBranchId || !createPeriod"
              class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! !px-2"
              @click="createTravelDocument"
            />
          </div>
        </div>
      </Dialog>

      <Dialog
        v-model:visible="showEmailDialog"
        header="Odoslať dokumenty emailom"
        :modal="true"
        :style="{ width: '40rem' }"
      >
        <div class="flex flex-col gap-4">
            <div>
                <label class="block text-sm mb-2">Email príjemcu</label>
                <InputText
                    v-model="recipientEmail"
                    type="email"
                    class="w-full"
                    placeholder="napr. meno@firma.sk"
                    :disabled="sendingEmail"
                />
            </div>

            <div>
                <label class="block text-sm mb-2">
                    Vybrané dokumenty ({{ selectedDocumentsForEmail.length }})
                </label>

                <div class="max-h-64 overflow-auto border rounded-md bg-white border-none">
                    <div
                        v-for="doc in selectedDocumentsForEmail"
                        :key="doc.id"
                        class="flex items-start gap-3 p-3 rounded-md border border-lightgrey mb-2 last:mb-0"
                    >
                        <div class="flex items-center justify-center w-10 h-10 rounded-md bg-tag3 shrink-0">
                            <i class="bi bi-file-earmark text-lg text-accent"></i>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm text-darkgrey">
                                {{ formatDocumentType(doc.type) }}
                            </div>

                            <div class="text-xs text-darkgrey mt-1">
                                Vytvoril: {{ doc.created_by_user || '-' }}
                            </div>


                        </div>
                    </div>

                    <div
                        v-if="selectedDocumentsForEmail.length === 0"
                        class="text-sm text-darkgrey text-center py-6"
                    >
                        Nie sú vybrané žiadne dokumenty.
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-2">
                <Button
                    label="Zrušiť"
                    text
                    :disabled="sendingEmail"
                    @click="closeEmailDialog"
                    class="text-accent!"
                />

                <Button
                    label="Odoslať"
                    :loading="sendingEmail"
                    :disabled="sendingEmail || selectedDocumentsForEmail.length === 0"
                    class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! !px-2"
                    @click="sendSelectedDocumentsByEmail"
                />
            </div>
        </div>
    </Dialog>
    </section>
  </div>
</template>
