<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'

interface SubscriptionTier {
    id: number
    name: string
    price_monthly: number | null
    users_limit: number | null
    is_active: boolean
}

interface CompanySubscription {
    id: number
    name: string
    subscription_tier_id: number | null
    subscription_price_monthly: number | null
    subscription_users_limit_override: number | null
    subscription_status: 'active' | 'trial' | 'paused' | 'cancelled'
    subscription_started_at: string | null
    subscription_ends_at: string | null
    subscription_notes: string | null
}

const props = defineProps<{
    company: CompanySubscription
    modalResolve?: (value?: any) => void
}>()

const emit = defineEmits(['save', 'close'])
const toast = useToast()

const saving = ref(false)
const loadingTiers = ref(false)
const tiers = ref<SubscriptionTier[]>([])

const statusOptions = [
    { label: 'Aktívne', value: 'active' },
    { label: 'Trial', value: 'trial' },
    { label: 'Pozastavené', value: 'paused' },
    { label: 'Zrušené', value: 'cancelled' },
]

const local = ref<CompanySubscription>({
    ...props.company,
})

onMounted(async () => {
    loadingTiers.value = true
    try {
        tiers.value = await api.fetchEntities<SubscriptionTier>('v1/subscription-tiers', {
            filter: { is_active: 1 },
            sort: 'sort_order,name',
        })
    } catch (error) {
        console.error(error)
        tiers.value = []
    } finally {
        loadingTiers.value = false
    }
})

function close() {
    if (props.modalResolve) {
        try { props.modalResolve(undefined) } catch {}
    } else {
        emit('close')
    }
}

function toDateInputValue(v: string | null | undefined): string | null {
    if (!v) return null
    return String(v).slice(0, 10)
}

async function save() {
    saving.value = true
    try {
        await api.put(`/v1/companies/${local.value.id}/subscription`, {
            subscription_tier_id: local.value.subscription_tier_id,
            subscription_price_monthly: local.value.subscription_price_monthly,
            subscription_users_limit_override: local.value.subscription_users_limit_override,
            subscription_status: local.value.subscription_status,
            subscription_started_at: toDateInputValue(local.value.subscription_started_at),
            subscription_ends_at: toDateInputValue(local.value.subscription_ends_at),
            subscription_notes: local.value.subscription_notes,
        })

        toast.add({ severity: 'success', summary: 'Uložené', detail: 'Predplatné bolo aktualizované.', life: 2500 })

        if (props.modalResolve) {
            props.modalResolve({ changed: true })
        } else {
            emit('save')
        }
    } catch (error) {
        console.error(error)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť predplatné.', life: 3500 })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="grid grid-cols-12 gap-4 p-2">
        <div class="col-span-12">
            <label class="block text-normal mb-1">Spoločnosť</label>
            <InputText :modelValue="local.name" disabled fluid />
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Balík</label>
            <Select
                v-model="local.subscription_tier_id"
                :options="tiers"
                optionLabel="name"
                optionValue="id"
                :loading="loadingTiers"
                placeholder="Vyber balík"
                showClear
                fluid
            />
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Stav predplatného</label>
            <Select
                v-model="local.subscription_status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                fluid
            />
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Cena / mesiac (€) - override</label>
            <InputNumber
                v-model="local.subscription_price_monthly"
                :min="0"
                :minFractionDigits="2"
                :maxFractionDigits="2"
                :useGrouping="false"
                locale="en-US"
                fluid
            />
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Limit používateľov - override</label>
            <InputNumber v-model="local.subscription_users_limit_override" :min="1" :useGrouping="false" fluid />
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Začiatok predplatného</label>
            <InputText v-model="local.subscription_started_at" type="date" fluid />
        </div>

        <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Koniec predplatného</label>
            <InputText v-model="local.subscription_ends_at" type="date" fluid />
        </div>

        <div class="col-span-12">
            <label class="block text-normal mb-1">Poznámky</label>
            <Textarea v-model="local.subscription_notes" :rows="4" autoResize fluid />
        </div>

        <div class="col-span-12 mt-2 flex justify-end gap-2">
            <Button label="Zrušiť" text class="text-accent! px-2!" @click="close" />
            <Button
                label="Uložiť"
                :loading="saving"
                @click="save"
                class="bg-accent! border-accent! px-2! hover:bg-darkgrey! hover:border-darkgrey! text-white!"
            />
        </div>
    </div>
</template>
