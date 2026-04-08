<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { usePatientStore } from '@/stores/patientStore'
import { useUiOverlayStore } from '@/stores/uiOverlay'

const patientStore = usePatientStore()
const route = useRoute()
const uiOverlayStore = useUiOverlayStore()

const loading = ref(false)
const isPrinting = ref(false)
const signatureUrl = ref<string | null>(null)

type DekurzDay = {
    date: string
    text: string
    terrain_time?: string
    administrative_time?: string
}

type DekurzData = {
    document_id: number
    created_at: string
    user_id: number
    user_name?: string
    company_name?: string
    company_address?: string
    insurance_code?: string
    patient_personal_number?: string
    patient_address?: string
    patient_id: number
    patient_name: string
    dekurz_number: string
    month: string
    sections: { text: string; dates: string[] }[]
    days: DekurzDay[]
}

type DekurzRow = {
    date: string
    leftDateTime: string
    rightTime: string
    text: string
    nurseName: string
}

const dekurz = ref<DekurzData>({
    document_id: 0,
    created_at: '',
    user_id: 0,
    user_name: '',
    company_name: '',
    company_address: '',
    insurance_code: '',
    patient_personal_number: '',
    patient_address: '',
    patient_id: 0,
    patient_name: '',
    dekurz_number: '1',
    month: '',
    sections: [],
    days: [],
})

const pagedRows = ref<DekurzRow[][]>([])
const measureRows = ref<DekurzRow[]>([])

const measurePageInnerRef = ref<HTMLElement | null>(null)

let resizeTimer: number | null = null

onMounted(async () => {
    window.addEventListener('afterprint', handleAfterPrint)
    window.addEventListener('resize', handleResize)
    await loadDekurz(String(route.params.documentId))
})

onBeforeUnmount(() => {
    window.removeEventListener('afterprint', handleAfterPrint)
    window.removeEventListener('resize', handleResize)

    if (resizeTimer) {
        window.clearTimeout(resizeTimer)
    }

    if (signatureUrl.value) {
        URL.revokeObjectURL(signatureUrl.value)
    }
})

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value)
})

function handleResize() {
    if (resizeTimer) {
        window.clearTimeout(resizeTimer)
    }

    resizeTimer = window.setTimeout(() => {
        void recalcPagination()
    }, 120)
}

async function loadDekurz(documentId: string) {
    loading.value = true
    try {
        const res = await api.get(`/v1/dekurz/${documentId}`)
        dekurz.value = res.data?.dekurz_data ?? dekurz.value
        await loadSignatureImage()
    } catch (error) {
        console.error('Failed to load Dekurz:', error)
    } finally {
        loading.value = false

        await nextTick()
        await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
        await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
        await recalcPagination()
        await persistNextDekurzNumber()
    }
}
async function waitForImageLoad(src: string) {
    await new Promise<void>((resolve) => {
        const img = new Image()
        img.onload = () => resolve()
        img.onerror = () => resolve()
        img.src = src
    })
}

async function waitForImagesInElement(root: ParentNode | null) {
    if (!root) return

    const images = Array.from(root.querySelectorAll('img'))

    await Promise.all(
        images.map(
            (img) =>
                new Promise<void>((resolve) => {
                    const image = img as HTMLImageElement
                    if (image.complete) {
                        resolve()
                        return
                    }
                    image.addEventListener('load', () => resolve(), { once: true })
                    image.addEventListener('error', () => resolve(), { once: true })
                })
        )
    )
}

async function waitForFonts() {
    try {
        if ('fonts' in document) {
            await (document as Document & { fonts: FontFaceSet }).fonts.ready
        }
    } catch {
        // ignore
    }
}

