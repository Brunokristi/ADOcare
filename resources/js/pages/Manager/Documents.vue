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

const closeEmailDialog = () => {
  showEmailDialog.value = false
  recipientEmail.value = ''
  selectedDocumentsForEmail.value = []
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

      <Dialog v-model:visible="showEmailDialog" header="Odoslať dokumenty emailom" :modal="true" :style="{ width: '40rem' }">
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
            <label class="block text-sm mb-2">Vybrané dokumenty ({{ selectedDocumentsForEmail.length }})</label>
            <div class="max-h-56 overflow-auto border border-darkgrey rounded-md p-2">
              <div
                v-for="doc in selectedDocumentsForEmail"
                :key="doc.id"
                class="py-1 text-sm border-b border-darkgrey last:border-b-0"
              >
                {{ formatDocumentType(doc.type) }} - {{ doc.created_by_user }}
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 mt-2">
            <Button label="Zrušiť" text :disabled="sendingEmail" @click="closeEmailDialog" class="text-accent!" />
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
