<script setup lang="ts">
import { ref, onMounted, computed, markRaw } from 'vue'
import router from '@/router'
import type { IModalContentProps } from '@/types/ui'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import type { Branch, Role, User } from '@/types/models'
import { useAuthStore } from '@/stores/auth'
import useModal from '@/composables/useModal'
import ChangePasswordModal from './ChangePasswordModal.vue'

const props = defineProps<IModalContentProps & { userId?: number; baseUrl?: string; companyId?: number }>()

const toast = useToast()
const auth = useAuthStore()
const { openModal } = useModal()

const roleNameMap: Record<string, string> = {
    'manager': 'Manažér',
    'nurse': 'Sestra',
    'branch_manager': 'Manažér pobočky'
}

const getRoleName = (role: Role | undefined): string => {
    if (!role) return ''
    return roleNameMap[role.position || ''] || role.position || role.name || ''
}

type BranchAssign = { branch_id?: number | null; working_time?: number | null; role_id?: number | null }

// Password handling:
// - On create: password is required (new user)
// - On edit: password field is always empty (backend doesn't return it for security)
//   Users can only set/change password, they cannot see the current one
const user = ref<Partial<User> & { role_id?: number | null; pin?: string | null }>({
    first_name: '',
    last_name: '',
    title: '',
    code: '',
    phone_number: '',
    email: '',
    login: '',
    pin: undefined,
    role_id: null,
})

const branchAssignments = ref<BranchAssign[]>([])
const branchOptions = ref<Branch[]>([])
const branchRoles = ref<Role[]>([])
const globalRoles = ref<Role[]>([])

const translatedBranchRoles = computed(() =>
    branchRoles.value.map(r => ({
        ...r,
        name: getRoleName(r)
    }))
)

const translatedGlobalRoles = computed(() =>
    globalRoles.value.map(r => ({
        ...r,
        name: getRoleName(r)
    }))
)

const showPin = ref(false)
const submitted = ref(false)
const emailError = ref<string | null>(null)
const deleteConfirmVisible = ref(false)
const pendingDeleteIndex = ref<number | null>(null)

// use auth helper if you have it; otherwise keep this check
const isAdmin = computed(() => auth.user?.role?.position === 'admin')

// normalize numeric ids (PrimeVue Select won't match if you bind "1" vs 1)
function toNumberOrNull(v: any) {
    const n = Number(v)
    return Number.isFinite(n) ? n : null
}

function normalizeUserFromApi(data: any) {
    // role_id might not be present but role relation might be
    const roleId = data?.role_id ?? data?.role?.id ?? null

    user.value = {
        ...user.value,
        ...data,
        role_id: roleId != null ? toNumberOrNull(roleId) : null,
        // NEVER preload password for security; backend doesn't return it anyway
        pin: null,
    }

    // branch pivot role_id
    branchAssignments.value = (data?.branches ?? []).map((b: any) => ({
        branch_id: b?.id ?? null,
        working_time: b?.pivot?.working_time ?? null,
        role_id: b?.pivot?.role_id != null ? toNumberOrNull(b.pivot.role_id) : null,
    }))
}

onMounted(async () => {
    // Load lookup data first (so selects have options)
    try {
        const url = auth.isSuperadmin && router.currentRoute.value.params.companyId
            ? `v1/companies/${Number(router.currentRoute.value.params.companyId)}/branches`
            : 'v1/my-company/branches'
        branchOptions.value = await api.fetchEntities<Branch>(url)
        branchRoles.value = await api.fetchEntities<Role>('v1/roles/branch')
        globalRoles.value = await api.fetchEntities<Role>('v1/roles/all')
    } catch (e) {
        console.error('Nepodarilo sa načítať pobočky alebo role', e)
    }

    // Then load user data
    if (props.userId) {
        try {
            const data = await api.fetchEntity<User>(`v1/users/${props.userId}`)
            normalizeUserFromApi(data)
        } catch (e) {
            console.error('Nepodarilo sa načítať používateľa', e)
        }
    } else {
        // create mode: start with one empty assignment row optionally
        // branchAssignments.value = [{ branch_id: null, working_time: null, role_id: null }]
        // if companyId provided, make sure new user has it
        if (props.companyId) {
            user.value.company_id = props.companyId
        }
    }
})