async function loadSignatureImage() {
    const representativeId = dekurz.value.user_id

    if (!representativeId) {
        signatureUrl.value = null
        await recalcPagination()
        return
    }

    try {
        if (signatureUrl.value) {
            URL.revokeObjectURL(signatureUrl.value)
            signatureUrl.value = null
        }

        const res = await api.get(`/v1/users/${representativeId}/signature`, {
            responseType: 'blob',
        })

        signatureUrl.value = URL.createObjectURL(res.data)

        if (signatureUrl.value) {
            await waitForImageLoad(signatureUrl.value)
        }

        await recalcPagination()
    } catch (error) {
        console.error('Failed to load signature image:', error)
        signatureUrl.value = null
        await recalcPagination()
    }
}

function formatDateSK(v?: string) {
    if (!v) return ''
    return new Date(v).toLocaleDateString('sk-SK')
}

function formatTimeSKFromDatetime(v?: string) {
    if (!v) return ''
    const parts = String(v).split(' ')
    if (parts.length < 2) return ''
    const timePart = parts[1] ?? ''
    const [hh, mm] = timePart.split(':')
    if (!hh || !mm) return ''
    return `${hh}:${mm}`
}

function safeText(t?: string) {
    return (t ?? '').trim()
}

const rows = computed<DekurzRow[]>(() => {
    const src = [...(dekurz.value.days || [])].sort((a, b) => (a.date || '').localeCompare(b.date || ''))

    return src.map((d) => {
        const left = d.terrain_time || d.administrative_time || `${d.date} 00:00:00`
        const right = d.administrative_time || d.terrain_time || `${d.date} 00:00:00`

        return {
            date: d.date,
            leftDateTime: `${formatDateSK(d.date)}\n${formatTimeSKFromDatetime(left)}`,
            rightTime: formatTimeSKFromDatetime(right),
            text: safeText(d.text),
            nurseName: dekurz.value.user_name || '',
        }
    })
})

const baseDekurzNumber = computed(() => {
    const n = Number(dekurz.value.dekurz_number ?? 1)
    return Number.isFinite(n) && n > 0 ? n : 1
})

function pageDekurzNumber(pageIdx: number) {
    return baseDekurzNumber.value + pageIdx
}

const lastDekurzNumber = computed(() => {
    const pages = pagedRows.value.length
    if (!pages) return baseDekurzNumber.value
    return baseDekurzNumber.value + (pages - 1)
})

const nextReservedDekurzNumber = computed(() => lastDekurzNumber.value + 1)

async function prepareMeasurement() {
    await nextTick()
    await waitForFonts()
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))

    const measureRoot = document.getElementById('measure-root')
    await waitForImagesInElement(measureRoot)

    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
}

function measurementOverflowed() {
    const inner = measurePageInnerRef.value
    if (!inner) return false
    return inner.scrollHeight > inner.clientHeight + 1
}

async function recalcPagination() {
    await prepareMeasurement()

    if (!rows.value.length) {
        measureRows.value = []
        pagedRows.value = [[]]
        return
    }

    const pages: DekurzRow[][] = []
    let currentPage: DekurzRow[] = []

    for (const row of rows.value) {
        currentPage.push(row)
        measureRows.value = [...currentPage]

        await prepareMeasurement()

        if (measurementOverflowed()) {
            currentPage.pop()

            if (currentPage.length === 0) {
                pages.push([row])
                currentPage = []
                measureRows.value = []
                await prepareMeasurement()
                continue
            }

            pages.push([...currentPage])
            currentPage = [row]
            measureRows.value = [...currentPage]

            await prepareMeasurement()
        }
    }

    if (currentPage.length) {
        pages.push([...currentPage])
    }

    measureRows.value = currentPage.length ? [...currentPage] : []
    pagedRows.value = pages.length ? pages : [[]]
}

async function persistNextDekurzNumber() {
    const patientId = dekurz.value.patient_id
    if (!patientId) return

    try {
        await api.put(`/v1/patients/${patientId}`, {
            dekurz_number: String(nextReservedDekurzNumber.value),
        })
        await patientStore.fetchPatient(patientId)
    } catch (e) {
        console.error('Failed to update patient dekurz_number:', e)
    }
}

watch(
    () => rows.value,
    async () => {
        await recalcPagination()
    },
    { deep: true }
)

