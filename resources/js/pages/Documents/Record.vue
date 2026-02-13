<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { getValueLabel } from '@/utils/valueTranslations'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

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
}

type Field = { id: string; label: string }
type Section = { id: string; title: string; fields: Field[] }
type Page = { sections: Section[] }

/* -------------------------------------------------------------------------- */
/*  Form spec                                                                  */
/* -------------------------------------------------------------------------- */

const formSpec: { sections: Section[] } = {
  sections: [
    {
      id: 'basic',
      title: 'Základné údaje',
      fields: [
        { id: 'diagnosis', label: 'Lekárska diagnóza' },
        { id: 'recommendedPharmacy', label: 'Odporučená farmakoterapia' },
        { id: 'admissionDate', label: 'Dátum prijatia do starostlivosti' },
      ],
    },
    {
      id: 'allergies',
      title: 'Alergie',
      fields: [
        { id: 'allergies', label: 'Alergie' },
        { id: 'allergies.otherFindings', label: 'Iné zistenia' },
      ],
    },
    { id: 'abuses', title: 'Abúzy', fields: [{ id: 'abuses', label: 'Abúzy' }] },
    {
      id: 'family',
      title: 'Rodinná anamnéza',
      fields: [
        { id: 'familyAnamnesis', label: 'Rodinná anamnéza' },
        { id: 'familyAnamnesis.otherFindings', label: 'Poznámka' },
      ],
    },
    {
      id: 'social',
      title: 'Sociálna anamnéza',
      fields: [
        { id: 'employment', label: 'Povolanie' },
        { id: 'socialConditions', label: 'Sociálne podmienky' },
        { id: 'socialConditions.otherFindings', label: 'Iné zistenia' },
        { id: 'socialStatus', label: 'Sociálny stav' },
        { id: 'socialContacts', label: 'Sociálne kontakty' },
        { id: 'supportSystems', label: 'Systémy podpory' },
        { id: 'socialCulture', label: 'Kultúra a sociálne faktory' },
        { id: 'socialMedia', label: 'Sociálne média' },
      ],
    },
    {
      id: 'healthPerception',
      title: 'Vnímanie zdravia',
      fields: [{ id: 'healthPerception.description', label: 'Subjektívny popis problémov pacienta' }],
    },
    {
      id: 'nursingAssessment',
      title: 'Vstupný záznam sesterského posúdenia zdravotného stavu pacienta',
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
      id: 'consciousnessOrientation',
      title: 'Vedomie a orientácia',
      fields: [
        { id: 'consciousness', label: 'Vedomie' },
        { id: 'consciousnessOtherNotes', label: 'Iné zistenia' },
        { id: 'orientation', label: 'Orientácia' },
        { id: 'orientationOtherNotes', label: 'Iné zistenia' },
      ],
    },
    {
      id: 'circulation',
      title: 'Cirkulácia',
      fields: [
        { id: 'bloodPressure', label: 'TK (mmHg)' },
        { id: 'temperature', label: 'TT (°C)' },
        { id: 'pulse', label: 'P (/min)' },
        { id: 'circulation.problemExists', label: 'Cirkulácia – problém' },
        { id: 'hypotensionHypertension', label: 'Hypotenzita/Hypertenzita' },
        { id: 'irregularPulse', label: 'Nepravidelný pulz' },
        { id: 'circulation.otherNotes', label: 'Iné zistenia' },
      ],
    },
    {
      id: 'breathing',
      title: 'Dýchanie',
      fields: [
        { id: 'respiratoryRate', label: 'D (/min)' },
        { id: 'breathing.problemExists', label: 'Dýchanie – problém' },
        { id: 'irregularities', label: 'Nepravidelnosti' },
        { id: 'breathing.otherNotes', label: 'Iné zistenia' },
        { id: 'suctioning', label: 'Odsávanie' },
        { id: 'oxygenTherapy', label: 'Kyslíková terapia' },
        { id: 'mechanicalVentilation', label: 'Mechanická ventilácia' },
        { id: 'inhalation', label: 'Inhalácia' },
      ],
    },
    {
      id: 'nutrition',
      title: 'Výživa',
      fields: [
        { id: 'nutrition.diet', label: 'Diéta č.' },
        { id: 'nutrition.weightTrend', label: 'Trend hmotnosti' },
        { id: 'nutrition.weightKg', label: 'Hmotnosť (kg)' },
        { id: 'nutrition.problemExists', label: 'Výživa – problém' },
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
      fields: [
        { id: 'defecation.problemExists', label: 'Defekácia – problém' },
        { id: 'defecation.symptoms', label: 'Symptómy defekácie' },
        { id: 'defecation.stomaCare', label: 'Starostlivosť o stómiu' },
        { id: 'defecation.stomaCareDate', label: 'Dátum zavedenia stómie' },
        { id: 'defecation.otherNotes', label: 'Iné zistenia' },
        { id: 'defecation.stomaAssistanceNeeded', label: 'Potreba pomoci pri ošetrovaní stómie' },
        { id: 'defecation.regulationUsed', label: 'Regulácia vyprázdňovania' },
        { id: 'defecation.regulationMethods', label: 'Spôsoby regulácie' },
        { id: 'defecation.regulationOtherNotes', label: 'Iné zistenia regulácie' },
        { id: 'urination.diuresis', label: 'Diuréza (ml/24 hod.)' },
        { id: 'urination.problemExists', label: 'Močenie – problém' },
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
      id: 'sleep',
      title: 'Spánok',
      fields: [
        { id: 'sleep.problemExists', label: 'Spánok – problém' },
        { id: 'sleep.findings', label: 'Zistenia' },
        { id: 'sleep.otherNotes', label: 'Iné zistenia' },
      ],
    },
    {
      id: 'mobility',
      title: 'Mobilita',
      fields: [
        { id: 'mobility.level', label: 'Úroveň mobility' },
        { id: 'mobility.compensatoryAids', label: 'Kompenzačné pomôcky' },
        { id: 'mobility.compensatoryAidsDetails', label: 'Aké kompenzačné pomôcky' },
      ],
    },
    {
      id: 'movement',
      title: 'Pohybový systém',
      fields: [
        { id: 'movement.problemExists', label: 'Pohybový systém – problém' },
        { id: 'movement.findings', label: 'Zistenia' },
        { id: 'movement.otherNotes', label: 'Iné zistenia' },
      ],
    },
    {
      id: 'skin',
      title: 'Koža / Edémy / Sliznice / Hygiena',
      fields: [
        { id: 'skin.problemExists', label: 'Koža – problém' },
        { id: 'skin.temperature', label: 'Teplota kože' },
        { id: 'skin.moisture', label: 'Vlhkosť kože' },
        { id: 'skin.color', label: 'Farba' },
        { id: 'skin.turgor', label: 'Turgor' },
        { id: 'skin.integrity', label: 'Celistvosť kože' },
        { id: 'skin.changes', label: 'Zmeny na koži' },
        { id: 'skin.defectLocation', label: 'Lokalizácia defektu' },
        { id: 'skin.defectSizeCm', label: 'Veľkosť defektu (cm)' },
        { id: 'skin.patientDayAfterSurgery', label: 'Deň po operácii' },
        { id: 'edema.problemExists', label: 'Edémy – problém' },
        { id: 'edema.type', label: 'Typ edému' },
        { id: 'edema.measures', label: 'Opatrenia' },
        { id: 'mucosa.problemExists', label: 'Sliznice – problém' },
        { id: 'mucosa.findings', label: 'Zistenia na sliznici' },
        { id: 'hygiene.statusOnAdmission', label: 'Hygienický stav pri prijatí' },
        { id: 'hygiene.selfCare', label: 'Hygienu vykonáva' },
        { id: 'skinMucosa.otherNotes', label: 'Iné zistenia' },
      ],
    },
    {
      id: 'postpartum',
      title: 'Pôrodné posúdenie (šestonedelie)',
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
      id: 'pain',
      title: 'Bolesť',
      fields: [
        { id: 'pain.problemExists', label: 'Bolesť – problém' },
        { id: 'pain.type', label: 'Typ bolesti' },
        { id: 'pain.location', label: 'Lokalizácia' },
        { id: 'pain.character', label: 'Charakter' },
        { id: 'pain.otherNotes', label: 'Iné zistenia' },
      ],
    },
    {
      id: 'communication',
      title: 'Komunikácia',
      fields: [
        { id: 'communication.type', label: 'Spôsob komunikácie' },
        { id: 'communication.problemExists', label: 'Komunikácia – problém' },
        { id: 'communication.issues', label: 'problémy v komunikácii' },
        { id: 'communication.otherNotes', label: 'Iné zistenia' },
      ],
    },
    {
      id: 'learning',
      title: 'Učenie, zmyslové vnímanie',
      fields: [
        { id: 'learning.problemExists', label: 'problém s učením/zmyslami' },
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
      fields: [
        { id: 'psychological.problemExists', label: 'Psychické potreby – problém' },
        { id: 'psychological.mood', label: 'Nálada' },
        { id: 'psychological.feelings', label: 'Pocity' },
        { id: 'social.problemExists', label: 'Sociálne potreby – problém' },
        { id: 'social.supportDependency', label: 'Sociálna pomoc' },
        { id: 'spiritual.problemExists', label: 'Duchovné potreby – problém' },
        { id: 'psychosocial.otherNotes', label: 'Iné zistenia' },
      ],
    },
    {
      id: 'careDeficits',
      title: 'Nedostatočnosť v oblasti',
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
      fields: [
        { id: 'instruction.topics', label: 'Témy poučenia' },
        { id: 'instruction.handoverOnAdmission', label: 'Odovzdané pri prijatí' },
        { id: 'instruction.date', label: 'Dátum' },
      ],
    },
    {
      id: 'nursingDiagnoses',
      title: 'Stanovenie sesterských diagnóz pri príjme',
      fields: [
        { id: 'nursingDiagnoses.list', label: 'Sesterské diagnózy pri príjme' },
        { id: 'nursingDiagnoses.dateTime', label: 'Dátum' },
      ],
    },
  ],
}

/* -------------------------------------------------------------------------- */
/*  State                                                                     */
/* -------------------------------------------------------------------------- */

const route = useRoute()
const loading = ref(false)
const isPrinting = ref(false)

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
})

const pages = ref<Page[]>([])

/* measurer refs (same idea as your DZC page) */
const measurePageInnerRef = ref<HTMLElement | null>(null)
const measureHeaderRef = ref<HTMLElement | null>(null)
const measureItemsWrapRef = ref<HTMLElement | null>(null)
const measureFooterRef = ref<HTMLElement | null>(null)

/* -------------------------------------------------------------------------- */
/*  Load                                                                       */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
  await loadRecord(String(route.params.documentId))
})

