<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { IModalContentProps } from '@/types/ui'
import api from '@/services/api'
import { useToast } from 'primevue/usetoast'
import type { Branch, Role, User } from '@/types/models'
import type Button from 'primevue/button'

const props = defineProps<IModalContentProps & { userId?: number; baseUrl?: string }>()

const toast = useToast()

const user = ref<Partial<User & { password: string }>>({ first_name: '', last_name: '', title: '', code: '', phone_number: '', email: '', login: '', password: '' })
const branchAssignments = ref<Array<{ branch_id?: number | null; working_time?: number | null; role_id?: number | null }>>([])
const branchOptions = ref<Branch[]>([])
const branchRoles = ref<Role[]>([])

onMounted(async () => {
    if (props.userId) {
        try {
            const data = await api.fetchEntity<User>(`v1/users/${props.userId}`)
            user.value = data
            // populate branch assignments from loaded relation (use pivot.working_time)
            branchAssignments.value = (data.branches ?? []).map((b: any) => ({ branch_id: b.id, working_time: b.pivot?.working_time ?? null, role_id: b.pivot?.role_id ?? null }))
        } catch (e) {
            console.error('Nepodarilo sa načítať používateľa', e)
        }
    }
    try {
        branchOptions.value = await api.fetchEntities<Branch>('v1/my-company/branches')
        branchRoles.value = await api.fetchEntities<Role>('v1/roles/branch')
    } catch (e) {
        console.error('Nepodarilo sa načítať pobočky', e)
    }

    // roles are managed via user_roles; not part of per-branch pivot
})

const save = async () => {
    try {
        if (props.userId) {
            // don't send empty password on update
            const payload: Record<string, any> = { ...user.value }
            if (!payload.password) delete payload.password
            // include branch assignments (working_time stored on pivot)
            payload.branches = branchAssignments.value.map(b => ({ branch_id: b.branch_id, working_time: b.working_time, role_id: b.role_id }))

            const resp = await api.patch(`v1/users/${props.userId}`, payload)
            if (props.modalResolve) props.modalResolve(resp.data.data)
            return
        }

        // create
        const payload: Record<string, any> = { ...user.value }
        payload.branches = branchAssignments.value.map(b => ({ branch_id: b.branch_id, working_time: b.working_time, role_id: b.role_id }))
        const resp = await api.post('v1/my-company/users', payload)
        if (props.modalResolve) props.modalResolve(resp.data.data)
    } catch (err) {
        console.error('Nepodarilo sa uložiť používateľa', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť používateľa' })
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

        <h4 class="mt-2">Prihlásenie</h4>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Prihlasovacie meno</label>
                <InputText v-model="user.login" class="w-full" />
            </div>
            <div>
                <label class="block text-sm mb-1">Heslo</label>
                <InputText v-model="user.password" type="password" class="w-full" />
            </div>
        </div>

        <h4 class="mt-2">Priradenia pobočiek</h4>
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
                        class="w-full" showClear />
                </div>
                <div class="col-span-4">
                    <label class="block text-sm mb-1">Úväzok</label>
                    <InputNumber v-model="assign.working_time" :min="0" :max="1" :step="0.1" class="w-full" />
                </div>
                <div class="col-span-1">
                    <Button icon="bi bi-trash" class="h-7!" severity="danger" @click="confirmDeleteAssignment(idx)" />
                </div>
            </div>
            <div>
                <Button label="Pridať pobočku" class="p-button-text"
                    @click="branchAssignments.push({ branch_id: null, working_time: null, role_id: null })" />
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <Button label="Zrušiť" text @click="props.modalResolve && props.modalResolve(null)" />
            <Button label="Uložiť" class="bg-accent!" @click="save" />
        </div>
    </div>
</template>

<style scoped>
.p-field {
    margin-bottom: 0.5rem;
}
</style>