watch(
    () => signatureUrl.value,
    async () => {
        await recalcPagination()
    }
)

async function printPage() {
    isPrinting.value = true

    await recalcPagination()

    const src = document.getElementById('print-root')
    if (!src) {
        isPrinting.value = false
        return
    }

    const iframe = document.createElement('iframe')
    iframe.style.position = 'fixed'
    iframe.style.right = '0'
    iframe.style.bottom = '0'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = '0'
    iframe.style.opacity = '0'
    document.body.appendChild(iframe)

    const doc = iframe.contentDocument || iframe.contentWindow?.document
    const win = iframe.contentWindow

    if (!doc || !win) {
        document.body.removeChild(iframe)
        isPrinting.value = false
        return
    }

    const headPieces: string[] = []

    document.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
        const href = (link as HTMLLinkElement).href
        if (href) headPieces.push(`<link rel="stylesheet" href="${href}">`)
    })

    document.querySelectorAll('style').forEach((style) => {
        headPieces.push(`<style>${style.innerHTML}</style>`)
    })

    headPieces.push(`
        <style>
            @page { size: A4; margin: 0; }
            html, body {
                margin: 0;
                padding: 0;
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #print-root {
                position: static !important;
                display: block !important;
                width: 100% !important;
            }

            .pages-wrapper {
                padding: 0 !important;
                gap: 0 !important;
            }

            .dekurz-page {
                break-after: page;
                page-break-after: always;
                margin: 0 auto !important;
                box-shadow: none !important;
                overflow: hidden !important;
            }

            .dekurz-page:last-child {
                break-after: auto;
                page-break-after: auto;
            }
        </style>
    `)

    doc.open()
    doc.write(`
        <!doctype html>
        <html>
            <head>
                <meta charset="utf-8" />
                ${headPieces.join('\n')}
            </head>
            <body>
                ${src.outerHTML}
            </body>
        </html>
    `)
    doc.close()

    const links = Array.from(doc.querySelectorAll('link[rel="stylesheet"]')) as HTMLLinkElement[]
    await Promise.all(
        links.map(
            (link) =>
                new Promise<void>((resolve) => {
                    if ((link as HTMLLinkElement).sheet) {
                        resolve()
                        return
                    }
                    link.addEventListener('load', () => resolve(), { once: true })
                    link.addEventListener('error', () => resolve(), { once: true })
                })
        )
    )

    try {
        if ('fonts' in doc) {
            await (doc as Document & { fonts: FontFaceSet }).fonts.ready
        }
    } catch {
        // ignore
    }

    await waitForImagesInElement(doc)
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))

    win.focus()
    win.print()

    setTimeout(() => {
        try {
            document.body.removeChild(iframe)
        } catch {
            // ignore
        }
        isPrinting.value = false
    }, 500)
}

