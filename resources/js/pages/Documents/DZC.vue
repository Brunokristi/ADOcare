<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useUiOverlayStore } from '@/stores/uiOverlay'

const signatureUrl = ref<string | null>(null)

/* -------------------------------------------------------------------------- */
/*  Types                                                                     */
/* -------------------------------------------------------------------------- */

type PatientAddress = {
    type?: 'branch_start' | 'patient' | 'branch_end'
    patient_id?: number | null

    address: string
    latitude: number
    longitude: number

    arrival_time?: string | null
    departure_time?: string | null

    kilometers?: number | null
    distance_to_location_m?: number | null
    time_to_location_seconds?: number | null

    time_on_location_seconds?: number | null
}

type PatientAddressesByDate = Record<string, PatientAddress[]>

type DayTotal = {
    date: string
    stops: number
    travel_seconds: number
    distance_m: number
    distance_km: number
    total_time?: string
    first_arrival?: string | null
    last_arrival?: string | null
}

type MonthTotals = {
    from: string
    to: string
    stops: number
    travel_seconds: number
    distance_m: number
    distance_km: number
}

interface CPData {
    user_id: number
    user_name: string
    start_date: string
    end_date: string
    trip_purpose?: string
    month: string
    year: string
    car_model: string
    car_license_plate: string
    car_consumption_l_per_100km: number | null
    branch_address: string
    patient_addresses: PatientAddressesByDate
    day_totals?: Record<string, DayTotal>
    month_totals?: MonthTotals | null
}

type DailyRecord = { date: string; addresses: PatientAddress[] }

/* -------------------------------------------------------------------------- */
/*  State                                                                     */
/* -------------------------------------------------------------------------- */

const route = useRoute()
const loading = ref(false)
const uiOverlayStore = useUiOverlayStore()
const isPrinting = ref(false)

const cpData = ref<CPData>({
    user_id: 0,
    user_name: '',
    start_date: '',
    end_date: '',
    trip_purpose: 'Návšteva pacienta',
    month: '',
    year: '',
    car_model: '',
    car_license_plate: '',
    car_consumption_l_per_100km: null,
    branch_address: '',
    patient_addresses: {},
    day_totals: {},
    month_totals: null,
})

const pagedRecords = ref<DailyRecord[][]>([])

const measurePageInnerRef = ref<HTMLElement | null>(null)
const measureHeaderRef = ref<HTMLElement | null>(null)
const measureItemsWrapRef = ref<HTMLElement | null>(null)
const measureFooterRef = ref<HTMLElement | null>(null)

let resizeTimer: number | null = null

/* -------------------------------------------------------------------------- */
/*  Load                                                                      */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
    window.addEventListener('afterprint', handleAfterPrint)
    window.addEventListener('resize', handleResize)
    await loadCP(String(route.params.documentId))
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

async function loadCP(documentId: string) {
    loading.value = true

    try {
        const res = await api.get(`/v1/dzcs/${documentId}`)
        const cp = res.data?.data?.dzc_data ?? {}

        cpData.value = {
            user_id: Number(cp.user_id ?? 0),
            user_name: cp.user_name ?? '',
            start_date: cp.start_date ?? '',
            end_date: cp.end_date ?? '',
            trip_purpose: cp.trip_purpose ?? 'Návšteva pacienta',
            month: String(cp.month ?? ''),
            year: String(cp.year ?? ''),
            car_model: cp.car_model ?? '',
            car_license_plate: cp.car_license_plate ?? '',
            car_consumption_l_per_100km: cp.car_consumption_l_per_100km == null
                ? null
                : Number(cp.car_consumption_l_per_100km),
            branch_address: cp.branch_address ?? '',
            patient_addresses: cp.patient_addresses ?? {},
            day_totals: cp.day_totals ?? {},
            month_totals: cp.month_totals ?? null,
        }

        await loadSignatureImage()
    } catch (error) {
        console.error('Failed to load DZC:', error)
    } finally {
        loading.value = false

        await settleLayout()
        await recalcPagination()

        window.setTimeout(() => {
            void recalcPagination()
        }, 100)
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

async function loadSignatureImage() {
    const representativeId = cpData.value.user_id

    if (!representativeId) {
        signatureUrl.value = null
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

        const url = URL.createObjectURL(res.data)
        signatureUrl.value = url
        await waitForImageLoad(url)
    } catch {
        signatureUrl.value = null
    }
}

/* -------------------------------------------------------------------------- */
/*  Formatting                                                                */
/* -------------------------------------------------------------------------- */

function formatDate(v?: string) {
    if (!v) return ''
    return new Date(v).toLocaleDateString('sk-SK')
}

function formatTime(v?: string | null) {
    if (!v) return '-'
    const d = new Date(v)
    if (Number.isNaN(d.getTime())) return '-'
    return d.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
}

function sumLegKm(addresses: PatientAddress[]) {
    const km = addresses.reduce((sum, a) => sum + (a.kilometers ?? 0), 0)
    return Math.round(km * 100) / 100
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
                })
        )
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

