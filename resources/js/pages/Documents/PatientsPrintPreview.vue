<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue'
import api from '@/services/api'
import type { Patient, Doctor } from '@/types/models'
import { formatBranchFullName, formatUserFullName } from '@/utils/formatUtils'
import { useUiOverlayStore } from '@/stores/uiOverlay'

interface PatientsPrintPayload {
    mode: 'selected' | 'filtered'
    selectedPatients?: Patient[]
    endpointUrl?: string
    params?: Record<string, any>
}

const loading = ref(false)
const isPrinting = ref(false)
const uiOverlayStore = useUiOverlayStore()

const printedAt = ref('')
const patients = ref<Patient[]>([])
const errorMessage = ref('')

const pagedPatients = ref<Patient[][]>([])

const measurePageInnerRef = ref<HTMLElement | null>(null)
const measureHeaderRef = ref<HTMLElement | null>(null)
const measureTableHeadRef = ref<HTMLElement | null>(null)
const measureRowsWrapRef = ref<HTMLElement | null>(null)
const measureFooterRef = ref<HTMLElement | null>(null)

let resizeTimer: number | null = null

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value)
})

function extractPatientsAndLastPage(payload: any): { items: Patient[]; lastPage: number } {
    const wrapped = payload?.data ?? payload
    const items = Array.isArray(wrapped?.items)
        ? wrapped.items
        : Array.isArray(wrapped?.data)
          ? wrapped.data
          : []

    const lastPage = Number(wrapped?.meta?.last_page ?? wrapped?.last_page ?? 1)

    return {
        items,
        lastPage: Number.isFinite(lastPage) && lastPage > 0 ? lastPage : 1,
    }
}

async function fetchAllFilteredPatients(endpointUrl: string, baseParams: Record<string, any>) {
    const collected: Patient[] = []
    const perPage = 100
    let page = 1
    let lastPage = 1

    do {
        const response = (await api.get(endpointUrl, {
            params: {
                ...baseParams,
                paginate: true,
                per_page: perPage,
                page,
            },
        })).data

        const parsed = extractPatientsAndLastPage(response)
        collected.push(...parsed.items)
        lastPage = parsed.lastPage
        page += 1
    } while (page <= lastPage)

    const deduped = new Map<number, Patient>()
    collected.forEach((patient) => {
        if (patient?.id) {
            deduped.set(patient.id, patient)
        }
    })

    patients.value = Array.from(deduped.values())
}

function formatDoctor(doctor?: Doctor | null) {
    if (!doctor) return '-'
    return `${doctor.title ?? ''} ${doctor.first_name ?? ''} ${doctor.last_name ?? ''}`.trim() || '-'
}

function outerHeightWithMargins(el: HTMLElement) {
    const style = window.getComputedStyle(el)
    const mt = parseFloat(style.marginTop || '0')
    const mb = parseFloat(style.marginBottom || '0')
    return el.getBoundingClientRect().height + mt + mb
}

async function waitForFonts(doc: Document = document) {
    try {
        if ('fonts' in doc) {
            await (doc as Document & { fonts: FontFaceSet }).fonts.ready
        }
    } catch {
        // ignore
    }
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
                }),
        ),
    )
}

async function settleLayout() {
    await nextTick()
    await waitForFonts()
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
    await waitForImagesInElement(document.getElementById('measure-root'))
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
}

async function recalcPagination() {
    if (loading.value || errorMessage.value) return

    await settleLayout()

    const inner = measurePageInnerRef.value
    const headerEl = measureHeaderRef.value
    const tableHeadEl = measureTableHeadRef.value
    const rowsWrap = measureRowsWrapRef.value
    const footerEl = measureFooterRef.value

    if (!inner || !rowsWrap) {
        pagedPatients.value = patients.value.length ? [patients.value] : [[]]
        return
    }

    const innerHeight = inner.clientHeight
    const headerHeight = headerEl ? outerHeightWithMargins(headerEl) : 0
    const tableHeadHeight = tableHeadEl ? outerHeightWithMargins(tableHeadEl) : 0
    const footerHeight = footerEl ? outerHeightWithMargins(footerEl) : 0

    const safety = 10
    const pageCapacity = innerHeight - headerHeight - tableHeadHeight - footerHeight - safety

    const rowEls = Array.from(rowsWrap.children) as HTMLElement[]
    const rowHeights = rowEls.map((el) => outerHeightWithMargins(el))

    const src = patients.value
    const pages: Patient[][] = []

    let current: Patient[] = []
    let used = 0

    for (let i = 0; i < src.length; i++) {
        const patient = src[i]
        const rowHeight = rowHeights[i] ?? 0

        if (!patient) continue

        if (current.length > 0 && used + rowHeight > pageCapacity) {
            pages.push(current)
            current = []
            used = 0
        }

        current.push(patient)
        used += rowHeight
    }

    pagedPatients.value = pages.length
        ? current.length
            ? [...pages, current]
            : pages
        : current.length
          ? [current]
          : [[]]
}