function handleAfterPrint() {
    isPrinting.value = false
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
            <template #start>
                <span class="text-heading-accent">Dekurz ošetrovateľskej starostlivosti</span>
            </template>
            <template #end>
                <Button
                    icon="bi bi-printer"
                    class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
                    :disabled="loading || isPrinting"
                    @click="printPage"
                />
            </template>
        </Toolbar>

        <div v-if="!loading" class="pages-wrapper">
            <div id="print-root">
                <div v-for="(page, pageIdx) in pagedRows" :key="pageIdx" class="dekurz-page">
                    <div class="page-inner">
                        <div class="text-center font-bold text-lg page-title">
                            DEKURZ OŠETROVATEĽSKEJ STAROSTLIVOSTI
                        </div>

                        <table class="w-full border-collapse dekurz-table table-fixed mb-2">
                            <colgroup>
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                            </colgroup>
                            <tbody>
                                <tr>
                                    <td class="border border-black p-2" colspan="4">
                                        <div class="text-normal"><strong>{{ dekurz.company_name }}</strong></div>
                                        <div class="text-normal">{{ dekurz.company_address }}</div>
                                        <div class="text-normal">Agentúra domácej ošetrovateľskej starostlivosti</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-2 align-top" colspan="2">
                                        <div class="text-normal">Meno, priezvisko, titul pacienta/pacientky:</div>
                                        <div class="font-normal"><strong>{{ dekurz.patient_name }}</strong></div>
                                    </td>
                                    <td class="border border-black p-2 align-top">
                                        <div class="text-normal">Rodné číslo:</div>
                                        <div class="font-normal">
                                            <strong>{{ dekurz.patient_personal_number || '—' }}</strong>
                                        </div>
                                    </td>
                                    <td class="border border-black p-2 align-top">
                                        <div class="text-normal">Poisťovňa:</div>
                                        <div class="font-normal"><strong>{{ dekurz.insurance_code || '—' }}</strong></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-2 align-top" colspan="3">
                                        <div class="text-normal">Adresa pacienta/pacientky:</div>
                                        <div class="font-normal"><strong>{{ dekurz.patient_address }}</strong></div>
                                    </td>
                                    <td class="border border-black p-2 align-top">
                                        <div class="text-normal">Poradové číslo dekurzu:</div>
                                        <div class="font-normal"><strong>{{ pageDekurzNumber(pageIdx) }}</strong></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="w-full border-collapse dekurz-table table-fixed">
                            <colgroup>
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                            </colgroup>
                            <tbody>
                                <tr>
                                    <td class="border border-black p-2 align-top text-xs font-bold">
                                        Dátum a<br />
                                        čas zápisu:
                                    </td>
                                    <td class="border border-black p-2 align-top text-xs font-bold" colspan="3">
                                        Rozsah poskytnutej ZS a služieb súvisiacich s poskytnutím ZS, identifikácia
                                        ošetrujúceho zdravotného pracovníka (meno, priezvisko, odtlačok pečiatky a podpis)
                                    </td>
                                </tr>

                                <tr v-for="(row, rowIdx) in page" :key="`${pageIdx}-${rowIdx}-${row.date}`">
                                    <td class="border border-black p-2 align-top">
                                        <div class="whitespace-pre-line text-normal">{{ row.leftDateTime }}</div>
                                    </td>
                                    <td class="border border-black p-2 align-top" colspan="3">
                                        <div class="text-normal leading-snug">
                                            <span class="font-normal">{{ row.rightTime }}: </span>
                                            <span class="whitespace-pre-line">{{ row.text }}</span>
                                        </div>

                                        <div class="mt-2 row-signature">
                                            <div class="text-sm"><strong>{{ row.nurseName }}</strong></div>
                                            <img
                                                v-if="signatureUrl"
                                                :src="signatureUrl"
                                                alt="Podpis"
                                                class="signature-image"
                                            />
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="!page.length">
                                    <td class="border border-black p-4 text-sm" colspan="4">Žiadne záznamy.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="measure-root" aria-hidden="true">
                <div class="dekurz-page measure-page">
                    <div ref="measurePageInnerRef" class="page-inner">
                        <div class="text-center font-bold text-lg page-title">
                            DEKURZ OŠETROVATEĽSKEJ STAROSTLIVOSTI
                        </div>

                        <table class="w-full border-collapse dekurz-table table-fixed mb-2">
                            <colgroup>
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                            </colgroup>
                            <tbody>
                                <tr>
                                    <td class="border border-black p-2" colspan="4">
                                        <div class="text-normal"><strong>{{ dekurz.company_name }}</strong></div>
                                        <div class="text-normal">{{ dekurz.company_address }}</div>
                                        <div class="text-normal">Agentúra domácej ošetrovateľskej starostlivosti</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-2 align-top" colspan="2">
                                        <div class="text-normal">Meno, priezvisko, titul pacienta/pacientky:</div>
                                        <div class="font-normal"><strong>{{ dekurz.patient_name }}</strong></div>
                                    </td>
                                    <td class="border border-black p-2 align-top">
                                        <div class="text-normal">Rodné číslo:</div>
                                        <div class="font-normal">
                                            <strong>{{ dekurz.patient_personal_number || '—' }}</strong>
                                        </div>
                                    </td>
                                    <td class="border border-black p-2 align-top">
                                        <div class="text-normal">Poisťovňa:</div>
                                        <div class="font-normal"><strong>{{ dekurz.insurance_code || '—' }}</strong></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-2 align-top" colspan="3">
                                        <div class="text-normal">Adresa pacienta/pacientky:</div>
                                        <div class="font-normal"><strong>{{ dekurz.patient_address }}</strong></div>
                                    </td>
                                    <td class="border border-black p-2 align-top">
                                        <div class="text-normal">Poradové číslo dekurzu:</div>
                                        <div class="font-normal"><strong>{{ dekurz.dekurz_number }}</strong></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="w-full border-collapse dekurz-table table-fixed">
                            <colgroup>
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                                <col class="w-1/4" />
                            </colgroup>
                            <tbody>
                                <tr>
                                    <td class="border border-black p-2 align-top text-xs font-bold">
                                        Dátum a<br />
                                        čas zápisu:
                                    </td>
                                    <td class="border border-black p-2 align-top text-xs font-bold" colspan="3">
                                        Rozsah poskytnutej ZS a služieb súvisiacich s poskytnutím ZS, identifikácia
                                        ošetrujúceho zdravotného pracovníka (meno, priezvisko, odtlačok pečiatky a podpis)
                                    </td>
                                </tr>

                                <tr v-for="(row, rowIdx) in measureRows" :key="`measure-${rowIdx}-${row.date}`">
                                    <td class="border border-black p-2 align-top">
                                        <div class="whitespace-pre-line text-normal">{{ row.leftDateTime }}</div>
                                    </td>
                                    <td class="border border-black p-2 align-top" colspan="3">
                                        <div class="text-normal leading-snug">
                                            <span class="font-normal">{{ row.rightTime }}: </span>
                                            <span class="whitespace-pre-line">{{ row.text }}</span>
                                        </div>

                                        <div class="mt-2 row-signature">
                                            <div class="text-sm"><strong>{{ row.nurseName }}</strong></div>
                                            <img
                                                v-if="signatureUrl"
                                                :src="signatureUrl"
                                                alt="Podpis"
                                                class="signature-image"
                                            />
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="!measureRows.length">
                                    <td class="border border-black p-4 text-sm" colspan="4">Žiadne záznamy.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pages-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem;
    gap: 2rem;
}