async function loadRecord(documentId: string) {
  loading.value = true
  try {
    const res = await api.get(`/v1/records/${documentId}`)
    const record = res.data?.data?.record_data ?? {}

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
    }
  } finally {
    loading.value = false
  }
}

/* -------------------------------------------------------------------------- */
/*  Formatting                                                                 */
/* -------------------------------------------------------------------------- */

function translateValue(value: string): string {
  const fallbackTranslations: Record<string, string> = { yes: 'Áno', no: 'Nie' }
  return fallbackTranslations[value] || value
}

function formatValue(value: any, fieldId?: string): string {
  if (Array.isArray(value)) {
    return value
      .map(v => {
        if (typeof v === 'object' && v !== null && (v as any).code) return `${(v as any).code} - ${(v as any).description}`
        if (fieldId) return getValueLabel(fieldId, String(v))
        return translateValue(String(v))
      })
      .join(', ')
  }

  if (typeof value === 'object' && value !== null) {
    if ((value as any).code) return `${(value as any).code} - ${(value as any).description}`
    return JSON.stringify(value)
  }

  const strValue = String(value ?? '')

  // Format dates to Slovak format (DD.MM.YYYY)
  if (/^\d{4}-\d{2}-\d{2}$/.test(strValue)) {
    const [year, month, day] = strValue.split('-')
    return `${day}.${month}.${year}`
  }

  if (fieldId) return getValueLabel(fieldId, strValue)
  return translateValue(strValue)
}

