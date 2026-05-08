<template>
    <div class="flex flex-col gap-4">
        <div>
            <label class="block text-sm mb-2">Email príjemcu</label>
            <InputText v-model="recipientEmail" type="email" class="w-full" placeholder="napr. meno@firma.sk"
                :disabled="sending" />
        </div>

        <div>
            <label class="block text-sm mb-2">Vybrané položky ({{ props.documents.length }})</label>

            <div class="max-h-64 overflow-auto border rounded-md bg-white border-none">
                <div v-for="doc in props.documents" :key="doc.id"
                    class="flex items-start gap-3 p-3 rounded-md border border-lightgrey mb-2 last:mb-0">
                    <div class="flex items-center justify-center w-10 h-10 rounded-md bg-tag3 shrink-0">
                        <i class="bi bi-file-earmark text-lg text-accent"></i>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-darkgrey">{{ formatDocumentType(doc.type) }}</div>
                        <div class="text-xs text-darkgrey mt-1">Vytvoril: {{ doc.created_by_user || '-' }}</div>
                    </div>
                </div>

                <div v-if="props.documents.length === 0" class="text-sm text-darkgrey text-center py-6">
                    Nie sú vybrané žiadne položky.
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-2">
            <Button label="Zrušiť" text :disabled="sending" @click="close" class="text-accent!" />
            <Button :label="props.buttonLabel || 'Odoslať'" :loading="sending"
                :disabled="sending || props.documents.length === 0"
                class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! px-2!"
                @click="onSendClick" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const formatDocumentType = (type?: string) => {
    const typeMap: Record<string, string> = {
        cp: 'Cestovný príkaz',
        dzc: 'Denný záznam ciest',
        kilometers: 'Kilometráž',
        kilometers_batch: 'Dávka kilometrov',
        points_batch: 'Dávka bodov',
        proposal: 'Návrh',
        agreement: 'Dohoda',
        dekurz: 'Dekurz',
        leave: 'Prepúšťacia správa',
        record: 'Ošetrovateľský záznam',
        scan: 'Lekársky nález',
        invoice: 'Faktúra',
        procedures: 'Faktúra (výkonová)',
        transport: 'Faktúra (dopravná)',
        credit_note: 'Dobropis',
        debit_note: 'Ťarchopis',
    }
    return typeMap[type || ''] || type || ''
}

type Document = {
    id: number
    type?: string
    created_by_user?: string
}

const props = defineProps<{
    documents: Document[]
    onSend?: (payload: Record<string, unknown>) => Promise<void>
    apiEndpoint?: string
    apiMethod?: 'post' | 'put' | 'patch' | 'delete'
    apiPayload?: Record<string, unknown>
    idKey?: 'ids' | 'invoice_ids'
    buttonLabel?: string
    successMessage?: string
    modalResolve?: (value?: any) => void
}>()
const emit = defineEmits(['sent'])

const recipientEmail = ref('')
const sending = ref(false)
const toast = useToast()

const close = () => {
    recipientEmail.value = ''
    if (props.modalResolve) {
        props.modalResolve(null)
    }
}

async function sendRequest(payload: Record<string, unknown>) {
    if (props.onSend) {
        return props.onSend(payload)
    }

    if (!props.apiEndpoint) {
        return Promise.resolve()
    }

    const fullPayload = {
        ...payload,
        ...(props.apiPayload ?? {}),
    }

    switch (props.apiMethod ?? 'post') {
        case 'post':
            return api.post(props.apiEndpoint, fullPayload)
        case 'put':
            return api.put(props.apiEndpoint, fullPayload)
        case 'patch':
            return api.patch(props.apiEndpoint, fullPayload)
        case 'delete':
            return api.delete(props.apiEndpoint, { data: fullPayload })
        default:
            return Promise.reject(new Error('Unsupported apiMethod'))
    }
}

const onSendClick = async () => {
    const email = recipientEmail.value.trim()
    if (!emailRegex.test(email)) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Neplatná emailová adresa', life: 3000 })
        return
    }

    if (!props.documents || props.documents.length === 0) {
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nie sú vybrané žiadne dokumenty', life: 3000 })
        return
    }

    sending.value = true
    try {
        const idsKey = props.idKey ?? 'ids'
        const payload = { email, [idsKey]: props.documents.map((d) => d.id) }
        await sendRequest(payload)

        toast.add({ severity: 'success', summary: 'Úspech', detail: props.successMessage ?? 'Email bol odoslaný', life: 3000 })
        emit('sent')
        close()
    } catch (err) {
        console.error('Error sending documents email from dialog:', err)
        toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa odoslať email', life: 3000 })
    } finally {
        sending.value = false
    }
}
</script>

<style scoped></style>