.dekurz-page {
    width: 210mm;
    height: 297mm;
    margin: 5mm auto;
    background: white;
    box-sizing: border-box;
    padding: 12mm;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.page-inner {
    height: 100%;
    box-sizing: border-box;
}

.page-title {
    margin-bottom: 8px;
}

.dekurz-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
}

.row-signature {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    min-height: 32px;
}

.signature-image {
    margin-left: auto;
    height: 28px;
    max-width: 120px;
    object-fit: contain;
    flex-shrink: 0;
}

#measure-root {
    position: absolute;
    left: -99999px;
    top: 0;
    width: 210mm;
    opacity: 0;
    pointer-events: none;
    visibility: hidden;
}

.measure-page {
    box-shadow: none !important;
}

@page {
    size: A4;
    margin: 0;
}

@media print {
    :global(html),
    :global(body) {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    :global(body) * {
        visibility: hidden !important;
    }

    :global(#print-root),
    :global(#print-root *) {
        visibility: visible !important;
    }

    :global(#print-root) {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        display: block !important;
    }

    :global(.dekurz-page) {
        box-shadow: none !important;
        margin: 0 auto !important;
        break-after: page !important;
        page-break-after: always !important;
        overflow: hidden !important;
    }

    :global(.dekurz-page:last-child) {
        break-after: auto !important;
        page-break-after: auto !important;
    }

    :global(.no-print),
    :global(.p-toolbar),
    :global(#measure-root) {
        display: none !important;
    }
}
</style>