function outerHeightWithMargins(el: HTMLElement) {
  const style = window.getComputedStyle(el)
  const mt = parseFloat(style.marginTop || '0')
  const mb = parseFloat(style.marginBottom || '0')
  return el.getBoundingClientRect().height + mt + mb
}

async function recalcPagination() {
  await nextTick()
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))

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

  const SAFETY = 18
  const firstCapacity = innerHeight - headerHeight - SAFETY
  const otherCapacity = innerHeight - SAFETY

  const itemEls = Array.from(itemsWrap.children) as HTMLElement[]
  const heights = itemEls.map(el => outerHeightWithMargins(el))

  const src = formSpec.sections
  const newPages: Page[] = []

  let current: Section[] = []
  let used = 0
  let capacity = firstCapacity

  for (let i = 0; i < src.length; i++) {
    const section = src[i]
    const h = heights[i] ?? 0

    // new page if would overflow and we already have something on the page
    if (current.length && used + h > capacity) {
      newPages.push({ sections: current })
      current = []
      used = 0
      capacity = otherCapacity
    }

    if (section) {
      current.push(section)
      used += h
    }
  }

  if (current.length) newPages.push({ sections: current })

  // Ensure footer fits on the last page by moving sections forward if needed
  if (newPages.length) {
    let lastIdx = newPages.length - 1
    const lastIsFirst = lastIdx === 0
    const lastCapacity = (lastIsFirst ? firstCapacity : otherCapacity)
    const lastPage = newPages[lastIdx]
    const lastUsed = lastPage ? lastPage.sections.reduce((sum, s) => {
      const idx = src.findIndex(x => x.id === s.id)
      return sum + (heights[idx] ?? 0)
    }, 0) : 0

    let remaining = lastCapacity - lastUsed

    if (footerHeight + SAFETY > remaining) {
      // Move sections from the end of last page to a new page until footer fits
      const overflowPage: Section[] = []
      while (newPages[lastIdx] && newPages[lastIdx].sections.length && footerHeight + SAFETY > remaining) {
        const moved = newPages[lastIdx].sections.pop()
        if (!moved) break
        overflowPage.unshift(moved)

        const movedIdx = src.findIndex(x => x.id === moved.id)
        remaining += (heights[movedIdx] ?? 0)
      }

      if (overflowPage.length) {
        // last page might become empty => remove it
        if (newPages[lastIdx] && !newPages[lastIdx].sections.length) newPages.pop()
        newPages.push({ sections: overflowPage })
      }
    }
  }

  pages.value = newPages.length ? newPages : [{ sections: [...formSpec.sections] }]
}