function handleResize() {
    if (resizeTimer) {
        window.clearTimeout(resizeTimer)
    }

    resizeTimer = window.setTimeout(() => {
        void recalcPagination()
    }, 120)
}

/* -------------------------------------------------------------------------- */
/*  Derived records                                                           */
/* -------------------------------------------------------------------------- */

const dailyRecords = computed<DailyRecord[]>(() => {
    const dates = Object.keys(cpData.value.patient_addresses || {}).sort()
    return dates.map((date) => ({
        date,
        addresses: cpData.value.patient_addresses[date] || [],
    }))
})

const monthTotalKm = computed(() => {
    const persisted = cpData.value.month_totals?.distance_km
    if (typeof persisted === 'number') return persisted
    return dailyRecords.value.reduce((sum, r) => sum + sumLegKm(r.addresses), 0)
})

const carConsumptionLabel = computed(() => {
    const value = cpData.value.car_consumption_l_per_100km

    if (typeof value !== 'number' || Number.isNaN(value)) {
        return '-'
    }

    const normalized = Math.round(value * 100) / 100
    return `${normalized.toLocaleString('sk-SK', { minimumFractionDigits: 1, maximumFractionDigits: 2 })}`
})

/* -------------------------------------------------------------------------- */
/*  Pagination                                                                */
/* -------------------------------------------------------------------------- */

async function recalcPagination() {
    if (loading.value) return

    await settleLayout()

    const inner = measurePageInnerRef.value
    const headerEl = measureHeaderRef.value
    const itemsWrap = measureItemsWrapRef.value
    const footerEl = measureFooterRef.value

    if (!inner || !itemsWrap) {
        pagedRecords.value = dailyRecords.value.length ? [dailyRecords.value] : []
        return
    }

    const innerHeight = inner.clientHeight
    const headerHeight = headerEl ? outerHeightWithMargins(headerEl) : 0
    const footerHeight = footerEl ? outerHeightWithMargins(footerEl) : 0

    const safety = 10
    const firstPageCapacity = innerHeight - headerHeight - footerHeight - safety
    const otherPageCapacity = innerHeight - footerHeight - safety

    const itemEls = Array.from(itemsWrap.children) as HTMLElement[]
    const heights = itemEls.map((el) => outerHeightWithMargins(el))

    const src = dailyRecords.value
    const pages: DailyRecord[][] = []

    let current: DailyRecord[] = []
    let used = 0
    let capacity = firstPageCapacity

    for (let i = 0; i < src.length; i++) {
        const record = src[i]
        const h = heights[i] ?? 0
        if (!record) continue

        if (current.length > 0 && used + h > capacity) {
            pages.push(current)
            current = []
            used = 0
            capacity = otherPageCapacity
        }

        current.push(record)
        used += h
    }

    if (current.length) {
        pages.push(current)
    }

    pagedRecords.value = pages.length ? pages : []
}

watch(
    () => [
        dailyRecords.value,
        signatureUrl.value,
        cpData.value.user_name,
        cpData.value.month,
        cpData.value.year,
        cpData.value.car_model,
        cpData.value.car_license_plate,
        cpData.value.car_consumption_l_per_100km,
    ],
    async () => {
        if (loading.value) return
        await recalcPagination()
    },
    { deep: true }
)

/* -------------------------------------------------------------------------- */
/*  Printing                                                                  */
/* -------------------------------------------------------------------------- */

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
                }

                .agreement-sheet-wrapper {
                    display: block !important;
                    padding: 0 !important;
                    gap: 0 !important;
                }

                .travel-page {
                    break-after: page;
                    page-break-after: always;
                    box-shadow: none !important;
                    margin: 0 !important;
                    overflow: hidden !important;
                }

                .travel-page:last-child {
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
                        if ((l as HTMLLinkElement).sheet) {
                            resolve()
                            return
                        }
                        l.addEventListener('load', () => resolve(), { once: true })
                        l.addEventListener('error', () => resolve(), { once: true })
                    })
            )
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

function handleAfterPrint() {
    isPrinting.value = false
}

function triggerDownload(blob: Blob, filename: string) {
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
}

