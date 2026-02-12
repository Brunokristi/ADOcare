<script setup lang="ts">
import { ref, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import type { DataTableOptions } from '@/types/datatable'
import router from '@/router'

interface BatchType {
  code: string
  name: string
}

type Document = {
  id: number
  name: string
  type?: string
  mime_type?: string
  path?: string
  created_at?: string
}

const authStore = useAuthStore()

const toast = useToast()

const branchId = computed(() => authStore.currentBranch?.id)
const batchType = ref<BatchType | null>(null)
const dates = ref<Date | null>(null)
const submitted = ref(false)
const loading = ref(false)
const tableRef = ref<any>(null)

const batchTypes = ref<BatchType[]>([
  { code: 'CP', name: 'Cestovný príkaz' },
  { code: 'DZC', name: 'Denný záznam ciest' },
])


const getDocumentUrl = (doc: { id: number; type?: string }) => {
  if (!doc.id) return null
  if (doc.type === 'cp') return `/documents/cp/${doc.id}`
  if (doc.type === 'dzc') return `/documents/dzc/${doc.id}`
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

const openGeneratedInThisTab = (type: 'cp' | 'dzc', id: number) => {
  const url = type === 'cp' ? `/documents/cp/${id}` : `/documents/dzc/${id}`
  router.push(url)
}

const formatDocumentType = (type?: string) => {
  const typeMap: Record<string, string> = {
    cp: 'Cestovný príkaz',
    dzc: 'Denný záznam ciest',
  }
  return typeMap[type || ''] || type || ''
}

const formatDateWithTime = (dateStr?: string) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const datePart = date.toLocaleDateString('sk-SK')
  const timePart = date.toLocaleTimeString('sk-SK', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
  return `${datePart} ${timePart}`
}

const options = computed<DataTableOptions<Document>>(() => ({
  rowKey: 'id',
  endpointUrl: 'v1/documents/travel',
  extraParams: branchId.value
    ? { branch_id: branchId.value }
    : {},
  defaultPageSize: 25,
  pageSizeOptions: [10, 25, 50],
  selectable: true,

  columns: [
    {
      field: 'name',
      header: 'Názov',
      sortable: true,
    },
    {
      field: 'type',
      header: 'Typ',
      sortable: true,
      render: (v: string | undefined) => formatDocumentType(v),
    },
    {
      field: 'created_at',
      header: 'Dátum a čas vytvorenia',
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
            openDocumentInNewTab(row) // ✅ table -> new tab
          },
        },
      ],
    },
  ],

  actions: [
    {
      key: 'delete',
      disabled: ({ selectedRows }: { selectedRows: Document[] }) => selectedRows.length === 0,
      icon: 'bi bi-eraser',
      class: 'bg-warning!',
      confirm: 'Naozaj chcete zmazať vybrané dokumenty?',
      handler: async ({ selectedRows, remote }: { selectedRows: Document[]; remote: any }) => {
        try {
          await api.delete('v1/documents', {
            data: {
              ids: selectedRows.map((r) => r.id),
            },
          })
          await remote.loadPage(remote.page)
        } catch (err) {
          console.error('Error deleting documents:', err)
        }
      },
    },
  ],
}))

async function onSubmit() {
  submitted.value = true

  const hasPeriod = !!dates.value
  if (!batchType.value || !hasPeriod) return
  if (!dates.value) return

  const monthDate = dates.value as Date
  const year = monthDate.getFullYear()
  const month = monthDate.getMonth()
  const startDate = new Date(year, month, 1)
  const endDate = new Date(year, month + 1, 0)

  loading.value = true

  const formatLocalDate = (date: Date) => {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
  }

  if (batchType.value.code === 'CP') {
    try {
      const res = await api.post('/v1/cps', {
        start: formatLocalDate(startDate),
        end: formatLocalDate(endDate),
        branch_id: authStore.currentBranch?.id,
      })

      const documentId = res.data?.data?.document_id
      if (!documentId) throw new Error('Missing document_id from API response')

      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Cestovný príkaz bol úspešne vytvorený',
        life: 3000,
      })

      if (tableRef.value?.remote?.loadPage) {
        await tableRef.value.remote.loadPage(1)
      }

      // ✅ generated -> same tab
      openGeneratedInThisTab('cp', documentId)
    } catch (error) {
      toast.add({
        severity: 'error',
        summary: 'Chyba',
        detail: 'Nepodarilo sa vytvoriť cestovný príkaz',
        life: 3000,
      })
      console.error('Navigation failed', error)
    } finally {
      loading.value = false
    }
  } else if (batchType.value.code === 'DZC') {
    try {
      const res = await api.post('/v1/dzcs', {
        start: formatLocalDate(startDate),
        end: formatLocalDate(endDate),
        branch_id: authStore.currentBranch?.id,
      })

      const documentId = res.data?.data?.document_id
      if (!documentId) throw new Error('Missing document_id from API response')

      toast.add({
        severity: 'success',
        summary: 'Úspech',
        detail: 'Denný záznam ciest bol úspešne vytvorený',
        life: 3000,
      })

      if (tableRef.value?.remote?.loadPage) {
        await tableRef.value.remote.loadPage(1)
      }

      // ✅ generated -> same tab
      openGeneratedInThisTab('dzc', documentId)
    } catch (error) {
      toast.add({
        severity: 'error',
        summary: 'Chyba',
        detail: 'Nepodarilo sa vytvoriť denný záznam ciest',
        life: 3000,
      })
      console.error('Navigation failed', error)
    } finally {
      loading.value = false
    }
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-12 gap-4">
          <!-- Typ -->
          <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Typ dokumentu</label>
            <Select
              v-model="batchType"
              :options="batchTypes"
              optionLabel="name"
              fluid
              class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
            />
            <small v-if="submitted && !batchType" class="text-warning">
              Typ cestovného je povinný.
            </small>
          </div>

          <!-- Obdobie -->
          <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Obdobie</label>
            <DatePicker
              v-model="dates"
              view="month"
              dateFormat="MM yy"
              :manualInput="false"
              inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
              fluid
            />
            <small v-if="submitted && !dates" class="text-warning"> Obdobie je povinné. </small>
          </div>
        </div>
      </section>

      <div class="flex justify-end">
        <Button
          type="submit"
          :disabled="loading"
          class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white w-100"
        >
          Vygenerovať
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>

    <section>
      <UniversalDataTable ref="tableRef" :options="options">
        <template #actions="{ row }">
          <button
            @click.stop="openDocumentInNewTab(row)"
            class="btn btn-sm btn-link p-0"
            title="Otvoriť dokument"
          >
            <i class="bi bi-eye"></i>
          </button>
        </template>
      </UniversalDataTable>
    </section>
  </div>
</template>
