<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

// We'll use a dynamic component approach to reuse existing "Show" components
import DocumentInvoice from '@/pages/Documents/Invoice.vue'
import DocumentPointsShow from '@/pages/Documents/PointsShow.vue'
import DocumentKilometersShow from '@/pages/Documents/KilometersShow.vue'
import DocumentCP from '@/pages/Documents/CP.vue'
import DocumentDZC from '@/pages/Documents/DZC.vue'
import DocumentProposal from '@/pages/Documents/Proposal.vue'
import DocumentAgreement from '@/pages/Documents/Agreement.vue'
import DocumentLeave from '@/pages/Documents/Leave.vue'
import DocumentRecord from '@/pages/Documents/Record.vue'
import DocumentNalez from '@/pages/Documents/Nalez.vue'
import DocumentDekurz from '@/pages/Documents/Dekurz.vue'

const route = useRoute()
const loading = ref(true)
const error = ref<string | null>(null)
const documentType = ref<string | null>(null)
const documentData = ref<any>(null)

// The signed URL parameters
const main_signature = computed(() => route.query.main_signature as string)
const data_signature = computed(() => route.query.data_signature as string)
const expires = computed(() => route.query.expires as string)

const isInvoice = computed(() => route.name === 'public-invoice-view')

onMounted(async () => {
    await loadData()
})

async function loadData() {
    loading.value = true
    error.value = null

    const id = route.params.documentId
    const baseUrl = isInvoice.value ? `/api/public/invoices/${id}/data` : `/api/public/documents/${id}/data`

    try {
        const response = await axios.get(baseUrl, {
            params: {
                signature: data_signature.value,
                expires: expires.value
            }
        })
        documentData.value = response.data.data
        documentType.value = isInvoice.value ? 'invoice' : documentData.value.type
    } catch (err: any) {
        console.error('Failed to load public document data', err)
        error.value = err.response?.data?.message || 'Nepodarilo sa načítať dokument. Odkaz môže byť neplatný alebo vypršaný.'
    } finally {
        loading.value = false
    }
}

const activeComponent = computed(() => {
    if (!documentType.value) return null

    switch (documentType.value) {
        case 'invoice': return DocumentInvoice
        case 'points_batch': return DocumentPointsShow
        case 'kilometers_batch': return DocumentKilometersShow
        case 'cp': return DocumentCP
        case 'dzc': return DocumentDZC
        case 'proposal': return DocumentProposal
        case 'agreement': return DocumentAgreement
        case 'leave': return DocumentLeave
        case 'record': return DocumentRecord
        case 'scan': return DocumentNalez
        case 'dekurz': return DocumentDekurz
        default: return null
    }
})

// We need to provide the data to the components.
// Most components currently fetch their own data using route.params.documentId.
// We'll need to refactor them slightly or "mock" the API they use.
// For now, let's see if we can pass it via props if they support it,
// OR use a Provider/Injection pattern.
</script>

<template>
    <div class="public-viewer min-h-screen">

        <div v-if="loading" class="flex flex-col items-center justify-center py-20">
            <i class="bi bi-arrow-repeat animate-spin text-4xl text-accent mb-4"></i>
            <p class="text-gray-600">Načítavam dokument...</p>
        </div>

        <div v-else-if="error" class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md border-t-4 border-danger">
            <h1 class="text-2xl font-bold text-danger mb-4">Chyba</h1>
            <p class="text-gray-700 mb-6">{{ error }}</p>
            <div class="text-sm text-gray-500">
                Ak si myslíte, že ide o chybu, kontaktujte odosielateľa.
            </div>
        </div>

        <component :is="activeComponent" :public-data="documentData" :is-public="true" :signature="main_signature"
            :expires="expires" />
    </div>
</template>

<style scoped>
.public-viewer {
    font-family: 'Inter', sans-serif;
}
</style>
