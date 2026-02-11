import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { usePatientStore } from '@/stores/patientStore'
import { formatBranchFullName } from '@/utils/formatUtils'

type BranchOption = { id: number; label: string; role: string }

export default function useBranches() {
    const authStore = useAuthStore()
    const patientStore = usePatientStore()

    const selectedBranchId = ref<number | null>(null)

    const branchOptions = computed<BranchOption[]>(() => {
        const userInfo = authStore.user
        if (!userInfo) return []

        const branchRoleMap = new Map<number, string>()
        userInfo.branch_roles.forEach((br) => {
            if (br.branch_id && br.position) {
                branchRoleMap.set(br.branch_id, br.position)
            }
        })

        const options: BranchOption[] = (userInfo.branches ?? []).map((branch) => ({
            id: branch.id,
            label: formatBranchFullName(branch),
            role: branchRoleMap.get(branch.id) ?? 'nurse',
        }))

        const userRoles: string[] = userInfo.role_names ?? []
        const hasManager = userRoles.some((role) => role && role.trim().toLowerCase() === 'manager')

        if (hasManager) {
            options.push({ id: -1, label: 'Manažér', role: 'manager' })
        }

        return options
    })

    async function applyBranchSelection(id: number) {
        const opt = branchOptions.value.find((o) => o.id === id)
        if (!opt) return
        patientStore.clear()

        authStore.setCurrentRole(opt.role)
        selectedBranchId.value = id
        if (opt.role === 'manager') {
            authStore.clearCurrentBranch()
            return;
        }

        authStore.setCurrentBranchById(id);
    }

    return {
        branchOptions,
        selectedBranchId,
        applyBranchSelection,
    }
}
