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
  subtype?: string
  mime_type?: string
  path?: string
  created_at?: string
  created_by_user?: string
  created_by_branch?: string
  insurance_company_name?: string
  branch_address?: string
  amount?: number
}

type Branch = {
  id: number
  name: string
}

const toast = useToast()
const branches = ref<Branch[]>([])
const loading = ref(true)

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
  if (doc.type === 'kilometers_batch') return `/documents/kilometers/${doc.id}`
  if (doc.type === 'points_batch') return `/documents/points/${doc.id}`
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
    kilometers_batch: 'Dávka kilometrov',
    points_batch: 'Dávka bodov',
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

const formatAmount = (amount?: number) => {
  if (!amount) return '-'
  return amount.toLocaleString('sk-SK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ' €'
}

const options = computed<DataTableOptions<Document>>(() => ({
  rowKey: 'id',
  endpointUrl: 'v1/batch-documents/company',
  extraParams: {},
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
      field: 'subtype',
      header: 'Podtyp',
      sortable: true,
      render: (v: string | undefined) => v || '-',
    },
    {
      field: 'amount',
      header: 'Suma',
      sortable: false,
      render: (v: number | undefined) => formatAmount(v),
      width: '10rem',
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
      field: 'created_at',
      header: 'Dátum a čas vytvorenia',
      sortable: true,
      render: (v: string | undefined) => formatDateWithTime(v),
    },
    {
      field: 'created_by_user',
      header: 'Používateľ',
      sortable: true,
      render: (v: string | undefined) => v || 'Neznámy',
    },
    {
      field: 'branch_address',
      header: 'Adresa',
      sortable: true,
      render: (v: string | undefined) => v || 'Neznáma',
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
    </section>
  </div>
</template>
