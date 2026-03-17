<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { getValueLabel } from '@/utils/valueTranslations'
import { useUiOverlayStore } from '@/stores/uiOverlay'

/* -------------------------------------------------------------------------- */
/*  Types                                                                     */
/* -------------------------------------------------------------------------- */

interface DocumentData {
    facilityName: string
    facilityAddress: string
    patientName: string
    patientIdNumber: string
    patientHealthCode: string
    patientCurrentAddress: string
    userName: string
    doctorName: string
    formData: Record<string, any>
    userId?: number | null
    companyId?: number | null
}

type Field = { id: string; label: string }

type SectionLayout = 'compact' | 'full'

type Section = {
    id: string
    title: string
    layout?: SectionLayout
    fields: Field[]
}

type Page = { sections: Section[] }

/* -------------------------------------------------------------------------- */
/*  State                                                                     */
/* -------------------------------------------------------------------------- */

const route = useRoute()
const uiOverlayStore = useUiOverlayStore()

const loading = ref(false)
const isPrinting = ref(false)

const stampUrl = ref<string | null>(null)
const signatureUrl = ref<string | null>(null)

const documentData = ref<DocumentData>({
    facilityName: '',
    facilityAddress: '',
    patientName: '',
    patientIdNumber: '',
    patientHealthCode: '',
    patientCurrentAddress: '',
    userName: '',
    doctorName: '',
    formData: {},
    userId: null,
    companyId: null,
})

const pages = ref<Page[]>([])

/* -------------------------------------------------------------------------- */
/*  Form spec                                                                 */
/* -------------------------------------------------------------------------- */