const save = async () => {
    submitted.value = true
    emailError.value = null

    // Required fields:
    // - On create: require pin and login
    // - On update: password is managed in separate modal
    const creating = !props.userId

    if (
        !user.value.first_name ||
        !user.value.last_name ||
        !user.value.code ||
        (creating && !user.value.login) ||
        !user.value.email ||
        (creating && !user.value.pin)
    ) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Vyplňte povinné údaje', life: 5000 })
        return
    }

    try {
        const payload: Record<string, any> = { ...user.value }

        // Ensure role_id is numeric
        if (payload.role_id != null) payload.role_id = toNumberOrNull(payload.role_id)

        // On create: send pin, on update: password changes handled in separate modal
        if (creating) {
            // ensure company is set when creating from overview page
            if (props.companyId) payload.company_id = props.companyId
            if (!payload.pin) delete payload.pin
        } else {
            // On update: remove both pin and login (not editable)
            delete payload.pin
            delete payload.login
        }

        // remove role_id if caller isn't allowed
        if (!isAdmin.value) delete payload.role_id

        // include branch assignments (role_id stored on pivot)
        payload.branches = branchAssignments.value.map(b => ({
            branch_id: b.branch_id ?? null,
            working_time: b.working_time ?? null,
            role_id: b.role_id != null ? toNumberOrNull(b.role_id) : null,
        }))

        const resp = creating
            ? await api.post('v1/users/', payload)
            : await api.patch(`v1/users/${props.userId}`, payload)

        if (props.modalResolve) props.modalResolve(resp.data.data)
    } catch (err: any) {
        console.error('Nepodarilo sa uložiť používateľa', err)

        if (err.response?.data?.message?.includes('email') || err.response?.status === 422) {
            emailError.value = 'Tento email je už používaný.'
            toast.add({ severity: 'error', summary: 'Chyba', detail: 'Tento email je už používaný.', life: 5000 })
            return
        }

        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť používateľa', life: 3000 })
    }
}

function confirmDeleteAssignment(idx: number) {
    const assign = branchAssignments.value[idx]
    const hasData =
        (assign?.branch_id !== null && assign?.branch_id !== undefined) ||
        (assign?.working_time !== null && assign?.working_time !== undefined) ||
        (assign?.role_id !== null && assign?.role_id !== undefined)

    // Allow immediate deletion of empty rows
    if (!hasData) {
        branchAssignments.value.splice(idx, 1)
        return
    }

    // Require confirmation for rows with data
    pendingDeleteIndex.value = idx
    deleteConfirmVisible.value = true
}

function cancelDelete() {
    deleteConfirmVisible.value = false
    pendingDeleteIndex.value = null
}