function handleResize() {
    if (resizeTimer) {
        window.clearTimeout(resizeTimer)
    }

    resizeTimer = window.setTimeout(() => {
        void recalcPagination()
    }, 120)
}

async function printPage() {
    isPrinting.value = true

    try {
        await recalcPagination()

        const src = document.getElementById('print-root')
        if (!src) return

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
            return
        }

        const headPieces: string[] = []

        document.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
            const href = (link as HTMLLinkElement).href
            if (href) {
                headPieces.push(`<link rel="stylesheet" href="${href}">`)
            }
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
                }

                .agreement-sheet-wrapper {
                    display: block !important;
                    padding: 0 !important;
                    gap: 0 !important;
                }

                .print-page {
                    break-after: page;
                    page-break-after: always;
                    box-shadow: none !important;
                    margin: 0 !important;
                    overflow: hidden !important;
                }

                .print-page:last-child {
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
                (l) =>
                    new Promise<void>((resolve) => {
                        if (l.sheet) {
                            resolve()
                            return
                        }

                        l.addEventListener('load', () => resolve(), { once: true })
                        l.addEventListener('error', () => resolve(), { once: true })
                    }),
            ),
        )

        await waitForFonts(doc)
        await waitForImagesInElement(doc)
        await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
        await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))

        win.focus()
        win.print()

        window.setTimeout(() => {
            try {
                document.body.removeChild(iframe)
            } catch {
                // ignore
            }
        }, 500)
    } finally {
        isPrinting.value = false
    }
}

watch(
    () => [
        patients.value,
        printedAt.value,
    ],
    async () => {
        if (loading.value || errorMessage.value) return
        await recalcPagination()
    },
    { deep: true },
)

onMounted(async () => {
    window.addEventListener('resize', handleResize)

    loading.value = true

    try {
        const raw = sessionStorage.getItem('patients-print-payload')

        if (!raw) {
            errorMessage.value = 'Chýbajú dáta pre náhľad tlače.'
            return
        }

        const payload = JSON.parse(raw) as PatientsPrintPayload

        if (payload.mode === 'selected') {
            patients.value = payload.selectedPatients ?? []
        } else if (payload.mode === 'filtered' && payload.endpointUrl) {
            await fetchAllFilteredPatients(payload.endpointUrl, payload.params ?? {})
        } else {
            errorMessage.value = 'Neplatné dáta pre tlač.'
        }

        printedAt.value = new Date().toLocaleString('sk-SK')

        await settleLayout()
        await recalcPagination()

        window.setTimeout(() => {
            void recalcPagination()
        }, 100)
    } catch (error) {
        console.error('Failed to build patients print preview', error)
        errorMessage.value = 'Nepodarilo sa pripraviť náhľad tlače.'
    } finally {
        loading.value = false
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)

    if (resizeTimer) {
        window.clearTimeout(resizeTimer)
    }

    isPrinting.value = false
})
</script>

