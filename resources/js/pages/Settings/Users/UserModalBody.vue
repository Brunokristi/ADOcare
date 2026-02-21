<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import type { IModalContentProps } from '@/types/ui'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import type { Branch, Role, User } from '@/types/models'
import { useAuthStore } from '@/stores/auth'
import type Button from 'primevue/button'

const props = defineProps<IModalContentProps & { userId?: number; baseUrl?: string }>()

const toast = useToast()
const auth = useAuthStore()

const user = ref<Partial<User>>({ first_name: '', last_name: '', title: '', code: '', phone_number: '', email: '', login: '', pin: '' })
const branchAssignments = ref<Array<{ branch_id?: number | null; working_time?: number | null; role_id?: number | null }>>([])
const branchOptions = ref<Branch[]>([])
const branchRoles = ref<Role[]>([])
const globalRoles = ref<Role[]>([])
const showPin = ref(false)
const isAdmin = computed(() => auth.user?.role?.position === 'admin')

onMounted(async () => {
    if (props.userId) {
        try {
            const data = await api.fetchEntity<User>(`v1/users/${props.userId}`)
            user.value = data
            // branchAssignments now mirror pivot role stored on user_branches
            branchAssignments.value = (data.branches ?? []).map((b: any) => ({ branch_id: b.id, working_time: b.pivot?.working_time ?? null, role_id: b.pivot?.role_id ?? null }))
        } catch (e) {
            console.error('Nepodarilo sa načítať používateľa', e)
        }
    }
    try {
        branchOptions.value = await api.fetchEntities<Branch>('v1/my-company/branches')
        branchRoles.value = await api.fetchEntities<Role>('v1/roles/branch')
        globalRoles.value = await api.fetchEntities<Role>('v1/roles/all')
    } catch (e) {
        console.error('Nepodarilo sa načítať pobočky alebo role', e)
    }

    // primary/system role is stored in user.role_id (accessible as user.role when loaded);
    // branch-specific roles are on the user_branches pivot
})

const save = async () => {
    try {
        if (props.userId) {
            // don't send empty pin on update
            const payload: Record<string, any> = { ...user.value }
            if (!payload.pin) delete payload.pin
            // remove role_id if caller isn't allowed
            if (!isAdmin.value) delete payload.role_id
            // include branch assignments (role_id stored on pivot)
            payload.branches = branchAssignments.value.map(b => ({ branch_id: b.branch_id, working_time: b.working_time, role_id: b.role_id }))

            const resp = await api.patch(`v1/users/${props.userId}`, payload)
            if (props.modalResolve) props.modalResolve(resp.data.data)
            return
        }

        // create
        const payload: Record<string, any> = { ...user.value }
        if (!isAdmin.value) delete payload.role_id
        payload.branches = branchAssignments.value.map(b => ({ branch_id: b.branch_id, working_time: b.working_time, role_id: b.role_id }))
        const resp = await api.post('v1/users/', payload)
        if (props.modalResolve) props.modalResolve(resp.data.data)
    } catch (err) {
        console.error('Nepodarilo sa uložiť používateľa', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť používateľa', life: 3000 })
    }
}

function confirmDeleteAssignment(idx: number) {
    const assign = branchAssignments.value[idx]
    const hasData = (assign?.branch_id !== null && assign?.branch_id !== undefined) || (assign?.working_time !== null && assign?.working_time !== undefined)
    if (!hasData) {
        branchAssignments.value.splice(idx, 1)
        return
    }

    const ok = window.confirm('Priradenie obsahuje údaje. Naozaj ho chcete odstrániť?')
    if (ok) branchAssignments.value.splice(idx, 1)
}

function togglePasswordVisibility() {
    showPin.value = !showPin.value
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Meno</label>
                <InputText v-model="user.first_name" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Priezvisko</label>
                <InputText v-model="user.last_name" class="w-full" />
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
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Telefón</label>
                <InputText v-model="user.phone_number" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Email</label>
                <InputText v-model="user.email" class="w-full" />
            </div>
        </div>

        <h4 class="text-accent text-normal mt-2">Prihlásenie</h4>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Prihlasovacie meno</label>
                <InputText v-model="user.login" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Heslo</label>
                <IconField class="flex items-center w-full">
                    <InputText v-model="user.pin" :type="showPin ? 'text' : 'pin'" class="w-full" />
                    <InputIcon>
                        <i :class="showPin ? 'bi bi-eye' : 'bi bi-eye-slash'" class="cursor-pointer"
                            @click="togglePasswordVisibility" />
                    </InputIcon>
                </IconField>
            </div>
        </div>

        <!-- system/global role selection -->
        <div v-if="isAdmin" class="grid grid-cols-2 gap-4 mt-2">
            <div>
                <label class="block text-sm mb-1">Systémová rola</label>
                <Select v-model="user.role_id" :options="globalRoles" optionLabel="name" optionValue="id"
                    class="w-full" />
            </div>
        </div>

        <h4 class="text-accent text-normal mt-2">Priradenia pobočiek</h4>
        <div class="space-y-3">
            <div v-for="(assign, idx) in branchAssignments" :key="idx" class="grid grid-cols-12 gap-2 items-end">
                <div class="col-span-4">
                    <label class="block text-sm mb-1">Pobočka</label>
                    <Select v-model="assign.branch_id" :options="branchOptions" optionLabel="address" optionValue="id"
                        class="w-full" />
                </div>
                <div class="col-span-3">
                    <label class="block text-sm mb-1">Rola</label>
                    <Select v-model="assign.role_id" :options="branchRoles" optionLabel="position" optionValue="id"
                        class="w-full" />
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
                    class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
                    @click="branchAssignments.push({ branch_id: null, working_time: null, role_id: null })" />
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <Button label="Zrušiť" text @click="props.modalResolve && props.modalResolve(null)"
                class="text-accent! px-2!" />
            <Button label="Uložiť"
                class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white! "
                @click="save" />
        </div>
    </div>
</template>

<style scoped>
.p-field {
    margin-bottom: 0.5rem;
}
</style>