async function downloadCSV() {
    try {
        const documentId = String(route.params.documentId)
        const res = await api.get(`/v1/dzcs/${documentId}/csv`, {
            responseType: 'blob',
            headers: { Accept: 'text/csv' },
        })

        const filename = `dzc_${cpData.value.month}_${cpData.value.year}.csv`
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' })
        triggerDownload(blob, filename)
    } catch (error) {
        console.error('Failed to download CSV:', error)
    }
}

watchEffect(() => {
    uiOverlayStore.setContentLoading(loading.value)
})
</script>

<template>
    <div class="flex flex-col gap-4 cover-sheet-page">
        <div class="bg-tag3 justify-between flex items-center p-3! rounded-md">
            <div class="flex items-center gap-2">
                <i class="bi bi-file-earmark" />
                {{ `dzc_${cpData.month}_${cpData.year}.csv` }}
            </div>

            <Button
                icon="bi bi-download"
                class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
                @click="downloadCSV"
            />
        </div>

        <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
            <template #start>
                <span class="text-heading-accent">Denný záznam ciest</span>
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

        <div v-if="!loading" class="agreement-sheet-wrapper">
            <div id="print-root">
                <div v-for="(page, pageIdx) in pagedRecords" :key="pageIdx" class="travel-page">
                    <div class="page-inner">
                        <div v-if="pageIdx === 0">
                            <div class="text-center font-bold text-lg mb-2">DENNÝ ZÁZNAM CIEST</div>

                            <table class="w-full border-collapse text-sm mb-4">
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-2 w-1/3">
                                            Obdobie:<br />
                                            <strong>{{ cpData.month }}/{{ cpData.year }}</strong>
                                        </td>
                                        <td class="border border-black p-2 w-2/3" colspan="2">
                                            Pracovník:<br />
                                            <strong>{{ cpData.user_name }}</strong>
                                            <img
                                                v-if="signatureUrl"
                                                :src="signatureUrl"
                                                alt="Podpis"
                                                class="signature-image-inline"
                                            />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border border-black p-2 w-1/3">
                                            Celkový počet km:<br />
                                            <strong>{{ monthTotalKm ?? '-' }}</strong>
                                        </td>
                                        <td class="border border-black p-2 w-1/3">
                                            Dopravný prostriedok:<br />
                                            <strong>{{ cpData.car_model }} - {{ cpData.car_license_plate }}</strong><br />
                                        </td>
                                        <td class="border border-black p-2 w-1/3">
                                            Spotreba:<br>
                                            <strong>{{ carConsumptionLabel }}</strong> L/100 km
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-for="record in page" :key="record.date" class="mb-4 dzc-block">
                            <table class="w-full border-collapse text-sm">
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-2 text-left w-1/4">
                                            <strong>Dátum</strong><br />
                                            {{ formatDate(record.date) }}
                                        </td>
                                        <td class="border border-black p-2 text-left w-1/4">
                                            <strong>Účel cesty</strong><br />
                                            {{ cpData.trip_purpose || 'Návšteva pacienta' }}
                                        </td>
                                        <td class="border border-black p-2 text-left w-1/4">
                                            <strong>Počet km</strong><br />
                                            {{ cpData.day_totals?.[record.date]?.distance_km ?? sumLegKm(record.addresses) }}
                                        </td>
                                        <td class="border border-black p-2 text-left w-1/4">
                                            <strong>Trvanie</strong><br />
                                            {{ cpData.day_totals?.[record.date]?.total_time ?? '-' }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="border border-black p-2 text-left w-full" colspan="4">
                                            <table class="w-full text-[0.5rem] mt-2 route-table">
                                                <tbody>
                                                    <tr class="border-b border-gray-300">
                                                        <td class="p-1 route-col-index text-left">
                                                            <strong>Poradové číslo</strong>
                                                        </td>
                                                        <td class="p-1 route-col-address">
                                                            <strong>Adresa</strong>
                                                        </td>
                                                        <td class="p-1 route-col-time text-center">
                                                            <strong>Príchod</strong>
                                                        </td>
                                                        <td class="p-1 route-col-km text-right">
                                                            <strong>KM</strong>
                                                        </td>
                                                    </tr>

                                                    <tr
                                                        v-for="(addr, idx) in record.addresses"
                                                        :key="idx"
                                                        class="border-b border-gray-300"
                                                    >
                                                        <td class="p-1 route-col-index text-left">
                                                            <strong>{{ idx + 1 }}.</strong>
                                                        </td>

                                                        <td class="p-1 route-col-address">
                                                            {{ addr.address }}
                                                        </td>

                                                        <td class="p-1 route-col-time text-center">
                                                            {{ formatTime(addr.arrival_time ?? null) }}
                                                        </td>

                                                        <td class="p-1 route-col-km text-right">
                                                            {{ addr.kilometers ?? '-' }} km
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="measure-root" aria-hidden="true">
                <div class="travel-page measure-page">
                    <div ref="measurePageInnerRef" class="page-inner">
                        <div ref="measureHeaderRef">
                            <div class="text-center font-bold text-lg mb-2">DENNÝ ZÁZNAM CIEST</div>

                            <table class="w-full border-collapse text-sm mb-4">
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-2 w-1/3">
                                            Obdobie:<br />
                                            <strong>{{ cpData.month }}/{{ cpData.year }}</strong>
                                        </td>
                                        <td class="border border-black p-2 w-2/3">
                                            Pracovník:<br />
                                            <strong>{{ cpData.user_name }}</strong>
                                            <img
                                                v-if="signatureUrl"
                                                :src="signatureUrl"
                                                alt="Podpis"
                                                class="signature-image-inline"
                                            />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border border-black p-2 w-1/3">
                                            Celkový počet km:<br />
                                            <strong>{{ monthTotalKm ?? '-' }}</strong>
                                        </td>
                                        <td class="border border-black p-2 w-1/3">
                                            Dopravný prostriedok:<br />
                                            <strong>{{ cpData.car_model }} - {{ cpData.car_license_plate }}</strong><br />
                                        </td>
                                        <td class="border border-black p-2 w-1/3">
                                            Spotreba:<br /> 
                                            <strong>{{ carConsumptionLabel }}</strong>
                                        </td>
                                        
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div ref="measureItemsWrapRef">
                            <div v-for="record in dailyRecords" :key="record.date" class="mb-4 dzc-block">
                                <table class="w-full border-collapse text-sm">
                                    <tbody>
                                        <tr>
                                            <td class="border border-black p-2 text-left w-1/4">
                                                <strong>Dátum</strong><br />
                                                {{ formatDate(record.date) }}
                                            </td>
                                            <td class="border border-black p-2 text-left w-1/4">
                                                <strong>Účel cesty</strong><br />
                                                {{ cpData.trip_purpose || 'Návšteva pacienta' }}
                                            </td>
                                            <td class="border border-black p-2 text-left w-1/4">
                                                <strong>Počet km</strong><br />
                                                {{ cpData.day_totals?.[record.date]?.distance_km ?? sumLegKm(record.addresses) }}
                                            </td>
                                            <td class="border border-black p-2 text-left w-1/4">
                                                <strong>Trvanie</strong><br />
                                                {{ cpData.day_totals?.[record.date]?.total_time ?? '-' }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td class="border border-black p-2 text-left w-full" colspan="4">
                                                <table class="w-full text-[0.5rem] mt-2 route-table">
                                                    <tbody>
                                                        <tr class="border-b border-gray-300">
                                                            <td class="p-1 route-col-index text-left">
                                                                <strong>Poradové číslo</strong>
                                                            </td>
                                                            <td class="p-1 route-col-address">
                                                                <strong>Adresa</strong>
                                                            </td>
                                                            <td class="p-1 route-col-time text-center">
                                                                <strong>Príchod</strong>
                                                            </td>
                                                            <td class="p-1 route-col-km text-right">
                                                                <strong>KM</strong>
                                                            </td>
                                                        </tr>

                                                        <tr
                                                            v-for="(addr, idx) in record.addresses"
                                                            :key="idx"
                                                            class="border-b border-gray-300"
                                                        >
                                                            <td class="p-1 route-col-index text-left">
                                                                <strong>{{ idx + 1 }}.</strong>
                                                            </td>

                                                            <td class="p-1 route-col-address">
                                                                {{ addr.address }}
                                                            </td>

                                                            <td class="p-1 route-col-time text-center">
                                                                {{ formatTime(addr.arrival_time ?? null) }}
                                                            </td>

                                                            <td class="p-1 route-col-km text-right">
                                                                {{ addr.kilometers ?? '-' }} km
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div ref="measureFooterRef"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.travel-page {
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
}

.agreement-sheet-wrapper {
    display: flex;
    justify-content: center;
    padding: 2rem;
    gap: 2rem;
    flex-wrap: wrap;
}

.route-table {
    table-layout: fixed;
}

.route-col-index {
    width: 18%;
}

.route-col-address {
    width: 52%;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.route-col-time {
    width: 15%;
}

.route-col-km {
    width: 15%;
}

.signature-image-inline {
    display: inline-block;
    margin-left: 8px;
    height: 40px;
    max-width: 140px;
    object-fit: contain;
    vertical-align: middle;
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

    :global(.travel-page) {
        box-shadow: none !important;
        margin: 0 !important;
        break-after: page !important;
        page-break-after: always !important;
        overflow: hidden !important;
    }

    :global(.travel-page:last-child) {
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