<template>
    <div class="flex flex-col gap-4 cover-sheet-page">
        <Toolbar
            class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print"
        >
            <template #start>
                <span class="text-heading-accent">Zoznam pacientov</span>
            </template>

            <template #end>
                <div class="flex items-center gap-2">
                    <Button
                        icon="bi bi-printer"
                        class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
                        :disabled="loading || isPrinting || !!errorMessage"
                        @click="printPage"
                    />
                </div>
            </template>
        </Toolbar>

        <div v-if="errorMessage" class="text-red-600 text-sm">
            {{ errorMessage }}
        </div>

        <div v-else-if="!loading" class="agreement-sheet-wrapper">
            <div id="print-root">
                <div
                    v-for="(pagePatients, pageIdx) in pagedPatients"
                    :key="pageIdx"
                    class="print-page"
                >
                    <div class="page-inner">
                        <div class="page-header" :ref="pageIdx === 0 ? undefined : undefined">
                            <div class="text-center font-bold text-lg mb-4">
                                ZOZNAM PACIENTOV
                            </div>

                            <table class="w-full border-collapse text-sm mb-4">
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-2 w-1/3">
                                            Dátum tlače:<br />
                                            <strong>{{ printedAt }}</strong>
                                        </td>
                                        <td class="border border-black p-2 w-1/3">
                                            Počet pacientov:<br />
                                            <strong>{{ patients.length }}</strong>
                                        </td>
                                        <td class="border border-black p-2 w-1/3">
                                            Strana:<br />
                                            <strong>{{ pageIdx + 1 }} / {{ pagedPatients.length }}</strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <table v-if="pagePatients.length" class="w-full border-collapse text-sm patients-table">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Meno a priezvisko</th>
                                    <th>Rodné číslo</th>
                                    <th>Sestra</th>
                                    <th>Lekár</th>
                                    <th>Prevádzka</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(patient, rowIdx) in pagePatients"
                                    :key="patient.id"
                                >
                                    <td class="text-center">
                                        {{ pageIdx * 1000 + rowIdx + 1 > 0 ? (pagedPatients.slice(0, pageIdx).reduce((sum, page) => sum + page.length, 0) + rowIdx + 1) : rowIdx + 1 }}
                                    </td>
                                    <td>
                                        {{ `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim() || '-' }}
                                    </td>
                                    <td>{{ patient.personal_number ?? '-' }}</td>
                                    <td>{{ patient.nurse ? formatUserFullName(patient.nurse) : '-' }}</td>
                                    <td>{{ formatDoctor(patient.doctor) }}</td>
                                    <td>{{ patient.branch ? formatBranchFullName(patient.branch) : '-' }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-else class="empty">
                            Neboli vybrané žiadne záznamy.
                        </div>
                    </div>
                </div>
            </div>

            <div id="measure-root" aria-hidden="true">
                <div class="print-page measure-page">
                    <div ref="measurePageInnerRef" class="page-inner">
                        <div ref="measureHeaderRef" class="page-header">
                            <div class="text-center font-bold text-lg mb-4">
                                ZOZNAM PACIENTOV
                            </div>

                            <table class="w-full border-collapse text-sm mb-4">
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-2 w-1/3">
                                            Dátum tlače:<br />
                                            <strong>{{ printedAt }}</strong>
                                        </td>
                                        <td class="border border-black p-2 w-1/3">
                                            Počet pacientov:<br />
                                            <strong>{{ patients.length }}</strong>
                                        </td>
                                        <td class="border border-black p-2 w-1/3">
                                            Strana:<br />
                                            <strong>1 / 1</strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <table class="w-full border-collapse text-sm patients-table">
                            <thead ref="measureTableHeadRef">
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Meno a priezvisko</th>
                                    <th>Rodné číslo</th>
                                    <th>Sestra</th>
                                    <th>Lekár</th>
                                    <th>Prevádzka</th>
                                </tr>
                            </thead>
                            <tbody ref="measureRowsWrapRef">
                                <tr
                                    v-for="(patient, rowIdx) in patients"
                                    :key="patient.id"
                                >
                                    <td class="text-center">{{ rowIdx + 1 }}</td>
                                    <td>
                                        {{ `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim() || '-' }}
                                    </td>
                                    <td>{{ patient.personal_number ?? '-' }}</td>
                                    <td>{{ patient.nurse ? formatUserFullName(patient.nurse) : '-' }}</td>
                                    <td>{{ formatDoctor(patient.doctor) }}</td>
                                    <td>{{ patient.branch ? formatBranchFullName(patient.branch) : '-' }}</td>
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
.print-page {
    width: 210mm;
    height: 297mm;
    margin: 5mm auto;
    background: white;
    box-sizing: border-box;
    padding: 14mm;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.page-inner {
    height: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

.page-header {
    flex: 0 0 auto;
}

.page-footer {
    flex: 0 0 auto;
    margin-top: auto;
    padding-top: 6mm;
    font-size: 10px;
    color: #6b7280;
    text-align: right;
}

.agreement-sheet-wrapper {
    display: flex;
    justify-content: center;
    padding: 2rem;
    gap: 2rem;
    flex-wrap: wrap;
}

.patients-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 10.5px;
}

.patients-table th,
.patients-table td {
    border: 1px solid #111827;
    padding: 5px 6px;
    vertical-align: top;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.patients-table th {
    font-weight: 700;
    text-align: left;
}

.patients-table td:nth-child(1),
.patients-table th:nth-child(1) {
    width: 8%;
}

.patients-table td:nth-child(2),
.patients-table th:nth-child(2) {
    width: 22%;
}

.patients-table td:nth-child(3),
.patients-table th:nth-child(3) {
    width: 14%;
}

.patients-table td:nth-child(4),
.patients-table th:nth-child(4) {
    width: 17%;
}

.patients-table td:nth-child(5),
.patients-table th:nth-child(5) {
    width: 17%;
}

.patients-table td:nth-child(6),
.patients-table th:nth-child(6) {
    width: 14%;
}

.patients-table td:nth-child(7),
.patients-table th:nth-child(7) {
    width: 8%;
}

.empty {
    margin-top: 28px;
    font-size: 14px;
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
        background: #fff !important;
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
        position: static !important;
        display: block !important;
        width: auto !important;
    }

    :global(.agreement-sheet-wrapper) {
        display: block !important;
        padding: 0 !important;
        gap: 0 !important;
    }

    :global(.print-page) {
        box-shadow: none !important;
        margin: 0 !important;
        break-after: page !important;
        page-break-after: always !important;
        overflow: hidden !important;
    }

    :global(.print-page:last-child) {
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