<script setup lang="ts">
import { ref, computed, onMounted, markRaw } from 'vue'
// PrimeVue 4 renamed/deprecated the old TabView component in favour of a
// simpler "Tabs" container.  The old TabView import is now marked
// deprecated and will eventually be removed; switch to the new implementation
// here.  We still use the TabPanel component for individual panels.
// PrimeVue 4 renamed/deprecated the old TabView component in favour of a
// simpler "Tabs" container.  The old TabView import is now marked
// deprecated and will eventually be removed; switch to the new implementation
// here.  We still use the TabPanel component for individual panels, and the
// new API requires a few more pieces (TabList/Tab/TabPanels) to build the
// structure.
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import { useRoute, useRouter } from 'vue-router'
import UniversalDataTable from '@/components/UniversalDataTable.vue'
import PatientDataTable from '@/components/PatientDataTable.vue'
import Menu from 'primevue/menu'
import type { Company, Branch, User } from '@/types/models'
import type { DataTableOptions, RemoteTableReturn } from '@/types/datatable'
import ActionButtons from '@/components/table-columns/ActionButtons.vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import useModal from '@/composables/useModal'
import BranchForm from '../Branches/BranchForm.vue'
import UserForm from '../Users/UserForm.vue'
import api from '@/services/api'

const route = useRoute()
const companyId = Number(route.params.companyId)
const router = useRouter()

const company = ref<Company | null>(null)
const stats = ref<any>(null)
const loadingStats = ref(false)

async function loadCompany() {
    try {
        company.value = await api.fetchEntity<Company>(`v1/companies/${companyId}`)
    } catch (e) {
        console.error('Failed to load company', e)
    }
}

async function loadStats() {
    loadingStats.value = true
    try {
        const res = await api.get(`v1/companies/${companyId}/stats`)
        stats.value = res.data?.data
    } catch (e) {
        console.error('Failed to load stats', e)
    } finally {
        loadingStats.value = false
    }
}

onMounted(async () => {
    await Promise.all([loadCompany(), loadStats()])
})

const toast = useToast()
const { openModal } = useModal()
const branchRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)
const userRemote = ref<RemoteTableReturn>({} as RemoteTableReturn)