async function confirmDelete() {
    const idx = pendingDeleteIndex.value
    if (idx === null) return

    const assign = branchAssignments.value[idx]
    if (!assign) return
    deleteConfirmVisible.value = false

    try {
        // Delete immediately if editing existing user
        if (props.userId && assign.branch_id) {
            await api.delete(`v1/users/${props.userId}/branches/${assign.branch_id}`)
            toast.add({ severity: 'success', summary: 'Zmazané', detail: 'Priradenie pobočky bolo zmazané', life: 3000 })
        }
        // Remove from UI
        branchAssignments.value.splice(idx, 1)
    } catch (err) {
        console.error('Nepodarilo sa odstrániť priradenie', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa odstrániť priradenie', life: 3000 })
    } finally {
        pendingDeleteIndex.value = null
    }
}

function togglePasswordVisibility() {
    showPin.value = !showPin.value
}

async function openChangePasswordModal() {
    await openModal(markRaw(ChangePasswordModal), { userId: props.userId }, { header: 'Zmeniť heslo', style: { width: '500px' } })
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Meno</label>
                <InputText v-model="user.first_name" class="w-full" />
                <small v-if="submitted && !user.first_name" class="text-warning">Meno je povinné.</small>
            </div>
            <div>
                <label class="block text-sm mb-1">Priezvisko</label>
                <InputText v-model="user.last_name" class="w-full" />
                <small v-if="submitted && !user.last_name" class="text-warning">Priezvisko je povinné.</small>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Titul</label>
                <InputText v-model="user.title" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Kód</label>
                <InputText v-model="user.code" class="w-full" />
                <small v-if="submitted && !user.code" class="text-warning">Kód je povinný.</small>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Telefón</label>
                <InputText v-model="user.phone_number" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Email</label>
                <InputText v-model="user.email" @input="emailError = null" class="w-full" />
                <small v-if="emailError" class="text-warning">{{ emailError }}</small>
                <small v-else-if="submitted && !user.email" class="text-warning">Email je povinný.</small>
            </div>
        </div>

        <h4 class="text-accent text-normal mt-2">Prihlasovacie údaje</h4>
        <div class="grid grid-cols-2 gap-4">
            <!-- Login field - only show on create -->
            <div v-if="!props.userId">
                <label class="block text-sm mb-1">Prihlasovacie meno</label>
                <InputText v-model="user.login" class="w-full" />
                <small v-if="submitted && !user.login" class="text-warning">Prihlasovacie meno je povinné.</small>
            </div>

            <!-- Password field - only show on create -->
            <div v-if="!props.userId">
                <label class="block text-sm mb-1">Heslo</label>
                <IconField class="flex items-center w-full">
                    <InputText v-model="user.pin" :type="showPin ? 'text' : 'password'" class="w-full" />
                    <InputIcon>
                        <i :class="showPin ? 'bi bi-eye' : 'bi bi-eye-slash'" class="cursor-pointer"
                            @click="togglePasswordVisibility" />
                    </InputIcon>
                </IconField>
                <small v-if="submitted && !user.pin" class="text-warning">Heslo je povinné.</small>
            </div>

            <!-- Change password button - show on edit -->
            <div v-if="props.userId" class="flex items-end col-span-2">
                <Button label="Zmeniť heslo" icon="bi bi-key"
                    class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white! w-full"
                    @click="openChangePasswordModal" />
            </div>
        </div>

        <!-- system/global role selection -->
        <div v-if="isAdmin" class="grid grid-cols-2 gap-4 mt-2">
            <div>
                <label class="block text-sm mb-1">Systémová rola</label>
                <Select v-model="user.role_id" :options="translatedGlobalRoles" optionLabel="name" optionValue="id"
                    class="w-full" />
            </div>
        </div>

        <h4 class="text-accent text-normal mt-2">Pobočky</h4>
        <div class="space-y-3">
            <div v-for="(assign, idx) in branchAssignments" :key="idx" class="grid grid-cols-12 gap-2 items-end">
                <div class="col-span-4">
                    <label class="block text-sm mb-1">Pobočka</label>
                    <Select v-model="assign.branch_id" :options="branchOptions" optionLabel="address" optionValue="id"
                        class="w-full" />
                </div>

                <div class="col-span-3">
                    <label class="block text-sm mb-1">Rola</label>
                    <Select v-model="assign.role_id" :options="translatedBranchRoles" optionLabel="name"
                        optionValue="id" class="w-full" />
                </div>

                <div class="col-span-4">
                    <label class="block text-sm mb-1">Úväzok</label>
                    <InputNumber v-model="assign.working_time" mode="decimal" locale="sk-SK" :min="0" :max="1"
                        :step="0.1" :minFractionDigits="1" :maxFractionDigits="2" :useGrouping="false" class="w-full" />
                </div>

                <div class="col-span-1">
                    <Button icon="bi bi-eraser" class="h-7! bg-warning!" severity="danger"
                        @click="confirmDeleteAssignment(idx)" />
                </div>
            </div>

            <div>
                <Button label="Pridať pobočku"
                    class="w-full! bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
                    @click="branchAssignments.push({ branch_id: null, working_time: null, role_id: null })" />
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <Button label="Zrušiť" text @click="props.modalResolve && props.modalResolve(null)"
                class="text-accent! px-2!" />
            <Button label="Uložiť"
                class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
                @click="save" />
        </div>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:visible="deleteConfirmVisible" :style="{ width: '500px' }" :modal="true" :closable="false"
            header="Upozornenie">
            <div class="flex items-center justify-between w-full">
                <span class="text-heading">
                    Priradenie obsahuje údaje. Naozaj ho chcete odstrániť?
                </span>

                <div class="flex items-center gap-2">
                    <Button label="Nie" text @click="cancelDelete"
                        class="!bg-accent !px-4 !text-white hover:!bg-darkgrey !border-0" />
                    <Button label="Áno" text @click="confirmDelete" class="!bg-warning !px-4 !text-white" />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.p-field {
    margin-bottom: 0.5rem;
}
</style>