watch(
  () => [documentData.value.formData, documentData.value.facilityName, documentData.value.patientName],
  async () => {
    await recalcPagination()
  },
  { deep: true, immediate: true }
)

/* -------------------------------------------------------------------------- */
/*  Printing (same as DZC: show only #print-root)                              */
/* -------------------------------------------------------------------------- */

async function printPage() {
  isPrinting.value = true
  await nextTick()
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  window.print()
}

function handleAfterPrint() {
  isPrinting.value = false
}

onMounted(() => window.addEventListener('afterprint', handleAfterPrint))
onBeforeUnmount(() => window.removeEventListener('afterprint', handleAfterPrint))

/* -------------------------------------------------------------------------- */
/*  Small helpers                                                              */
/* -------------------------------------------------------------------------- */

const title = computed(() => 'OŠETROVATEĽSKÝ ZÁZNAM')
</script>

<template>
  <LoadingOverlay :show="loading" text="" />

  <div class="flex flex-col gap-4">
    <!-- Toolbar -->
    <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print">
      <template #start>
        <span class="text-heading-accent">Ošetrovateľský záznam</span>
      </template>

      <template #end>
        <Button
          icon="bi bi-printer"
          class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          @click="printPage"
        />
      </template>
    </Toolbar>

    <div v-if="!loading" class="record-sheet-wrapper">
      <!-- PRINTED CONTENT (only this will be visible in print) -->
      <div id="print-root">
        <div class="pages">
          <div v-for="(page, pageIndex) in pages" :key="pageIndex" class="page">
            <div class="page-inner">
              <!-- Header only on first page -->
              <div v-if="pageIndex === 0" class="header">
                <div class="text-center font-bold text-lg mb-4">
                  {{ title }}
                </div>

                <table class="w-full border-collapse text-sm mb-2">
                  <tbody>
                    <tr>
                      <td class="border border-black p-2 w-1/2">
                        Zdravotnícke zariadenie:<br />
                        <strong>{{ documentData.facilityName }}</strong>
                      </td>
                      <td class="border border-black p-2 w-1/2">
                        so sídlom v:<br />
                        <strong>{{ documentData.facilityAddress }}</strong>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <table class="w-full border-collapse text-sm mb-2" style="table-layout: fixed">
                  <tbody>
                    <tr>
                      <td class="border border-black p-2 w-1/2">
                        Meno, priezvisko, titul pacienta/pacientky:<br />
                        <strong>{{ documentData.patientName }}</strong>
                      </td>
                      <td class="border border-black p-2 w-1/4">
                        Rodné číslo:<br />
                        <strong>{{ documentData.patientIdNumber }}</strong>
                      </td>
                      <td class="border border-black p-2 w-1/4">
                        Kód ZP:<br />
                        <strong>{{ documentData.patientHealthCode }}</strong>
                      </td>
                    </tr>
                    <tr>
                      <td class="border border-black p-2" colspan="3">
                        Trvalý pobyt:<br />
                        <strong>{{ documentData.patientCurrentAddress }}</strong>
                      </td>
                    </tr>
                    <tr>
                      <td class="border border-black p-2" colspan="3">
                        Prechodný pobyt:<br />
                        <strong></strong>
                      </td>
                    </tr>
                    <tr>
                      <td class="border border-black p-2" colspan="2">
                        Kontaktná osoba a vzťah k pacientovi:<br />
                        <strong></strong>
                      </td>
                      <td class="border border-black p-2" colspan="1">
                        Kontakt:<br />
                        <strong></strong>
                      </td>
                    </tr>
                    <tr>
                      <td class="border border-black p-2" colspan="3">
                        Adresa kontaktnej osoby:<br />
                        <strong></strong>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <table class="w-full border-collapse text-sm mb-2">
                  <tbody>
                    <tr>
                      <td class="border border-black p-2 w-1/2">
                        Ošetrujúci lekár:<br />
                        <strong>{{ documentData.doctorName }}</strong>
                      </td>
                      <td class="border border-black p-2 w-1/2">
                        Pracovisko:<br />
                        <strong>ADOS</strong>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Sections -->
              <div class="content">
                <section v-for="section in page.sections" :key="section.id" class="print-section">
                  <div class="section-title">{{ section.title }}</div>

                  <table class="w-full border-collapse text-xs field-table">
                    <tbody>
                      <tr v-for="field in section.fields" :key="field.id" class="field-row border-b border-gray-300">
                        <td class="p-1 w-2/5 text-xs font-semibold align-top">
                          {{ field.label }}
                        </td>
                        <td class="p-1 w-3/5 text-xs align-top">
                          {{ formatValue(documentData.formData[field.id], field.id) || '-' }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </section>

                <div v-if="pageIndex === pages.length - 1" class="footer">
                  <div class="mt-12 grid grid-cols-2 gap-12 text-sm">
                    <div class="text-center">
                      <div class="border-t-1 border-black mb-2"></div>
                      podpis sestry/zdravotného pracovníka a pečiatka
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div id="measure-root" aria-hidden="true">
        <div class="page ruler-page measure-page">
          <div ref="measurePageInnerRef" class="page-inner">
            <!-- Header measurer: MUST match real first-page header -->
            <div ref="measureHeaderRef" class="header">
              <div class="text-center font-bold text-lg mb-4">
                {{ title }}
              </div>

              <table class="w-full border-collapse text-sm mb-2">
                <tbody>
                  <tr>
                    <td class="border border-black p-2 w-1/2">
                      Zdravotnícke zariadenie:<br />
                      <strong>{{ documentData.facilityName }}</strong>
                    </td>
                    <td class="border border-black p-2 w-1/2">
                      so sídlom v:<br />
                      <strong>{{ documentData.facilityAddress }}</strong>
                    </td>
                  </tr>
                </tbody>
              </table>

              <table class="w-full border-collapse text-sm mb-2" style="table-layout: fixed">
                <tbody>
                  <tr>
                    <td class="border border-black p-2 w-1/2">
                      Meno, priezvisko, titul pacienta/pacientky:<br />
                      <strong>{{ documentData.patientName }}</strong>
                    </td>
                    <td class="border border-black p-2 w-1/4">
                      Rodné číslo:<br />
                      <strong>{{ documentData.patientIdNumber }}</strong>
                    </td>
                    <td class="border border-black p-2 w-1/4">
                      Kód ZP:<br />
                      <strong>{{ documentData.patientHealthCode }}</strong>
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-black p-2" colspan="3">
                      Trvalý pobyt:<br />
                      <strong>{{ documentData.patientCurrentAddress }}</strong>
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-black p-2" colspan="3">
                      Prechodný pobyt:<br />
                      <strong></strong>
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-black p-2" colspan="2">
                      Kontaktná osoba a vzťah k pacientovi:<br />
                      <strong></strong>
                    </td>
                    <td class="border border-black p-2" colspan="1">
                      Kontakt:<br />
                      <strong></strong>
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-black p-2" colspan="3">
                      Adresa kontaktnej osoby:<br />
                      <strong></strong>
                    </td>
                  </tr>
                </tbody>
              </table>

              <table class="w-full border-collapse text-sm mb-2">
                <tbody>
                  <tr>
                    <td class="border border-black p-2 w-1/2">
                      Ošetrujúci lekár:<br />
                      <strong>{{ documentData.doctorName }}</strong>
                    </td>
                    <td class="border border-black p-2 w-1/2">
                      Pracovisko:<br />
                      <strong>ADOS</strong>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Items measurer: each child = one section block -->
            <div ref="measureItemsWrapRef" class="content">
              <section v-for="section in formSpec.sections" :key="section.id" class="print-section">
                <div class="section-title">{{ section.title }}</div>

                <table class="w-full border-collapse text-xs field-table">
                  <tbody>
                    <tr v-for="field in section.fields" :key="field.id" class="field-row border-b border-gray-300">
                      <td class="p-1 w-2/5 text-xs font-semibold align-top">
                        {{ field.label }}
                      </td>
                      <td class="p-1 w-3/5 text-xs align-top">
                        {{ formatValue(documentData.formData[field.id], field.id) || '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </section>
            </div>

            <!-- Footer measurer (we use it to ensure last page has space) -->
            <div ref="measureFooterRef" class="footer">
              <table class="w-full border-collapse text-sm">
                <tbody>
                  <tr>
                    <td class="border border-black p-2 w-1/2">
                      Ošetrujúci lekár:<br />
                      <strong>{{ documentData.doctorName }}</strong>
                    </td>
                    <td class="border border-black p-2 w-1/2">
                      Sestra/Zdravotný pracovník:<br />
                      <strong>{{ documentData.userName }}</strong>
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="mt-12 grid grid-cols-2 gap-12 text-sm">
                <div class="text-center">
                  <div class="border-t-1 border-black mb-2"></div>
                  podpis lekára a pečiatka
                </div>
                <div class="text-center">
                  <div class="border-t-1 border-black mb-2"></div>
                  podpis sestry/zdravotného pracovníka a pečiatka
                </div>
              </div>
            </div>
          </div>
          <!-- /page-inner -->
        </div>
      </div>
      <!-- /measurer -->
    </div>
  </div>
</template>

<style scoped>
.record-sheet-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

/* Visible pages stack */
.pages {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* A4 page */
.page {
  width: 210mm;
  height: 297mm;
  margin: 0 auto;
  background: #fff;
  box-sizing: border-box;
  padding: 14mm;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.page-inner {
  height: 100%;
}

.header {
  break-after: avoid;
  page-break-after: avoid;
}

/* section blocks */
.print-section {
  border: 1px solid #000;
  padding: 8px;
  margin-bottom: 10px;
  font-size: 0.65rem;
}

.section-title {
  font-weight: 700;
  text-transform: uppercase;
  border-bottom: 1px solid #000;
  padding-bottom: 4px;
  margin-bottom: 6px;
  font-size: 0.7rem;
}

/* wrapping in values */
.field-table {
  table-layout: fixed;
  font-size: 0.65rem;
}
.field-table td {
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
  padding: 4px !important;
}

/* Footer spacing */
.footer {
  margin-top: 12px;
  font-size: 0.65rem;
}

/* hidden measurer */
#measure-root {
  position: absolute;
  left: -99999px;
  top: 0;
  width: 0;
  height: 0;
  overflow: hidden;
  pointer-events: none;
  opacity: 0;
}
.measure-page {
  opacity: 0;
}

/* PRINT (same behavior as your working DZC page) */
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
  }

  :global(.page) {
    box-shadow: none !important;
    margin: 0 auto !important;
    break-after: page !important;
    page-break-after: always !important;
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