async function openEditBranch(branchId: number) {
    const result = await openModal(BranchForm, { branchId, companyId }, { header: 'Upraviť pobočku', style: { width: '90%' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Pobočka bola upravená', life: 3000 })
        branchRemote.value?.reload()
    }
}

async function openCreateBranch() {
    const result = await openModal(BranchForm, { companyId }, { header: 'Pridať pobočku', style: { width: '90%' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Pobočka bola vytvorená', life: 3000 })
        branchRemote.value?.reload()
    }
}

async function openEditUser(userId: number) {
    const result = await openModal(markRaw(UserForm), { userId, companyId }, { header: 'Upraviť používateľa', style: { width: '800px' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Používateľ bol uložený', life: 3000 })
        userRemote.value?.reload()
    }
}

async function openCreateUser() {
    const result = await openModal(markRaw(UserForm), { companyId }, { header: 'Pridať používateľa', style: { width: '800px' } })
    if (result) {
        toast.add({ severity: 'success', summary: 'Vytvorené', detail: 'Používateľ bol vytvorený', life: 3000 })
        userRemote.value?.reload()
    }
}


// dropdown menu for actions next to company name
const menu = ref<any>(null)
const menuItems = [
    {
        label: 'Upraviť',
        icon: 'pi pi-pencil',
        command: () => {
            // navigate to settings page, passing same companyId param
            router.push({ name: 'superadmin-company-edit', params: { companyId } })
        }
    },
    {
        label: 'Zmazať',
        icon: 'pi pi-trash',
        command: async () => {
            if (!confirm('Naozaj zmazať túto spoločnosť?')) return
            try {
                await api.delete(`v1/companies/${companyId}`)
                toast.add({ severity: 'success', summary: 'Zmazané', detail: 'Spoločnosť bola odstránená', life: 3000 })
                router.go(-1)
            } catch (e) {
                console.error('Failed to delete company', e)
                toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa zmazať spoločnosť', life: 5000 })
            }
        }
    }
]

function openMenu(event: Event) {
    menu.value?.toggle(event)
}

const branchOptions = computed<DataTableOptions<Branch>>(() => ({
    rowKey: 'id',
    endpointUrl: `v1/companies/${companyId}/branches`,
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    extraParams: { with: 'representative', count: 'users' },
    afterInit: ({ remote }) => { branchRemote.value = remote },
    columns: [
        { field: 'address', header: 'Adresa', sortable: false, render: (_v, row: Branch) => `${row.address || ''} ${row.city ? ', ' + row.city : ''}` },
        { field: 'city', header: 'Mesto', sortable: true },
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'representative', header: 'Obozorný zástupca', sortable: false, render: (v: User) => v ? `${v.first_name} ${v.last_name}` : '' },
        { field: 'users_count', header: 'Počet zamestnancov', sortable: true },
        {
            field: 'edit', header: '', width: '3rem', component: ActionButtons, componentOptions: [
                { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: Branch) => openEditBranch(row.id) }
            ]
        }
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            confirm: 'Zmazať vybrané pobočky?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/branches', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            }
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => { await openCreateBranch() }
        }
    ]
}))

const userOptions = computed<DataTableOptions<User>>(() => ({
    rowKey: 'id',
    endpointUrl: `v1/companies/${companyId}/users`,
    defaultPageSize: 25,
    pageSizeOptions: [10, 25, 50],
    selectable: true,
    extraParams: { company_id: companyId, with: 'role,branches' },
    afterInit: ({ remote }) => { userRemote.value = remote },
    columns: [
        { field: 'first_name', header: 'Meno', sortable: true },
        { field: 'last_name', header: 'Priezvisko', sortable: true },
        { field: 'title', header: 'Titul', sortable: false },
        { field: 'code', header: 'Kód', sortable: true },
        { field: 'phone_number', header: 'Telefón', sortable: false },
        { field: 'email', header: 'Email', sortable: false },
        {
            field: 'edit', header: '', width: '3rem', component: ActionButtons, componentOptions: [
                { icon: 'bi bi-pencil', color: 'info', tooltip: 'Upraviť', action: (row: User) => openEditUser(row.id) }
            ]
        }
    ],
    actions: [
        {
            key: 'delete',
            disabled: ({ selectedRows }) => selectedRows.length === 0,
            icon: 'bi bi-eraser',
            class: 'bg-danger!',
            confirm: 'Naozaj vymazať vybraných používateľov?',
            handler: async ({ selectedRows, remote }) => {
                await api.delete('v1/users', { data: { ids: selectedRows.map((r) => r.id) } })
                await remote.loadPage(remote.page.value)
            }
        },
        {
            key: 'add',
            icon: 'bi bi-plus-lg',
            class: 'bg-accent!',
            handler: async () => { await openCreateUser() }
        }
    ]
}))

// track the currently active tab (string values make the template easier to
// read, but numeric indexes would work as well).  Default to the first one.
// computed endpoint for patient listing when viewed from company overview
const companyPatientEndpoint = computed(() => `v1/companies/${companyId}/patients`)

const activeTab = ref<string>('info')
</script>

<template>
    <div class="p-4">
        <div v-if="company" class="text-heading-accent text-xl mb-4 flex items-center">
            <span>{{ company.name }}</span>
            <Button icon="bi bi-three-dots-vertical" class="ml-2 p-0 text-accent bg-transparent border-none"
                @click="openMenu" />
            <Menu :model="menuItems" popup ref="menu" />
        </div>
        <!-- new PrimeVue 4 Tabs API: Tabs + TabList/Tab/TabPanels/TabPanel -->
        <Tabs v-model:value="activeTab">
            <TabList>
                <Tab value="info">Informácie</Tab>
                <Tab value="branches">Pobočky</Tab>
                <Tab value="users">Používatelia</Tab>
                <Tab value="patients">Pacienti</Tab>
            </TabList>
            <TabPanels>
                <TabPanel value="info">
                    <div v-if="company">
                        <div>IČO: {{ company.ico }} | Kód: {{ company.bic }}</div>
                    </div>

                    <div class="mt-4">
                        <div v-if="loadingStats">Načítavam štatistiky...</div>
                        <div v-else-if="stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="bg-white shadow rounded p-4 text-center">
                                <div class="text-2xl font-bold">{{ stats.branches }}</div>
                                <div>pobočiek</div>
                            </div>
                            <div class="bg-white shadow rounded p-4 text-center">
                                <div class="text-2xl font-bold">{{ stats.users }}</div>
                                <div>užívateľov</div>
                            </div>
                            <div class="bg-white shadow rounded p-4 text-center">
                                <div class="text-2xl font-bold">{{ stats.patients }}</div>
                                <div>pacientov</div>
                            </div>
                        </div>
                    </div>
                </TabPanel>
                <TabPanel value="branches">
                    <UniversalDataTable :options="branchOptions" />
                </TabPanel>
                <TabPanel value="users">
                    <UniversalDataTable :options="userOptions" />
                </TabPanel>
                <TabPanel value="patients">
                    <PatientDataTable :endpoint-url="companyPatientEndpoint" />
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>
