<script setup lang="ts">
import { ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'

interface SubscriptionTier {
    id?: number
    name: string
    price_monthly: number | null
    users_limit: number | null
    description: string | null
    is_active: boolean
    sort_order?: number
}

const props = defineProps<{
    tier?: Partial<SubscriptionTier> | null
    modalResolve?: (value?: any) => void
}>()

const emit = defineEmits(['save', 'close'])
const toast = useToast()
const saving = ref(false)
const submitted = ref(false)

const local = ref<SubscriptionTier>({
    name: '',
    price_monthly: null,
    users_limit: null,
    description: '',
    is_active: true,
})

watch(
    () => props.tier,
    (v) => {
        local.value = {
            id: v?.id,
            name: v?.name ?? '',
            price_monthly: v?.price_monthly ?? null,
            users_limit: v?.users_limit ?? null,
            description: v?.description ?? '',
            is_active: v?.is_active ?? true,
            sort_order: v?.sort_order,
        }
    },
    { immediate: true }
)

function close() {
    if (props.modalResolve) {
        try { props.modalResolve(undefined) } catch {}
    } else {
        emit('close')
    }
}

async function save() {
    submitted.value = true
    if (!local.value.name?.trim()) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Názov je povinný.', life: 3000 })
        return
    }

    saving.value = true
    try {
        const payload = {
            name: local.value.name.trim(),
            price_monthly: local.value.price_monthly,
            users_limit: local.value.users_limit,
            description: local.value.description ?? '',
            is_active: !!local.value.is_active,
            sort_order: local.value.sort_order,
        }

        if (local.value.id) {
            await api.put(`/v1/subscription-tiers/${local.value.id}`, payload)
        } else {
            await api.post('/v1/subscription-tiers', payload)
        }

        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Balík bol uložený.', life: 2500 })

        if (props.modalResolve) {
            props.modalResolve({ changed: true })
        } else {
            emit('save')
        }
    } catch (error) {
        console.error(error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť balík.', life: 3500 })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="grid grid-cols-12 gap-4 p-2">
        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Názov balíka</label>
            <InputText v-model.trim="local.name" fluid />
            <small v-if="submitted && !local.name" class="text-danger">Povinné pole</small>
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Cena / mesiac (€)</label>
            <InputNumber
                v-model="local.price_monthly"
                :min="0"
                :minFractionDigits="2"
                :maxFractionDigits="2"
                :useGrouping="false"
                locale="en-US"
                fluid
            />
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Limit používateľov</label>
            <InputNumber v-model="local.users_limit" :min="1" :useGrouping="false" fluid />
            <small class="text-lightgrey">Nechaj prázdne pre neobmedzený počet.</small>
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Aktívny</label>
            <div class="pt-2">
                <ToggleSwitch v-model="local.is_active" />
            </div>
        </div>

        <div class="col-span-12">
            <label class="block text-normal mb-1">Popis</label>
            <Textarea v-model="local.description" :rows="5" autoResize fluid />
        </div>

        <div class="col-span-12 mt-2 flex justify-end gap-2">
            <Button label="Zrušiť" text class="text-accent! px-2!" @click="close" />
            <Button
                :label="local.id ? 'Upraviť' : 'Vytvoriť'"
                :loading="saving"
                @click="save"
                class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
            />
        </div>
    </div>
</template>