const formSpec: { sections: Section[] } = {
    sections: [
        {
            id: 'basic',
            title: 'Základné údaje',
            layout: 'compact',
            fields: [
                { id: 'diagnosis', label: 'Lekárska diagnóza' },
                { id: 'recommendedPharmacy', label: 'Odporučená farmakoterapia' },
                { id: 'admissionDate', label: 'Dátum prijatia do starostlivosti' },
            ],
        },
        {
            id: 'allergies',
            title: 'Alergie',
            layout: 'full',
            fields: [
                { id: 'allergies', label: 'Alergie' },
                { id: 'allergies.otherFindings', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'abuses',
            title: 'Abúzy',
            layout: 'compact',
            fields: [{ id: 'abuses', label: 'Abúzy' }],
        },
        {
            id: 'family',
            title: 'Rodinná anamnéza',
            layout: 'full',
            fields: [
                { id: 'familyAnamnesis', label: 'Rodinná anamnéza' },
                { id: 'familyAnamnesis.otherFindings', label: 'Poznámka' },
            ],
        },
        {
            id: 'social',
            title: 'Sociálna anamnéza',
            layout: 'compact',
            fields: [
                { id: 'employment', label: 'Povolanie' },
                { id: 'socialConditions', label: 'Sociálne podmienky' },
                { id: 'socialConditions.otherFindings', label: 'Iné zistenia' },
                { id: 'socialStatus', label: 'Sociálny stav' },
                { id: 'socialContacts', label: 'Sociálne kontakty' },
                { id: 'supportSystems', label: 'Systémy podpory' },
                { id: 'socialCulture', label: 'Kultúra a sociálne faktory' },
                { id: 'socialMedia', label: 'Sociálne médiá' },
            ],
        },
        {
            id: 'healthPerception',
            title: 'Vnímanie zdravia',
            layout: 'full',
            fields: [{ id: 'healthPerception.description', label: 'Subjektívny popis ťažkostí pacienta' }],
        },
        {
            id: 'consciousnessOrientation',
            title: 'Vedomie a orientácia',
            layout: 'compact',
            fields: [
                { id: 'consciousness', label: 'Vedomie' },
                { id: 'consciousnessOtherNotes', label: 'Iné zistenia' },
                { id: 'orientation', label: 'Orientácia' },
                { id: 'orientationOtherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'nursingAssessment',
            title: 'Vstupný záznam sesterského posúdenia zdravotného stavu pacienta',
            layout: 'compact',
            fields: [
                { id: 'nursing.caredRecommendedBy', label: 'Starostlivosť odporúčaná' },
                { id: 'nursing.otherDoctor', label: 'Iný ošetrujúci lekár' },
                { id: 'nursing.otherDoctorDetails', label: 'Aký' },
                { id: 'nursing.transferredFromOtherFacility', label: 'Prevzatý z iného zariadenia' },
                { id: 'nursing.transferredFromOtherFacilityDetails', label: 'Odkiaľ' },
                { id: 'nursing.department', label: 'Oddelenie' },
                { id: 'nursing.lastHospitalizationFrom', label: 'Posledná hospitalizácia od' },
                { id: 'nursing.lastHospitalizationTo', label: 'do' },
            ],
        },
        {
            id: 'circulation',
            title: 'Cirkulácia',
            layout: 'compact',
            fields: [
                { id: 'bloodPressure', label: 'TK (mmHg)' },
                { id: 'temperature', label: 'TT (°C)' },
                { id: 'pulse', label: 'P (/min)' },
                { id: 'circulation.problemExists', label: 'Cirkulácia – pobem' },
                { id: 'hypotensionHypertension', label: 'Hypotenzita/Hypertenzita' },
                { id: 'irregularPulse', label: 'Nepravidelný pulz' },
                { id: 'circulation.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'breathing',
            title: 'Dýchanie',
            layout: 'compact',
            fields: [
                { id: 'respiratoryRate', label: 'D (/min)' },
                { id: 'breathing.problemExists', label: 'Dýchanie – pobem' },
                { id: 'irregularities', label: 'Nepravidelnosti' },
                { id: 'breathing.otherNotes', label: 'Iné zistenia' },
                { id: 'suctioning', label: 'Odsávanie' },
                { id: 'oxygenTherapy', label: 'Kyslíková terapia' },
                { id: 'mechanicalVentilation', label: 'Mechanická ventilácia' },
                { id: 'inhalation', label: 'Inhalácia' },
            ],
        },
        {
            id: 'sleep',
            title: 'Spánok',
            layout: 'compact',
            fields: [
                { id: 'sleep.problemExists', label: 'Spánok – pobem' },
                { id: 'sleep.findings', label: 'Zistenia' },
                { id: 'sleep.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'mobility',
            title: 'Mobilita',
            layout: 'compact',
            fields: [
                { id: 'mobility.level', label: 'Úroveň mobility' },
                { id: 'mobility.compensatoryAids', label: 'Kompenzačné pomôcky' },
                { id: 'mobility.compensatoryAidsDetails', label: 'Aké kompenzačné pomôcky' },
            ],
        },
        {
            id: 'movement',
            title: 'Pohybový systém',
            layout: 'compact',
            fields: [
                { id: 'movement.problemExists', label: 'Pohybový systém – pobem' },
                { id: 'movement.findings', label: 'Zistenia' },
                { id: 'movement.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'pain',
            title: 'Bolesť',
            layout: 'compact',
            fields: [
                { id: 'pain.problemExists', label: 'Bolesť – pobem' },
                { id: 'pain.type', label: 'Typ bolesti' },
                { id: 'pain.location', label: 'Lokalizácia' },
                { id: 'pain.character', label: 'Charakter' },
                { id: 'pain.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'nutrition',
            title: 'Výživa',
            layout: 'compact',
            fields: [
                { id: 'nutrition.diet', label: 'Diéta č.' },
                { id: 'nutrition.weightTrend', label: 'Trend hmotnosti' },
                { id: 'nutrition.weightKg', label: 'Hmotnosť (kg)' },
                { id: 'nutrition.problemExists', label: 'Výživa – pobem' },
                { id: 'nutrition.symptoms', label: 'Symptómy' },
                { id: 'nutrition.feedingType', label: 'Typ kŕmenia' },
                { id: 'nutrition.preparations', label: 'Prípravky' },
                { id: 'nutrition.appetite', label: 'Apetít' },
                { id: 'nutrition.intake', label: 'Príjem' },
                { id: 'nutrition.gastrostomy', label: 'Gastrostómia' },
                { id: 'nutrition.gastrostomyDateIntroduced', label: 'Dátum zavedenia gastrostómie' },
                { id: 'nutrition.peg', label: 'PEG' },
                { id: 'nutrition.pegDateIntroduced', label: 'Dátum zavedenia PEG' },
                { id: 'nutrition.fluidIntake', label: 'Príjem tekutín (ml/24h)' },
                { id: 'nutrition.nutritionRoute', label: 'Trasa výživy' },
                { id: 'nutrition.cvk', label: 'CVK' },
                { id: 'nutrition.cvkDateIntroduced', label: 'Dátum zavedenia CVK' },
                { id: 'nutrition.peripheralIVAccess', label: 'Periférny IV prístup' },
                { id: 'nutrition.peripheralIVAccessDateIntroduced', label: 'Dátum zavedenia periférneho IV prístupu' },
                { id: 'nutrition.denture', label: 'Zubná protéza' },
                { id: 'nutrition.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'elimination',
            title: 'Vylučovanie',
            layout: 'compact',
            fields: [
                { id: 'defecation.problemExists', label: 'Defekácia – pobem' },
                { id: 'defecation.symptoms', label: 'Symptómy defekácie' },
                { id: 'defecation.stomaCare', label: 'Starostlivosť o stómiu' },
                { id: 'defecation.stomaCareDate', label: 'Dátum zavedenia stómie' },
                { id: 'defecation.otherNotes', label: 'Iné zistenia' },
                { id: 'defecation.stomaAssistanceNeeded', label: 'Potreba pomoci pri ošetrovaní stómie' },
                { id: 'defecation.regulationUsed', label: 'Regulácia vyprázdňovania' },
                { id: 'defecation.regulationMethods', label: 'Spôsoby regulácie' },
                { id: 'defecation.regulationOtherNotes', label: 'Iné zistenia regulácie' },
                { id: 'urination.diuresis', label: 'Diuréza (ml/24 hod.)' },
                { id: 'urination.problemExists', label: 'Močenie – pobem' },
                { id: 'urination.symptoms', label: 'Symptómy močenia' },
                { id: 'urination.catheter', label: 'Permanentný katéter' },
                { id: 'urination.catheterDate', label: 'Dátum zavedenia katétra' },
                { id: 'urination.urineColor', label: 'Farba moču' },
                { id: 'urination.urostomy', label: 'Urostómia' },
                { id: 'urination.urostomyDate', label: 'Dátum zavedenia urostómie' },
                { id: 'urination.dialysis', label: 'Dialýza' },
                { id: 'urination.dialysisDate', label: 'Dátum zavedenia dialýzy' },
                { id: 'urination.condomSystem', label: 'Kondómový systém' },
                { id: 'urination.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'communication',
            title: 'Komunikácia',
            layout: 'compact',
            fields: [
                { id: 'communication.type', label: 'Spôsob komunikácie' },
                { id: 'communication.problemExists', label: 'Komunikácia – pobem' },
                { id: 'communication.issues', label: 'Pobemy v komunikácii' },
                { id: 'communication.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'skin',
            title: 'Koža / Edémy / Sliznice / Hygiena',
            layout: 'compact',
            fields: [
                { id: 'skin.problemExists', label: 'Koža – pobem' },
                { id: 'skin.temperature', label: 'Teplota kože' },
                { id: 'skin.moisture', label: 'Vlhkosť kože' },
                { id: 'skin.color', label: 'Farba' },
                { id: 'skin.turgor', label: 'Turgor' },
                { id: 'skin.integrity', label: 'Celistvosť kože' },
                { id: 'skin.changes', label: 'Zmeny na koži' },
                { id: 'skin.defectLocation', label: 'Lokalizácia defektu' },
                { id: 'skin.defectSizeCm', label: 'Veľkosť defektu (cm)' },
                { id: 'skin.patientDayAfterSurgery', label: 'Deň po operácii' },
                { id: 'edema.problemExists', label: 'Edémy – pobem' },
                { id: 'edema.type', label: 'Typ edému' },
                { id: 'edema.measures', label: 'Opatrenia' },
                { id: 'mucosa.problemExists', label: 'Sliznice – pobem' },
                { id: 'mucosa.findings', label: 'Zistenia na sliznici' },
                { id: 'hygiene.statusOnAdmission', label: 'Hygienický stav pri prijatí' },
                { id: 'hygiene.selfCare', label: 'Hygienu vykonáva' },
                { id: 'skinMucosa.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'postpartum',
            title: 'Pôrodné posúdenie (šestonedelie)',
            layout: 'compact',
            fields: [
                { id: 'postpartum.parity', label: 'Poradie pôrodu' },
                { id: 'postpartum.deliveryDate', label: 'Dátum pôrodu' },
                { id: 'postpartum.deliveryType', label: 'Typ pôrodu' },
                { id: 'postpartum.complications', label: 'Komplikácie' },
                { id: 'postpartum.complicationDetails', label: 'Podrobnosti komplikácií' },
                { id: 'postpartum.fundusUteri', label: 'Fundus maternice' },
                { id: 'postpartum.lochiaAppearance', label: 'Vzhľad lochií' },
                { id: 'postpartum.lochiaAmount', label: 'Množstvo lochií' },
                { id: 'postpartum.woundHealing', label: 'Hojenie poranenia' },
                { id: 'postpartum.breasts', label: 'Prsníky' },
                { id: 'postpartum.lactation', label: 'Laktácia' },
                { id: 'postpartum.newbornSex', label: 'Pohlavie novorodenca' },
                { id: 'postpartum.newbornWeight', label: 'Pôrodná hmotnosť (g)' },
                { id: 'postpartum.newbornLength', label: 'Dĺžka (cm)' },
                { id: 'postpartum.newbornHeadCircumference', label: 'Obvod hlavy (cm)' },
                { id: 'postpartum.newbornChestCircumference', label: 'Obvod hrudníka (cm)' },
                { id: 'postpartum.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'learning',
            title: 'Učenie, zmyslové vnímanie',
            layout: 'compact',
            fields: [
                { id: 'learning.problemExists', label: 'Pobem s učením/zmyslami' },
                { id: 'learning.sensoryChanges', label: 'Zmeny v zmysloch' },
                { id: 'learning.sensoryChangesExist', label: 'Zmeny prítomné' },
                { id: 'learning.sensoryChangesDetails', label: 'Detaily zmien' },
                { id: 'learning.compensatoryAids', label: 'Kompenzačné pomôcky' },
                { id: 'learning.diseaseKnowledge', label: 'Vedomosti o chorobe' },
                { id: 'learning.educationTopics', label: 'Edukačné témy' },
                { id: 'learning.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'psychosocial',
            title: 'Psychické, sociálne a duchovné potreby',
            layout: 'compact',
            fields: [
                { id: 'psychological.problemExists', label: 'Psychické potreby – pobem' },
                { id: 'psychological.mood', label: 'Nálada' },
                { id: 'psychological.feelings', label: 'Pocity' },
                { id: 'social.problemExists', label: 'Sociálne potreby – pobem' },
                { id: 'social.supportDependency', label: 'Sociálna pomoc' },
                { id: 'spiritual.problemExists', label: 'Duchovné potreby – pobem' },
                { id: 'psychosocial.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'careDeficits',
            title: 'Nedostatočnosť v oblasti',
            layout: 'compact',
            fields: [
                { id: 'deficits.areas', label: 'Oblasti nedostatočnosti' },
                { id: 'deficits.treatments', label: 'Liečba a intervencie' },
                { id: 'deficits.nursingCare', label: 'Ošetrovateľská starostlivosť' },
                { id: 'careDeficits.otherNotes', label: 'Iné zistenia' },
            ],
        },
        {
            id: 'patientInstruction',
            title: 'Poučenie pacienta/pacientky',
            layout: 'compact',
            fields: [
                { id: 'instruction.topics', label: 'Témy poučenia' },
                { id: 'instruction.handoverOnAdmission', label: 'Odovzdané pri prijatí' },
                { id: 'instruction.date', label: 'Dátum' },
            ],
        },
        {
            id: 'nursingDiagnoses',
            title: 'Stanovenie sesterských diagnóz pri príjme',
            layout: 'full',
            fields: [
                { id: 'nursingDiagnoses.list', label: 'Sesterské diagnózy pri príjme' },
                { id: 'nursingDiagnoses.dateTime', label: 'Dátum' },
            ],
        },
    ],
}

/* -------------------------------------------------------------------------- */
/*  Helpers                                                                   */
/* -------------------------------------------------------------------------- */

const measurePageInnerRef = ref<HTMLElement | null>(null)
const measureHeaderRef = ref<HTMLElement | null>(null)
const measureItemsWrapRef = ref<HTMLElement | null>(null)
const measureFooterRef = ref<HTMLElement | null>(null)

let resizeTimer: number | null = null

function chunkFields<T>(items: T[], size: number): T[][] {
    const out: T[][] = []
    for (let i = 0; i < items.length; i += size) {
        out.push(items.slice(i, i + size))
    }
    return out
}

function fieldPairs(section: Section) {
    return chunkFields(section.fields, 2)
}

function isCompact(section: Section) {
    return (section.layout ?? 'compact') === 'compact'
}

function translateValue(value: string): string {
    const fallbackTranslations: Record<string, string> = {
        yes: 'Áno',
        no: 'Nie',
        true: 'Áno',
        false: 'Nie',
    }

    return fallbackTranslations[value] || value
}

function formatValue(value: any, fieldId?: string): string {
    if (Array.isArray(value)) {
        return value
            .map((v) => {
                if (typeof v === 'object' && v !== null && (v as any).code) {
                    return `${(v as any).code} - ${(v as any).description}`
                }

                if (fieldId) {
                    return getValueLabel(fieldId, String(v))
                }

                return translateValue(String(v))
            })
            .join(', ')
    }

    if (typeof value === 'object' && value !== null) {
        if ((value as any).code) {
            return `${(value as any).code} - ${(value as any).description}`
        }

        return JSON.stringify(value)
    }

    const strValue = String(value ?? '').trim()

    if (!strValue) {
        return ''
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(strValue)) {
        const [year, month, day] = strValue.split('-')
        return `${day}.${month}.${year}`
    }

    if (fieldId) {
        return getValueLabel(fieldId, strValue)
    }

    return translateValue(strValue)
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

async function settleLayout() {
    await nextTick()
    await waitForFonts()
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
    await waitForImagesInElement(document.getElementById('measure-root'))
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
}

/* -------------------------------------------------------------------------- */
/*  Images                                                                    */
/* -------------------------------------------------------------------------- */

async function loadStampImage() {
    const companyId = documentData.value.companyId

    if (!companyId) {
        stampUrl.value = null
        return
    }

    try {
        if (stampUrl.value) {
            URL.revokeObjectURL(stampUrl.value)
            stampUrl.value = null
        }

        const res = await api.get(`/v1/companies/${companyId}/stamp`, {
            responseType: 'blob',
        })

        const url = URL.createObjectURL(res.data)
        stampUrl.value = url
        await waitForImageLoad(url)
    } catch {
        stampUrl.value = null
    }
}

async function loadSignatureImage() {
    const representativeId = documentData.value.userId

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
/*  Load                                                                      */
/* -------------------------------------------------------------------------- */

async function loadRecord(documentId: string) {
    loading.value = true

    try {
        const res = await api.get(`/v1/records/${documentId}`)
        const record = res.data?.data?.record_data ?? res.data?.record_data ?? {}

        documentData.value = {
            facilityName: record.company_name ?? '',
            facilityAddress: record.company_address ?? '',
            patientName: record.patient_name ?? '',
            patientIdNumber: record.patient_birth_number ?? '',
            patientHealthCode: record.insurance_code ?? '',
            patientCurrentAddress: record.patient_address ?? '',
            userName: record.user_name ?? '',
            doctorName: record.doctor_name ?? '',
            formData: record.form_data ?? {},
            userId: record.user_id ?? null,
            companyId: record.company_id ?? null,
        }

        await Promise.all([loadStampImage(), loadSignatureImage()])
    } finally {
        loading.value = false

        await settleLayout()
        await recalcPagination()

        window.setTimeout(() => {
            void recalcPagination()
        }, 100)
    }
}

onMounted(async () => {
    window.addEventListener('afterprint', handleAfterPrint)
    window.addEventListener('keydown', handlePrintShortcut)
    window.addEventListener('resize', handleResize)

    await loadRecord(String(route.params.documentId))
})

onBeforeUnmount(() => {
    window.removeEventListener('afterprint', handleAfterPrint)
    window.removeEventListener('keydown', handlePrintShortcut)
    window.removeEventListener('resize', handleResize)

    if (resizeTimer) {
        window.clearTimeout(resizeTimer)
    }

    if (stampUrl.value) {
        URL.revokeObjectURL(stampUrl.value)
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
        pages.value = [{ sections: [...formSpec.sections] }]
        return
    }

    const innerHeight = inner.clientHeight
    const headerHeight = headerEl ? outerHeightWithMargins(headerEl) : 0
    const footerHeight = footerEl ? outerHeightWithMargins(footerEl) : 0

    const safety = 8
    const firstCapacity = innerHeight - headerHeight - safety
    const otherCapacity = innerHeight - safety

    const itemEls = Array.from(itemsWrap.children) as HTMLElement[]
    const heights = itemEls.map((el) => outerHeightWithMargins(el))
    const src = formSpec.sections

    const newPages: Page[] = []
    let current: Section[] = []
    let used = 0
    let capacity = firstCapacity

    for (let i = 0; i < src.length; i++) {
        const section = src[i]
        const sectionHeight = heights[i] ?? 0

        if (current.length > 0 && used + sectionHeight > capacity) {
            newPages.push({ sections: current })
            current = []
            used = 0
            capacity = otherCapacity
        }

        if (section) {
            current.push(section)
            used += sectionHeight
        }
    }

    if (current.length) {
        newPages.push({ sections: current })
    }

    if (!newPages.length) {
        pages.value = [{ sections: [...formSpec.sections] }]
        return
    }

    let lastIdx = newPages.length - 1
    let lastPage = newPages[lastIdx]
    let remaining = 0

    if (lastPage) {
        const isFirstAndLast = lastIdx === 0
        const lastCapacity = isFirstAndLast ? firstCapacity : otherCapacity

        const lastUsed = lastPage.sections.reduce((sum, section) => {
            const idx = src.findIndex((x) => x.id === section.id)
            return sum + (heights[idx] ?? 0)
        }, 0)

        remaining = lastCapacity - lastUsed
    }

    if (footerHeight + safety > remaining) {
        const movedSections: Section[] = []

        while (lastPage && lastPage.sections.length && footerHeight + safety > remaining) {
            const moved = lastPage.sections.pop()
            if (!moved) break

            movedSections.unshift(moved)

            const movedIdx = src.findIndex((x) => x.id === moved.id)
            remaining += heights[movedIdx] ?? 0
        }

        if (lastPage && lastPage.sections.length === 0) {
            newPages.pop()
        }

        if (movedSections.length) {
            newPages.push({ sections: movedSections })
        }
    }

    pages.value = newPages.length ? newPages : [{ sections: [...formSpec.sections] }]
}

watch(
    () => [
        documentData.value.formData,
        documentData.value.facilityName,
        documentData.value.facilityAddress,
        documentData.value.patientName,
        documentData.value.patientIdNumber,
        documentData.value.patientHealthCode,
        documentData.value.patientCurrentAddress,
        documentData.value.userName,
        documentData.value.doctorName,
        stampUrl.value,
        signatureUrl.value,
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

                .record-sheet-wrapper {
                    display: block !important;
                    padding: 0 !important;
                    justify-content: initial !important;
                }

                .pages {
                    display: block !important;
                    gap: 0 !important;
                }

                .page {
                    break-after: page;
                    page-break-after: always;
                    box-shadow: none !important;
                    margin: 0 !important;
                    overflow: hidden !important;
                }

                .page:last-child {
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

function handlePrintShortcut(e: KeyboardEvent) {
    const isPrintShortcut = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p'

    if (!isPrintShortcut) {
        return
    }

    e.preventDefault()
    e.stopPropagation()
    void printPage()
}

/* -------------------------------------------------------------------------- */
/*  View                                                                      */
/* -------------------------------------------------------------------------- */

const title = computed(() => 'OŠETROVATEĽSKÝ ZÁZNAM')
</script>

<template>
    <div class="flex flex-col gap-4">
        <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
            <template #start>
                <span class="text-heading-accent">Ošetrovateľský záznam</span>
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

        <div v-if="!loading" class="record-sheet-wrapper">
            <div id="print-root">
                <div class="pages">
                    <div v-for="(page, pageIndex) in pages" :key="pageIndex" class="page">
                        <div class="page-inner">
                            <div v-if="pageIndex === 0" class="header">
                                <div class="text-center font-bold text-lg mb-3">
                                    {{ title }}
                                </div>

                                <table class="w-full border-collapse text-normal mb-2">
                                    <tbody>
                                        <tr>
                                            <td class="border border-black p-1.5 w-1/2">
                                                Zdravotnícke zariadenie:<br />
                                                <strong>{{ documentData.facilityName }}</strong>
                                            </td>
                                            <td class="border border-black p-1.5 w-1/2">
                                                so sídlom v:<br />
                                                <strong>{{ documentData.facilityAddress }}</strong>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table class="w-full border-collapse text-normal mb-2" style="table-layout: fixed">
                                    <tbody>
                                        <tr>
                                            <td class="border border-black p-1.5 w-1/2">
                                                Meno, priezvisko, titul pacienta/pacientky:<br />
                                                <strong>{{ documentData.patientName }}</strong>
                                            </td>
                                            <td class="border border-black p-1.5 w-1/4">
                                                Rodné číslo:<br />
                                                <strong>{{ documentData.patientIdNumber }}</strong>
                                            </td>
                                            <td class="border border-black p-1.5 w-1/4">
                                                Kód ZP:<br />
                                                <strong>{{ documentData.patientHealthCode }}</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="border border-black p-1.5" colspan="3">
                                                Trvalý pobyt:<br />
                                                <strong>{{ documentData.patientCurrentAddress }}</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="border border-black p-1.5" colspan="3">
                                                Prechodný pobyt:<br />
                                                <strong></strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="border border-black p-1.5" colspan="2">
                                                Kontaktná osoba a vzťah k pacientovi:<br />
                                                <strong></strong>
                                            </td>
                                            <td class="border border-black p-1.5">
                                                Kontakt:<br />
                                                <strong></strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="border border-black p-1.5" colspan="3">
                                                Adresa kontaktnej osoby:<br />
                                                <strong></strong>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table class="w-full border-collapse text-normal mb-2">
                                    <tbody>
                                        <tr>
                                            <td class="border border-black p-1.5 w-1/2">
                                                Ošetrujúci lekár:<br />
                                                <strong>{{ documentData.doctorName }}</strong>
                                            </td>
                                            <td class="border border-black p-1.5 w-1/2">
                                                Pracovisko:<br />
                                                <strong>ADOS</strong>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="content">
                                <section v-for="section in page.sections" :key="section.id" class="print-section">
                                    <div class="section-title">{{ section.title }}</div>

                                    <table
                                        v-if="isCompact(section)"
                                        class="w-full border-collapse text-[0.5rem] field-table compact-table"
                                    >
                                        <colgroup>
                                            <col style="width: 18%" />
                                            <col style="width: 32%" />
                                            <col style="width: 18%" />
                                            <col style="width: 32%" />
                                        </colgroup>
                                        <tbody>
                                            <tr
                                                v-for="(pair, pairIndex) in fieldPairs(section)"
                                                :key="`${section.id}-${pairIndex}`"
                                                class="field-row border-b border-gray-300"
                                            >
                                                <template v-for="field in pair" :key="field.id">
                                                    <td class="field-label-cell">
                                                        {{ field.label }}
                                                    </td>
                                                    <td class="field-value-cell">
                                                        {{ formatValue(documentData.formData[field.id], field.id) || '-' }}
                                                    </td>
                                                </template>

                                                <template v-if="pair.length === 1">
                                                    <td class="field-label-cell"></td>
                                                    <td class="field-value-cell"></td>
                                                </template>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <table
                                        v-else
                                        class="w-full border-collapse text-[0.5rem] field-table full-table"
                                    >
                                        <tbody>
                                            <tr
                                                v-for="field in section.fields"
                                                :key="field.id"
                                                class="field-row border-b border-gray-300"
                                            >
                                                <td class="field-label-cell full-label">
                                                    {{ field.label }}
                                                </td>
                                                <td class="field-value-cell full-value">
                                                    {{ formatValue(documentData.formData[field.id], field.id) || '-' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </section>

                                <div v-if="pageIndex === pages.length - 1" class="footer">
                                    <div class="mt-8 grid grid-cols-2 gap-10 text-normal">
                                        <div class="text-center">
                                            <div class="signature-box"></div>
                                            <div class="border-t border-black mb-2"></div>
                                            podpis pacienta
                                        </div>

                                        <div class="text-center">
                                            <div class="signature-box">
                                                <img
                                                    v-if="stampUrl"
                                                    :src="stampUrl"
                                                    alt="Pečiatka spoločnosti"
                                                    class="stamp-image"
                                                />

                                                <img
                                                    v-if="signatureUrl"
                                                    :src="signatureUrl"
                                                    alt="Podpis odborného zástupcu"
                                                    class="signature-overlay"
                                                />
                                            </div>

                                            <div class="border-t border-black mb-2"></div>
                                            {{ documentData.userName }} <br />
                                            <span class="text-xs">zdravotný pracovník</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="measure-root" aria-hidden="true">
                <div class="page measure-page">
                    <div ref="measurePageInnerRef" class="page-inner">
                        <div ref="measureHeaderRef" class="header">
                            <div class="text-center font-bold text-lg mb-3">
                                {{ title }}
                            </div>

                            <table class="w-full border-collapse text-normal mb-2">
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-1.5 w-1/2">
                                            Zdravotnícke zariadenie:<br />
                                            <strong>{{ documentData.facilityName }}</strong>
                                        </td>
                                        <td class="border border-black p-1.5 w-1/2">
                                            so sídlom v:<br />
                                            <strong>{{ documentData.facilityAddress }}</strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <table class="w-full border-collapse text-normal mb-2" style="table-layout: fixed">
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-1.5 w-1/2">
                                            Meno, priezvisko, titul pacienta/pacientky:<br />
                                            <strong>{{ documentData.patientName }}</strong>
                                        </td>
                                        <td class="border border-black p-1.5 w-1/4">
                                            Rodné číslo:<br />
                                            <strong>{{ documentData.patientIdNumber }}</strong>
                                        </td>
                                        <td class="border border-black p-1.5 w-1/4">
                                            Kód ZP:<br />
                                            <strong>{{ documentData.patientHealthCode }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border border-black p-1.5" colspan="3">
                                            Trvalý pobyt:<br />
                                            <strong>{{ documentData.patientCurrentAddress }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border border-black p-1.5" colspan="3">
                                            Prechodný pobyt:<br />
                                            <strong></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border border-black p-1.5" colspan="2">
                                            Kontaktná osoba a vzťah k pacientovi:<br />
                                            <strong></strong>
                                        </td>
                                        <td class="border border-black p-1.5">
                                            Kontakt:<br />
                                            <strong></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border border-black p-1.5" colspan="3">
                                            Adresa kontaktnej osoby:<br />
                                            <strong></strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <table class="w-full border-collapse text-normal mb-2">
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-1.5 w-1/2">
                                            Ošetrujúci lekár:<br />
                                            <strong>{{ documentData.doctorName }}</strong>
                                        </td>
                                        <td class="border border-black p-1.5 w-1/2">
                                            Pracovisko:<br />
                                            <strong>ADOS</strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div ref="measureItemsWrapRef" class="content">
                            <section v-for="section in formSpec.sections" :key="section.id" class="print-section">
                                <div class="section-title">{{ section.title }}</div>

                                <table
                                    v-if="isCompact(section)"
                                    class="w-full border-collapse text-[0.5rem] field-table compact-table"
                                >
                                    <colgroup>
                                        <col style="width: 18%" />
                                        <col style="width: 32%" />
                                        <col style="width: 18%" />
                                        <col style="width: 32%" />
                                    </colgroup>
                                    <tbody>
                                        <tr
                                            v-for="(pair, pairIndex) in fieldPairs(section)"
                                            :key="`measure-${section.id}-${pairIndex}`"
                                            class="field-row border-b border-gray-300"
                                        >
                                            <template v-for="field in pair" :key="field.id">
                                                <td class="field-label-cell">
                                                    {{ field.label }}
                                                </td>
                                                <td class="field-value-cell">
                                                    {{ formatValue(documentData.formData[field.id], field.id) || '-' }}
                                                </td>
                                            </template>

                                            <template v-if="pair.length === 1">
                                                <td class="field-label-cell"></td>
                                                <td class="field-value-cell"></td>
                                            </template>
                                        </tr>
                                    </tbody>
                                </table>

                                <table
                                    v-else
                                    class="w-full border-collapse text-normal field-table full-table"
                                >
                                    <tbody>
                                        <tr
                                            v-for="field in section.fields"
                                            :key="`measure-${section.id}-${field.id}`"
                                            class="field-row border-b border-gray-300"
                                        >
                                            <td class="field-label-cell full-label">
                                                {{ field.label }}
                                            </td>
                                            <td class="field-value-cell full-value">
                                                {{ formatValue(documentData.formData[field.id], field.id) || '-' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </section>
                        </div>

                        <div ref="measureFooterRef" class="footer">
                            <div class="mt-8 grid grid-cols-2 gap-10 text-[0.6rem]">
                                <div class="text-center">
                                    <div class="border-t border-black mb-2"></div>
                                    podpis pacienta
                                </div>

                                <div class="text-center">
                                    <div class="signature-box">
                                        <img
                                            v-if="stampUrl"
                                            :src="stampUrl"
                                            alt="Pečiatka spoločnosti"
                                            class="stamp-image"
                                        />

                                        <img
                                            v-if="signatureUrl"
                                            :src="signatureUrl"
                                            alt="Podpis odborného zástupcu"
                                            class="signature-overlay"
                                        />
                                    </div>

                                    <div class="border-t border-black mb-2"></div>
                                    {{ documentData.userName }} <br />
                                    <span class="text-xs">zdravotný pracovník</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.record-sheet-wrapper {
    display: flex;
    justify-content: center;
    padding: 2rem;
}

.pages {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    background: #fff;
    box-sizing: border-box;
    padding: 12mm;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.page-inner {
    height: 100%;
    box-sizing: border-box;
}

.header {
    break-after: avoid;
    page-break-after: avoid;
}

.content {
    width: 100%;
}

.print-section {
    border: 1px solid #000;
    padding: 2px;
    margin-bottom: 6px;
    font-size: 0.6rem;
}

.section-title {
    font-weight: 700;
    text-transform: uppercase;
    border-bottom: 1px solid #000;
    padding: 2px 4px;
    margin-bottom: 2px;
    font-size: 0.5rem;
    line-height: 1.15;
}

.field-table {
    table-layout: fixed;
    width: 100%;
    font-size: 0.55rem;
}

.field-table td {
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
    padding: 3px 4px !important;
    vertical-align: top;
}

.field-label-cell {
    font-weight: 700;
}

.field-value-cell {
    font-weight: 400;
}

.full-label {
    width: 32%;
}

.full-value {
    width: 68%;
}

.footer {
    margin-top: 10px;
    font-size: 0.6rem;
}

.signature-box {
    position: relative;
    height: 65px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.stamp-image {
    max-width: 150px;
    max-height: 55px;
    object-fit: contain;
    opacity: 0.7;
}

.signature-overlay {
    position: absolute;
    z-index: 2;
    max-width: 180px;
    max-height: 90px;
    object-fit: contain;
    top: 50%;
    left: 58%;
    transform: translate(-40%, -55%);
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
        width: auto !important;
        display: block !important;
    }

    :global(.record-sheet-wrapper) {
        display: block !important;
        padding: 0 !important;
        justify-content: initial !important;
    }

    :global(.pages) {
        display: block !important;
        gap: 0 !important;
    }

    :global(.page) {
        box-shadow: none !important;
        margin: 0 !important;
        break-after: page !important;
        page-break-after: always !important;
        overflow: hidden !important;
    }

    :global(.page:last-child) {
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