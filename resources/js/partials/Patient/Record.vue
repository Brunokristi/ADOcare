<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/api'

interface Patient {
  id: number
  name: string
  birthNumber: string
}

interface Diagnosis {
  id: number
  code: string
  description: string
}

// Form data - Page 1
const patientSearch = ref('')
const selectedPatient = ref<Patient | null>(null)
const patientSuggestions = ref<Patient[]>([])

const medicalDiagnosis = ref<Diagnosis | null>(null)
const filteredDiagnoses = ref<Diagnosis[]>([])
const recommendedPharmacy = ref('')
const treatingDoctor = ref('')
const workplace = ref('ADOS')
const admissionDate = ref<Date | null>(null)

// Allergies
const allergies = ref({
  medicines: false,
  food: false,
  disinfectants: false,
  plaster: false,
  bite: false,
  other: ''
})

// Abuses
const abuses = ref({
  caffeine: false,
  nicotine: false,
  alcohol: false,
  medicines: false,
  drugs: false
})

// Family anamnesis
const familyAnamnesis = ref({
  IM: false,
  DM: false,
  ICHS: false,
  TBC: false,
  CA: false,
  notes: ''
})

// Social anamnesis
const socialAnamnesis = ref({
  profession: '',
  socialConditions: '',
  socialStatus: '',
  socialContact: '',
  friends: false,
  neighbors: false,
  selfHelpGroups: false,
  careService: false,
  culturalSituation: '',
  TV: false,
  radio: false,
  newspaper: false,
  otherNotes: ''
})

// Health perception
const healthPerception = ref('')

// Nursing assessment entry
const nursingAssessment = ref({
  caredRecommendedBy: '',
  otherDoctorName: '',
  transferredFromFacility: '',
  department: '',
  lastHospitalizationFrom: null,
  lastHospitalizationTo: null,
  consciousness: '',
  consciousnessOtherNotes: '',
  orientation: '',
  orientationHint: '',
  orientationOtherNotes: ''
})

// Options
const caredRecommendedByOptions = [
  { label: 'Všeobecný lekár', value: 'gp' },
  { label: 'Lekár LSPP', value: 'lspp' },
  { label: 'ZZS', value: 'zzs' }
]

const consciousnessOptions = [
  { label: 'Pri vedomí', value: 'conscious' },
  { label: 'Somnolencia', value: 'somnolence' },
  { label: 'Semikóma', value: 'semicoma' },
  { label: 'Kóma', value: 'coma' }
]

const orientationOptions = [
  { label: 'Orientovaný', value: 'oriented' },
  { label: 'Dezorientovaný', value: 'disoriented' }
]

// Circulation
const circulation = ref({
  bloodPressure: '',
  temperature: '',
  pulse: '',
  problemExists: '',
  hypotensionHypertension: '',
  irregularPulse: false,
  pacemaker: false,
  otherNotes: ''
})

// Breathing
const breathing = ref({
  respiratoryRate: '',
  problemExists: '',
  irregular: false,
  fast: false,
  slow: false,
  difficult: false,
  shallow: false,
  deepened: false,
  apneicPauses: false,
  stridor: false,
  dyspneaAtRest: false,
  cough: false,
  coughType: '',
  tracheostomy: false,
  suctioning: false,
  oxygenTherapy: false,
  mechanicalVentilation: false,
  inhalation: false,
  otherNotes: ''
})

// Nutrition
const nutrition = ref({
  diet: '',
  problemExists: '',
  obesity: false,
  cachexia: false,
  weightChange: '',
  appetiteLoss: false,
  nausea: false,
  vomiting: false,
  diarrhea: false,
  swallowingDisorder: false,
  heartburn: false,
  supplementSupport: '',
  supplements: '',
  tasteAppetite: '',
  foodIntake: '',
  nasogastricTubeDate: null,
  gastrostomy: false,
  gastrostomyDate: null,
  peg: false,
  pegDate: null,
  fluidIntake: '',
  enterallyParenterally: '',
  sipping: false,
  cvk: false,
  cvkDate: null,
  peripheralAccess: false,
  peripheralAccessDate: null,
  dentures: '',
  otherNotes: ''
})

// Elimination
const elimination = ref({
  defecationProblem: '',
  defecationRegularity: '',
  bowelMovement: '',
  stoolConsistency: '',
  diarrhea: false,
  constipation: false,
  hemorrhoids: false,
  stoma: false,
  stomaDate: null,
  defecationOtherNotes: '',
  micturitionDiuresis: '',
  micturitionProblem: '',
  dysuria: false,
  retention: false,
  urinaryIncontinence: false,
  absorptivePads: false,
  catheter: false,
  catheterDate: null,
  colorUrine: '',
  colorUrineText: '',
  urostomy: false,
  urostomyDate: null,
  micturitionOtherNotes: ''
})

// Sleep
const sleep = ref({
  problemExists: '',
  insomnia: false,
  nightAwakenings: false,
  pharmacotherapy: false,
  otherNotes: ''
})

// Mobility
const mobility = ref({
  mobilityLevel: '',
  compensatoryDevices: '',
  musculoskeletalProblem: '',
  deformation: false,
  tremblingExtremities: false,
  fracture: false,
  paralysis: false,
  amputation: false,
  musculoskeletalOtherNotes: ''
})

// Skin
const skin = ref({
  skinProblem: '',
  temperature: '',
  moisture: '',
  color: '',
  turgor: '',
  integrity: '',
  rashes: false,
  itching: false,
  peeling: false,
  maceration: false,
  bruising: false,
  inflammation: false,
  woundType: '',
  bleeding: false,
  ulcusCruris: false,
  gangrene: false,
  decubitus: false,
  localization: '',
  defectSize: '',
  postOperativeDays: '',
  edemaExists: '',
  edemaType: '',
  bandageLowerExtremity: false,
  antiemboliStockings: false,
  vascularExercise: false,
  mucousMembranes: '',
  mucousOtherNotes: '',
  hygieneStatus: '',
  hygienePerformedBy: '',
  hygieneOtherNotes: ''
})

// Pain
const pain = ref({
  painExists: '',
  painIntensity: '',
  painCharacter: '',
  painLocalization: '',
  painTiming: '',
  painManagement: '',
  otherPainNotes: ''
})

// Communication
const communication = ref({
  communicationProblem: '',
  verbalCommunication: '',
  nonVerbalCommunication: '',
  comprehension: '',
  expression: '',
  hearing: '',
  vision: '',
  speech: '',
  otherCommunicationNotes: ''
})

// Psychosocial needs
const psychologicalNeeds = ref({
  needsExist: '',
  anxiety: false,
  depression: false,
  anger: false,
  fear: false,
  confusion: false,
  lowSelfEsteem: false,
  suicidalIdeation: false,
  otherPsychologicalNotes: ''
})

const socialNeeds = ref({
  needsExist: '',
  familySeparation: false,
  roleChange: false,
  socialIsolation: false,
  economicConcern: false,
  workConcern: false,
  otherSocialNotes: ''
})

const spiritualNeeds = ref({
  needsExist: '',
  faith: '',
  religiousPractice: '',
  religiousService: false,
  spiritualConflict: false,
  needsChaplain: false,
  otherSpiritualNotes: ''
})

// Learning needs
const learningNeeds = ref({
  needsExist: '',
  learningAbility: '',
  preferredMethod: '',
  topicsOfInterest: '',
  culturalConsiderations: '',
  otherLearningNotes: ''
})

// Nursing diagnoses
const nursingDiagnoses = ref({
  diagnosis1: '',
  diagnosis2: '',
  diagnosis3: '',
  diagnosis4: '',
  diagnosis5: '',
  additionalDiagnoses: ''
})

// Nursing plan
const nursingPlan = ref({
  priorityGoals: '',
  interventions: '',
  additionalNotes: ''
})

// Signature section
const signatureSection = ref({
  recordedDate: null,
  recordedBy: '',
  recordedByTitle: '',
  supervisedDate: null,
  supervisedBy: '',
  supervisedByTitle: '',
  notes: ''
})

// Options
const socialConditionOptions = [
  { label: 'Žije sám(a)', value: 'alone' },
  { label: 's rodinou', value: 'family' },
  { label: 'v zariadení sociálnych služieb', value: 'facility' }
]

const socialStatusOptions = [
  { label: 'Zamestnaný(á)', value: 'employed' },
  { label: 'Dôchodca', value: 'retired' },
  { label: 'Materskú dovolenku', value: 'maternity' },
  { label: 'Nezamestnaný(á)', value: 'unemployed' },
  { label: 'Invalidný(á) dôchodca', value: 'disability' }
]

const socialContactOptions = [
  { label: 'Deti', value: 'children' },
  { label: 'Príbuzní', value: 'relatives' }
]

const culturalOptions = [
  { label: 'Uprednostňuje samotu', value: 'solitude' },
  { label: 'Spoločnosť', value: 'company' }
]

async function searchPatients(event: any) {
  try {
    const q = event.query?.trim() ?? ''
    if (!q || q.length < 1) {
      patientSuggestions.value = []
      return
    }

    // TODO: Call actual patient API endpoint
    // const res = await api.get('/v1/patients', { params: { q, paginate: 0 } })
    patientSuggestions.value = []
  } catch (e) {
    console.error('Failed to load patients', e)
    patientSuggestions.value = []
  }
}

async function searchDiagnoses(event: { query: string }) {
  try {
    const q = event.query?.trim() ?? ''
    if (!q || q.length < 1) {
      filteredDiagnoses.value = []
      return
    }

    const res = await api.get('/v1/diagnoses', { params: { q, paginate: 0 } })
    const raw = res.data
    const arr =
      Array.isArray(raw) ? raw :
      Array.isArray(raw?.data) ? raw.data :
      Array.isArray(raw?.data?.items) ? raw.data.items :
      Array.isArray(raw?.items) ? raw.items :
      []

    filteredDiagnoses.value = (arr as Diagnosis[]).map(d => ({
      id: d.id,
      code: d.code ?? '',
      description: d.description ?? ''
    }))
  } catch (e) {
    console.error('Failed to load diagnoses', e)
    filteredDiagnoses.value = []
  }
}

function selectPatient(patient: Patient) {
  selectedPatient.value = patient
}

function submitForm() {
  console.log('Form submitted:', {
    patient: selectedPatient.value,
    medicalDiagnosis: medicalDiagnosis.value,
    recommendedPharmacy: recommendedPharmacy.value,
    treatingDoctor: treatingDoctor.value,
    admissionDate: admissionDate.value,
    allergies: allergies.value,
    abuses: abuses.value,
    familyAnamnesis: familyAnamnesis.value,
    socialAnamnesis: socialAnamnesis.value,
    healthPerception: healthPerception.value
  })
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="submitForm" class="flex flex-col gap-4">
      <!-- Medical Information Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Lekárska diagnóza</label>
            <AutoComplete
              v-model="medicalDiagnosis"
              :suggestions="filteredDiagnoses"
              optionLabel="code"
              :minLength="1"
              @complete="searchDiagnoses"
              class="w-full"
              inputClass="w-full! border-none!"
            >
              <template #option="slotProps">
                <div class="flex flex-col">
                  <span class="shrink-0 text-accent">{{ slotProps.option.code }}</span>
                  <span>{{ slotProps.option.description }}</span>
                </div>
              </template>
            </AutoComplete>
          </div>
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Dátum prijatia</label>
            <DatePicker
              v-model="admissionDate"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full border-none!"
            />
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Doporučená farmakoterapia</label>
          <Textarea
            v-model="recommendedPharmacy"
            class="w-full border-none!"
            rows="4"
            inputClass="w-full!"
          />
        </div>
      </section>

      <!-- Allergies -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <label class="block text-normal text-accent">Alergia</label>
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <Checkbox v-model="allergies.medicines" inputId="allergy-medicines" value="medicines" />
            <label for="allergy-medicines" class="text-normal cursor-pointer">Lieky</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="allergies.food" inputId="allergy-food" value="food" />
            <label for="allergy-food" class="text-normal cursor-pointer">Potraviny</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="allergies.disinfectants" inputId="allergy-disinfectants" value="disinfectants" />
            <label for="allergy-disinfectants" class="text-normal cursor-pointer">Dezinfekčné prípravky</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="allergies.plaster" inputId="allergy-plaster" value="plaster" />
            <label for="allergy-plaster" class="text-normal cursor-pointer">Leukoplast</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="allergies.bite" inputId="allergy-bite" value="bite" />
            <label for="allergy-bite" class="text-normal cursor-pointer">Uštipnutie</label>
          </div>
          <div class="flex items-center gap-2">
            <label for="allergy-other" class="text-normal cursor-pointer whitespace-nowrap">Iné:</label>
            <InputText id="allergy-other" v-model="allergies.other" type="text" class="flex-1 border-none!" />
          </div>
        </div>
      </section>

      <!-- Abuses -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <label class="block text-normal text-accent">Abúzy</label>
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <Checkbox v-model="abuses.caffeine" inputId="abuse-caffeine" value="caffeine" />
            <label for="abuse-caffeine" class="text-normal cursor-pointer">Kofeín</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="abuses.nicotine" inputId="abuse-nicotine" value="nicotine" />
            <label for="abuse-nicotine" class="text-normal cursor-pointer">Nikotín</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="abuses.alcohol" inputId="abuse-alcohol" value="alcohol" />
            <label for="abuse-alcohol" class="text-normal cursor-pointer">Alkohol</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="abuses.medicines" inputId="abuse-medicines" value="medicines" />
            <label for="abuse-medicines" class="text-normal cursor-pointer">Lieky</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="abuses.drugs" inputId="abuse-drugs" value="drugs" />
            <label for="abuse-drugs" class="text-normal cursor-pointer">Drogy</label>
          </div>
        </div>
      </section>

      <!-- Family Anamnesis -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <label class="block text-normal text-accent">Rodinná anamnéza</label>
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <Checkbox v-model="familyAnamnesis.IM" inputId="family-IM" value="IM" />
            <label for="family-IM" class="text-normal cursor-pointer">IM</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="familyAnamnesis.DM" inputId="family-DM" value="DM" />
            <label for="family-DM" class="text-normal cursor-pointer">DM</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="familyAnamnesis.ICHS" inputId="family-ICHS" value="ICHS" />
            <label for="family-ICHS" class="text-normal cursor-pointer">ICHS</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="familyAnamnesis.TBC" inputId="family-TBC" value="TBC" />
            <label for="family-TBC" class="text-normal cursor-pointer">TBC</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="familyAnamnesis.CA" inputId="family-CA" value="CA" />
            <label for="family-CA" class="text-normal cursor-pointer">CA</label>
          </div>
          <div class="flex flex-col gap-2">
          <label class="block text-normal">Poznámky</label>
          <Textarea
            v-model="familyAnamnesis.notes"
            class="w-full border-none!"
            rows="2"
            inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
          />
        </div>
        </div>

        
      </section>

      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Sociálna anamnéza</h3>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Povolanie:</label>
          <InputText v-model="socialAnamnesis.profession" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sociálne podmienky</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in socialConditionOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="socialAnamnesis.socialConditions" :inputId="`social-conditions-${idx}`" :value="option.value" />
              <label :for="`social-conditions-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sociálne postavenie</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in socialStatusOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="socialAnamnesis.socialStatus" :inputId="`social-status-${idx}`" :value="option.value" />
              <label :for="`social-status-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sociálny kontakt</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in socialContactOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="socialAnamnesis.socialContact" :inputId="`social-contact-${idx}`" :value="option.value" />
              <label :for="`social-contact-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
            <div class="flex items-center gap-2">
            <Checkbox v-model="socialAnamnesis.friends" inputId="social-friends" value="friends" />
            <label for="social-friends" class="text-normal cursor-pointer">Priatelia</label>
            </div>
            <div class="flex items-center gap-2">
            <Checkbox v-model="socialAnamnesis.neighbors" inputId="social-neighbors" value="neighbors" />
            <label for="social-neighbors" class="text-normal cursor-pointer">Susedia</label>
            </div>
            <div class="flex items-center gap-2">
            <Checkbox v-model="socialAnamnesis.selfHelpGroups" inputId="social-selfhelp" value="selfhelp" />
            <label for="social-selfhelp" class="text-normal cursor-pointer">Svojpomocné skupiny</label>
            </div>
            <div class="flex items-center gap-2">
            <Checkbox v-model="socialAnamnesis.careService" inputId="social-care" value="care" />
            <label for="social-care" class="text-normal cursor-pointer">Opatrovateľská služba</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Spoločensko-kultúrna situácia</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in culturalOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="socialAnamnesis.culturalSituation" :inputId="`cultural-${idx}`" :value="option.value" />
              <label :for="`cultural-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
        <div class="flex items-center gap-2">
            <Checkbox v-model="socialAnamnesis.TV" inputId="cultural-tv" value="tv" />
            <label for="cultural-tv" class="text-normal cursor-pointer">TV</label>
        </div>
        <div class="flex items-center gap-2">
            <Checkbox v-model="socialAnamnesis.radio" inputId="cultural-radio" value="radio" />
            <label for="cultural-radio" class="text-normal cursor-pointer">Rádio</label>
        </div>
        <div class="flex items-center gap-2">
            <Checkbox v-model="socialAnamnesis.newspaper" inputId="cultural-newspaper" value="newspaper" />
            <label for="cultural-newspaper" class="text-normal cursor-pointer">Dennú tlač</label>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal">Iné zistenia: </label>
          <InputText v-model="socialAnamnesis.otherNotes" type="text" class="flex-1 border-none!" />
        </div>
        </div>

      </section>

      <!-- Health Perception -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Vnímanie zdravia</h3>
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Subjektívny popis problémov pacienta/pacientky</label>
          <Textarea
            v-model="healthPerception"
            class="w-full border-none!"
            rows="3"
            inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
          />
        </div>
      </section>

      <!-- Nursing Assessment Entry Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Vstupný záznam sesterského posúdenia zdravotného stavu</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Starostlivosť odporučil</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in caredRecommendedByOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="nursingAssessment.caredRecommendedBy" :inputId="`care-recommended-${idx}`" :value="option.value" />
              <label :for="`care-recommended-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="nursingAssessment.otherDoctorName" inputId="other-doctor" type="checkbox" />
          <label for="other-doctor" class="text-normal cursor-pointer whitespace-nowrap">Iný ošetrujúci lekár, aký:</label>
          <InputText v-model="nursingAssessment.otherDoctorName" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="nursingAssessment.transferredFromFacility" inputId="transferred-facility" type="checkbox" />
          <label for="transferred-facility" class="text-normal cursor-pointer whitespace-nowrap">Prebratý(á) z iného zariadenia, odkiaľ:</label>
          <InputText v-model="nursingAssessment.transferredFromFacility" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Oddelenie</label>
          <InputText v-model="nursingAssessment.department" type="text" class="flex-1 border-none!" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Posledná hospitalizácia: od</label>
            <DatePicker
              v-model="nursingAssessment.lastHospitalizationFrom"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full border-none!"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="block text-normal">do</label>
            <DatePicker
              v-model="nursingAssessment.lastHospitalizationTo"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="w-full"
              inputClass="!w-full border-none!"
            />
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Vedomie</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in consciousnessOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="nursingAssessment.consciousness" :inputId="`consciousness-${idx}`" :value="option.value" />
              <label :for="`consciousness-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="nursingAssessment.consciousnessOtherNotes" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Orientácia</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in orientationOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton v-model="nursingAssessment.orientation" :inputId="`orientation-${idx}`" :value="option.value" />
              <label :for="`orientation-${idx}`" class="text-normal cursor-pointer">{{ option.label }}</label>
            </div>
          </div>
          <p class="text-sm text-gray-600 ml-6">v čase / v priestore</p>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="nursingAssessment.orientationOtherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Circulation Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Cirkulácia</h3>

        <div class="grid grid-cols-3 gap-4">
          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">TK (mmHg)</label>
            <InputText v-model="circulation.bloodPressure" type="text" class="flex-1 border-none!" />
          </div>
          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Teplota (°C)</label>
            <InputText v-model="circulation.temperature" type="text" class="flex-1 border-none!" />
          </div>
          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Pulz (/min)</label>
            <InputText v-model="circulation.pulse" type="text" class="flex-1 border-none!" />
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="circulation.problemExists" inputId="circulation-problem-no" value="no" />
              <label for="circulation-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="circulation.problemExists" inputId="circulation-problem-yes" value="yes" />
              <label for="circulation-problem-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Hypotenzia / Hypertenzia</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="circulation.hypotensionHypertension" inputId="hypotension" value="hypotension" />
              <label for="hypotension" class="text-normal cursor-pointer">Hypotenzia</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="circulation.hypotensionHypertension" inputId="hypertension" value="hypertension" />
              <label for="hypertension" class="text-normal cursor-pointer">Hypertenzia</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <Checkbox v-model="circulation.irregularPulse" inputId="irregular-pulse" value="irregular" />
            <label for="irregular-pulse" class="text-normal cursor-pointer">Pulz – nepravidelný / slabo hmatný / nitkovitý</label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox v-model="circulation.pacemaker" inputId="pacemaker" value="pacemaker" />
            <label for="pacemaker" class="text-normal cursor-pointer">Kardiostimulátor</label>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="circulation.otherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Breathing Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Dýchanie</h3>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">D (/min)</label>
          <InputText v-model="breathing.respiratoryRate" type="text" class="w-24 border-none!" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="breathing.problemExists" inputId="breathing-problem-no" value="no" />
              <label for="breathing-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="breathing.problemExists" inputId="breathing-problem-yes" value="yes" />
              <label for="breathing-problem-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Typ dýchania</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.irregular" inputId="breathing-irregular" value="irregular" />
              <label for="breathing-irregular" class="text-normal cursor-pointer">Nepravidelné</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.fast" inputId="breathing-fast" value="fast" />
              <label for="breathing-fast" class="text-normal cursor-pointer">Rýchle</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.slow" inputId="breathing-slow" value="slow" />
              <label for="breathing-slow" class="text-normal cursor-pointer">Pomalé</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.difficult" inputId="breathing-difficult" value="difficult" />
              <label for="breathing-difficult" class="text-normal cursor-pointer">Sťažené</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.shallow" inputId="breathing-shallow" value="shallow" />
              <label for="breathing-shallow" class="text-normal cursor-pointer">Plytké</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.deepened" inputId="breathing-deepened" value="deepened" />
              <label for="breathing-deepened" class="text-normal cursor-pointer">Prehĺbené</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.apneicPauses" inputId="breathing-apneic" value="apneic" />
              <label for="breathing-apneic" class="text-normal cursor-pointer">Apnoické pauzy</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.stridor" inputId="breathing-stridor" value="stridor" />
              <label for="breathing-stridor" class="text-normal cursor-pointer">Stridor</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Ďalšie príznaky</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.dyspneaAtRest" inputId="breathing-dyspnea" value="dyspnea" />
              <label for="breathing-dyspnea" class="text-normal cursor-pointer">Dýchavica v kľude</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.cough" inputId="breathing-cough" value="cough" />
              <label for="breathing-cough" class="text-normal cursor-pointer">Kašeľ</label>
              <InputText v-if="breathing.cough" v-model="breathing.coughType" type="text" class="flex-1 border-none! ml-2" />
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="breathing.tracheostomy" inputId="breathing-tracheostomy" value="tracheostomy" />
              <label for="breathing-tracheostomy" class="text-normal cursor-pointer">Tracheostómia</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="breathing.otherNotes" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Podpora dýchania</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <label class="text-normal whitespace-nowrap">Odsávanie z dýchacích ciest:</label>
              <RadioButton v-model="breathing.suctioning" inputId="suctioning-yes" value="yes" />
              <label for="suctioning-yes" class="text-normal cursor-pointer">Áno</label>
              <RadioButton v-model="breathing.suctioning" inputId="suctioning-no" value="no" />
              <label for="suctioning-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-normal whitespace-nowrap">Oxygenoterapia:</label>
              <RadioButton v-model="breathing.oxygenTherapy" inputId="oxygen-yes" value="yes" />
              <label for="oxygen-yes" class="text-normal cursor-pointer">Áno</label>
              <RadioButton v-model="breathing.oxygenTherapy" inputId="oxygen-no" value="no" />
              <label for="oxygen-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-normal whitespace-nowrap">Mechanická ventilácia:</label>
              <RadioButton v-model="breathing.mechanicalVentilation" inputId="upv-yes" value="yes" />
              <label for="upv-yes" class="text-normal cursor-pointer">Áno</label>
              <RadioButton v-model="breathing.mechanicalVentilation" inputId="upv-no" value="no" />
              <label for="upv-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-normal whitespace-nowrap">Inhalácia:</label>
              <RadioButton v-model="breathing.inhalation" inputId="inhalation-yes" value="yes" />
              <label for="inhalation-yes" class="text-normal cursor-pointer">Áno</label>
              <RadioButton v-model="breathing.inhalation" inputId="inhalation-no" value="no" />
              <label for="inhalation-no" class="text-normal cursor-pointer">Nie</label>
            </div>
          </div>
        </div>
      </section>

      <!-- Nutrition Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Výživa</h3>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Diéta č.</label>
          <InputText v-model="nutrition.diet" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.problemExists" inputId="nutrition-problem-no" value="no" />
              <label for="nutrition-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.problemExists" inputId="nutrition-problem-yes" value="yes" />
              <label for="nutrition-problem-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Nutričný stav</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.obesity" inputId="nutrition-obesity" value="obesity" />
              <label for="nutrition-obesity" class="text-normal cursor-pointer">Obezita</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.cachexia" inputId="nutrition-cachexia" value="cachexia" />
              <label for="nutrition-cachexia" class="text-normal cursor-pointer">Kachexia</label>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-normal whitespace-nowrap">Zmena hmotnosti:</label>
              <RadioButton v-model="nutrition.weightChange" inputId="weight-loss" value="loss" />
              <label for="weight-loss" class="text-normal cursor-pointer">Úbytok</label>
              <RadioButton v-model="nutrition.weightChange" inputId="weight-gain" value="gain" />
              <label for="weight-gain" class="text-normal cursor-pointer">Prírastok</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.appetiteLoss" inputId="nutrition-anorexia" value="anorexia" />
              <label for="nutrition-anorexia" class="text-normal cursor-pointer">Nechutenstvo</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.nausea" inputId="nutrition-nausea" value="nausea" />
              <label for="nutrition-nausea" class="text-normal cursor-pointer">Nauzea</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.vomiting" inputId="nutrition-vomiting" value="vomiting" />
              <label for="nutrition-vomiting" class="text-normal cursor-pointer">Zvracanie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.diarrhea" inputId="nutrition-diarrhea" value="diarrhea" />
              <label for="nutrition-diarrhea" class="text-normal cursor-pointer">Hnačka</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.swallowingDisorder" inputId="nutrition-swallowing" value="swallowing" />
              <label for="nutrition-swallowing" class="text-normal cursor-pointer">Porucha prehĺtania</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.heartburn" inputId="nutrition-heartburn" value="heartburn" />
              <label for="nutrition-heartburn" class="text-normal cursor-pointer">Pálenie záhy</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Príjem stravy</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.foodIntake" inputId="intake-alone" value="alone" />
              <label for="intake-alone" class="text-normal cursor-pointer">Sám</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.foodIntake" inputId="intake-help" value="help" />
              <label for="intake-help" class="text-normal cursor-pointer">S pomocou</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.foodIntake" inputId="intake-tube" value="tube" />
              <label for="intake-tube" class="text-normal cursor-pointer">Nazog. sonda / dátum zavedenia:</label>
              <DatePicker v-if="nutrition.foodIntake === 'tube'" v-model="nutrition.nasogastricTubeDate" dateFormat="dd.mm.yy" :showIcon="false" class="w-32" inputClass="!w-full border-none!" />
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="nutrition.gastrostomy" inputId="nutrition-gastrostomy" value="gastrostomy" />
          <label for="nutrition-gastrostomy" class="text-normal cursor-pointer whitespace-nowrap">Gastrostómia / dátum:</label>
          <DatePicker v-if="nutrition.gastrostomy" v-model="nutrition.gastrostomyDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="nutrition.peg" inputId="nutrition-peg" value="peg" />
          <label for="nutrition-peg" class="text-normal cursor-pointer whitespace-nowrap">PEG / dátum:</label>
          <DatePicker v-if="nutrition.peg" v-model="nutrition.pegDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Príjem tekutín / 24h:</label>
          <InputText v-model="nutrition.fluidIntake" type="text" class="w-24 border-none!" />
          <label class="text-normal">ml</label>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="nutrition.cvk" inputId="nutrition-cvk" value="cvk" />
          <label for="nutrition-cvk" class="text-normal cursor-pointer whitespace-nowrap">CVK / dátum:</label>
          <DatePicker v-if="nutrition.cvk" v-model="nutrition.cvkDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="nutrition.peripheralAccess" inputId="nutrition-peripheral" value="peripheral" />
          <label for="nutrition-peripheral" class="text-normal cursor-pointer whitespace-nowrap">Periférny i.v. prístup / dátum:</label>
          <DatePicker v-if="nutrition.peripheralAccess" v-model="nutrition.peripheralAccessDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="nutrition.otherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Elimination - Defecation Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Vylučovanie – defekácia</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="elimination.defecationProblem" inputId="defecation-problem-no" value="no" />
              <label for="defecation-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="elimination.defecationProblem" inputId="defecation-problem-yes" value="yes" />
              <label for="defecation-problem-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Pravidelnosť</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.defecationRegularity" inputId="defecation-regular" value="regular" />
              <label for="defecation-regular" class="text-normal cursor-pointer">Pravidelná</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.bowelMovement" inputId="defecation-diarrhea" value="diarrhea" />
              <label for="defecation-diarrhea" class="text-normal cursor-pointer">Hnačka</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.constipation" inputId="defecation-constipation" value="constipation" />
              <label for="defecation-constipation" class="text-normal cursor-pointer">Zápcha</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.stoolConsistency" inputId="defecation-admixtures" value="admixtures" />
              <label for="defecation-admixtures" class="text-normal cursor-pointer">S prímesami</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.hemorrhoids" inputId="defecation-hemorrhoids" value="hemorrhoids" />
              <label for="defecation-hemorrhoids" class="text-normal cursor-pointer">Hemoroidy</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.stoma" inputId="defecation-stoma" value="stoma" />
              <label for="defecation-stoma" class="text-normal cursor-pointer whitespace-nowrap">Stómia – ošetrená naposledy / dátum:</label>
              <DatePicker v-if="elimination.stoma" v-model="elimination.stomaDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="elimination.defecationOtherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Elimination - Micturition Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Vylučovanie – močenie</h3>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Diuréza / 24h:</label>
          <InputText v-model="elimination.micturitionDiuresis" type="text" class="w-24 border-none!" />
          <label class="text-normal">ml</label>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="elimination.micturitionProblem" inputId="micturition-problem-no" value="no" />
              <label for="micturition-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="elimination.micturitionProblem" inputId="micturition-problem-yes" value="yes" />
              <label for="micturition-problem-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Príznaky</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.dysuria" inputId="micturition-dysuria" value="dysuria" />
              <label for="micturition-dysuria" class="text-normal cursor-pointer">Dyzúria</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.retention" inputId="micturition-retention" value="retention" />
              <label for="micturition-retention" class="text-normal cursor-pointer">Retencia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.urinaryIncontinence" inputId="micturition-incontinence" value="incontinence" />
              <label for="micturition-incontinence" class="text-normal cursor-pointer">Inkontinencia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.absorptivePads" inputId="micturition-pads" value="pads" />
              <label for="micturition-pads" class="text-normal cursor-pointer">Absorpčné pomôcky</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="elimination.catheter" inputId="micturition-catheter" value="catheter" />
          <label for="micturition-catheter" class="text-normal cursor-pointer whitespace-nowrap">RK / dátum:</label>
          <DatePicker v-if="elimination.catheter" v-model="elimination.catheterDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="elimination.colorUrine" inputId="micturition-color" value="color" />
          <label for="micturition-color" class="text-normal cursor-pointer whitespace-nowrap">Farba moču:</label>
          <InputText v-if="elimination.colorUrine" v-model="elimination.colorUrineText" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="elimination.urostomy" inputId="micturition-urostomy" value="urostomy" />
          <label for="micturition-urostomy" class="text-normal cursor-pointer whitespace-nowrap">Urostómia / dátum:</label>
          <DatePicker v-if="elimination.urostomy" v-model="elimination.urostomyDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="elimination.micturitionOtherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Sleep Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Spánok</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="sleep.problemExists" inputId="sleep-problem-no" value="no" />
              <label for="sleep-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="sleep.problemExists" inputId="sleep-problem-yes" value="yes" />
              <label for="sleep-problem-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Poruchy spánku</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="sleep.insomnia" inputId="sleep-insomnia" value="insomnia" />
              <label for="sleep-insomnia" class="text-normal cursor-pointer">Nespavosť</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="sleep.nightAwakenings" inputId="sleep-awakenings" value="awakenings" />
              <label for="sleep-awakenings" class="text-normal cursor-pointer">Nočné budenie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="sleep.pharmacotherapy" inputId="sleep-pharmacotherapy" value="pharmacotherapy" />
              <label for="sleep-pharmacotherapy" class="text-normal cursor-pointer">Farmakoterapia</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="sleep.otherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Mobility Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Mobilita</h3>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Úroveň mobility:</label>
          <div class="flex gap-2">
            <div class="flex items-center gap-1">
              <RadioButton v-model="mobility.mobilityLevel" inputId="mobility-1" value="full" />
              <label for="mobility-1" class="text-normal cursor-pointer">1 Plná</label>
            </div>
            <div class="flex items-center gap-1">
              <RadioButton v-model="mobility.mobilityLevel" inputId="mobility-2" value="mild" />
              <label for="mobility-2" class="text-normal cursor-pointer">2 Mierne obm.</label>
            </div>
            <div class="flex items-center gap-1">
              <RadioButton v-model="mobility.mobilityLevel" inputId="mobility-3" value="severe" />
              <label for="mobility-3" class="text-normal cursor-pointer">3 Veľmi obm.</label>
            </div>
            <div class="flex items-center gap-1">
              <RadioButton v-model="mobility.mobilityLevel" inputId="mobility-4" value="immobility" />
              <label for="mobility-4" class="text-normal cursor-pointer">4 Imobilita</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Kompenzačné pomôcky:</label>
          <InputText v-model="mobility.compensatoryDevices" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Pohybový systém – problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="mobility.musculoskeletalProblem" inputId="musculoskeletal-no" value="no" />
              <label for="musculoskeletal-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="mobility.musculoskeletalProblem" inputId="musculoskeletal-yes" value="yes" />
              <label for="musculoskeletal-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Zmeny na pohybovom systéme</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="mobility.deformation" inputId="mobility-deformation" value="deformation" />
              <label for="mobility-deformation" class="text-normal cursor-pointer">Deformácia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="mobility.tremblingExtremities" inputId="mobility-trembling" value="trembling" />
              <label for="mobility-trembling" class="text-normal cursor-pointer">Tŕpnutie končatín</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="mobility.fracture" inputId="mobility-fracture" value="fracture" />
              <label for="mobility-fracture" class="text-normal cursor-pointer">Zlomenina</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="mobility.paralysis" inputId="mobility-paralysis" value="paralysis" />
              <label for="mobility-paralysis" class="text-normal cursor-pointer">Ochrnutie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="mobility.amputation" inputId="mobility-amputation" value="amputation" />
              <label for="mobility-amputation" class="text-normal cursor-pointer">Amputácia</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="mobility.musculoskeletalOtherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Skin Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Koža</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.skinProblem" inputId="skin-problem-no" value="no" />
              <label for="skin-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.skinProblem" inputId="skin-problem-yes" value="yes" />
              <label for="skin-problem-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Teplota kože</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.temperature" inputId="skin-temp-warm" value="warm" />
                <label for="skin-temp-warm" class="text-normal cursor-pointer">Teplá</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.temperature" inputId="skin-temp-cold" value="cold" />
                <label for="skin-temp-cold" class="text-normal cursor-pointer">Studená</label>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label class="block text-normal">Vlhkosť kože</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.moisture" inputId="skin-moist-dry" value="dry" />
                <label for="skin-moist-dry" class="text-normal cursor-pointer">Suchá</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.moisture" inputId="skin-moist-wet" value="wet" />
                <label for="skin-moist-wet" class="text-normal cursor-pointer">Spotená</label>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Farba kože</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.color" inputId="skin-color-pink" value="pink" />
                <label for="skin-color-pink" class="text-normal cursor-pointer">Ružová</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.color" inputId="skin-color-pale" value="pale" />
                <label for="skin-color-pale" class="text-normal cursor-pointer">Bledá</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.color" inputId="skin-color-red" value="red" />
                <label for="skin-color-red" class="text-normal cursor-pointer">Začervenená</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.color" inputId="skin-color-jaundice" value="jaundice" />
                <label for="skin-color-jaundice" class="text-normal cursor-pointer">Ikterická</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.color" inputId="skin-color-cyanotic" value="cyanotic" />
                <label for="skin-color-cyanotic" class="text-normal cursor-pointer">Cyanotická</label>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label class="block text-normal">Turgor kože</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.turgor" inputId="skin-turgor-normal" value="normal" />
                <label for="skin-turgor-normal" class="text-normal cursor-pointer">Primeraný</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.turgor" inputId="skin-turgor-low" value="low" />
                <label for="skin-turgor-low" class="text-normal cursor-pointer">Znížený</label>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Čistosť kože</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.integrity" inputId="skin-integrity-intact" value="intact" />
              <label for="skin-integrity-intact" class="text-normal cursor-pointer">Nenarušená</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.integrity" inputId="skin-integrity-damaged" value="damaged" />
              <label for="skin-integrity-damaged" class="text-normal cursor-pointer">Narušená</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Zmeny na koži</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.rashes" inputId="skin-rashes" value="rashes" />
              <label for="skin-rashes" class="text-normal cursor-pointer">Kožné vyrážky</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.itching" inputId="skin-itching" value="itching" />
              <label for="skin-itching" class="text-normal cursor-pointer">Svrbenie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.peeling" inputId="skin-peeling" value="peeling" />
              <label for="skin-peeling" class="text-normal cursor-pointer">Olupovanie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.maceration" inputId="skin-maceration" value="maceration" />
              <label for="skin-maceration" class="text-normal cursor-pointer">Zapareniny</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.bruising" inputId="skin-bruising" value="bruising" />
              <label for="skin-bruising" class="text-normal cursor-pointer">Modriny</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.inflammation" inputId="skin-inflammation" value="inflammation" />
              <label for="skin-inflammation" class="text-normal cursor-pointer">Zápal</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Typ poranenia</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.woundType" inputId="wound-superficial" value="superficial" />
              <label for="wound-superficial" class="text-normal cursor-pointer">Povrchové poranenie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.woundType" inputId="wound-open" value="open" />
              <label for="wound-open" class="text-normal cursor-pointer">Otvorená rana</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.woundType" inputId="wound-surgical" value="surgical" />
              <label for="wound-surgical" class="text-normal cursor-pointer">Operačná rana</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Komplikácie</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.bleeding" inputId="skin-bleeding" value="bleeding" />
              <label for="skin-bleeding" class="text-normal cursor-pointer">Krvácanie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.ulcusCruris" inputId="skin-ulcus" value="ulcus" />
              <label for="skin-ulcus" class="text-normal cursor-pointer">Ulcus cruris</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.gangrene" inputId="skin-gangrene" value="gangrene" />
              <label for="skin-gangrene" class="text-normal cursor-pointer">Gangréna</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.decubitus" inputId="skin-decubitus" value="decubitus" />
              <label for="skin-decubitus" class="text-normal cursor-pointer">Dekubity</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Lokalizácia:</label>
          <InputText v-model="skin.localization" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Veľkosť defektu:</label>
          <InputText v-model="skin.defectSize" type="text" class="w-24 border-none!" />
          <label class="text-normal">cm</label>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Pacient/pacientka je:</label>
          <InputText v-model="skin.postOperativeDays" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Edémy</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <label class="text-normal whitespace-nowrap">Problém:</label>
              <RadioButton v-model="skin.edemaExists" inputId="edema-no" value="no" />
              <label for="edema-no" class="text-normal cursor-pointer">Nie</label>
              <RadioButton v-model="skin.edemaExists" inputId="edema-yes" value="yes" />
              <label for="edema-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.edemaType" inputId="edema-local" value="local" />
              <label for="edema-local" class="text-normal cursor-pointer">Miestne</label>
              <RadioButton v-model="skin.edemaType" inputId="edema-general" value="general" />
              <label for="edema-general" class="text-normal cursor-pointer">Celkové</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.bandageLowerExtremity" inputId="skin-bandage" value="bandage" />
              <label for="skin-bandage" class="text-normal cursor-pointer">Bandáž DK</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.antiemboliStockings" inputId="skin-stockings" value="stockings" />
              <label for="skin-stockings" class="text-normal cursor-pointer">Antitrombotické pančuchy</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.vascularExercise" inputId="skin-vascular" value="vascular" />
              <label for="skin-vascular" class="text-normal cursor-pointer">Cievna gymnastika</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sliznice</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <label class="text-normal whitespace-nowrap">Problém:</label>
              <RadioButton v-model="skin.mucousMembranes" inputId="mucous-no" value="no" />
              <label for="mucous-no" class="text-normal cursor-pointer">Nie</label>
              <RadioButton v-model="skin.mucousMembranes" inputId="mucous-yes" value="yes" />
              <label for="mucous-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="skin.mucousOtherNotes" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Hygienický stav pri prijatí</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.hygieneStatus" inputId="hygiene-adequate" value="adequate" />
              <label for="hygiene-adequate" class="text-normal cursor-pointer">Primeraný</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.hygieneStatus" inputId="hygiene-neglected" value="neglected" />
              <label for="hygiene-neglected" class="text-normal cursor-pointer">Zanedbaný</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Hygienickú starostlivosť vykonáva</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.hygienePerformedBy" inputId="hygiene-self" value="self" />
              <label for="hygiene-self" class="text-normal cursor-pointer">Samostatne</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.hygienePerformedBy" inputId="hygiene-help" value="help" />
              <label for="hygiene-help" class="text-normal cursor-pointer">S pomocou</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.hygienePerformedBy" inputId="hygiene-dependent" value="dependent" />
              <label for="hygiene-dependent" class="text-normal cursor-pointer">Je úplne závislý(á)</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
        </div>
      </section>

      <!-- Pain Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Bolesť</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="pain.painExists" inputId="pain-no" value="no" />
              <label for="pain-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="pain.painExists" inputId="pain-yes" value="yes" />
              <label for="pain-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div v-if="pain.painExists === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Intenzita bolesti (1-10):</label>
          <InputText v-model="pain.painIntensity" type="number" min="1" max="10" class="w-20 border-none!" />
        </div>

        <div v-if="pain.painExists === 'yes'" class="flex flex-col gap-2">
          <label class="block text-normal">Charakter bolesti</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="pain.painCharacter" inputId="pain-sharp" value="sharp" />
              <label for="pain-sharp" class="text-normal cursor-pointer">Ostrá</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="pain.painCharacter" inputId="pain-dull" value="dull" />
              <label for="pain-dull" class="text-normal cursor-pointer">Tupá</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="pain.painCharacter" inputId="pain-burning" value="burning" />
              <label for="pain-burning" class="text-normal cursor-pointer">Pálčivá</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="pain.painCharacter" inputId="pain-throbbing" value="throbbing" />
              <label for="pain-throbbing" class="text-normal cursor-pointer">Pulzujúca</label>
            </div>
          </div>
        </div>

        <div v-if="pain.painExists === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Lokalizácia:</label>
          <InputText v-model="pain.painLocalization" type="text" class="flex-1 border-none!" />
        </div>

        <div v-if="pain.painExists === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Čas výskytu:</label>
          <InputText v-model="pain.painTiming" type="text" class="flex-1 border-none!" />
        </div>

        <div v-if="pain.painExists === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Liečba bolesti:</label>
          <InputText v-model="pain.painManagement" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="pain.otherPainNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Communication Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Komunikácia</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém v komunikácii</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.communicationProblem" inputId="comm-no" value="no" />
              <label for="comm-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.communicationProblem" inputId="comm-yes" value="yes" />
              <label for="comm-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Verbálna komunikácia</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.verbalCommunication" inputId="verbal-clear" value="clear" />
              <label for="verbal-clear" class="text-normal cursor-pointer">Zrozumiteľná</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.verbalCommunication" inputId="verbal-unclear" value="unclear" />
              <label for="verbal-unclear" class="text-normal cursor-pointer">Nejasná</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.verbalCommunication" inputId="verbal-none" value="none" />
              <label for="verbal-none" class="text-normal cursor-pointer">Chýba</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Neverbálna komunikácia</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="communication.nonVerbalCommunication" inputId="nonverbal-gesture" value="gesture" />
              <label for="nonverbal-gesture" class="text-normal cursor-pointer">Gesto</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="communication.nonVerbalCommunication" inputId="nonverbal-facial" value="facial" />
              <label for="nonverbal-facial" class="text-normal cursor-pointer">Gestikulácia/Mimika</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="communication.nonVerbalCommunication" inputId="nonverbal-writing" value="writing" />
              <label for="nonverbal-writing" class="text-normal cursor-pointer">Písomne</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Porozumenie komunikácii</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="communication.comprehension" inputId="comp-yes" value="yes" />
                <label for="comp-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="communication.comprehension" inputId="comp-no" value="no" />
                <label for="comp-no" class="text-normal cursor-pointer">Nie</label>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label class="block text-normal">Schopnosť sa vyjadriť</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="communication.expression" inputId="expr-yes" value="yes" />
                <label for="expr-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="communication.expression" inputId="expr-no" value="no" />
                <label for="expr-no" class="text-normal cursor-pointer">Nie</label>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Sluch</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="communication.hearing" inputId="hearing-normal" value="normal" />
                <label for="hearing-normal" class="text-normal cursor-pointer">Normálny</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="communication.hearing" inputId="hearing-impaired" value="impaired" />
                <label for="hearing-impaired" class="text-normal cursor-pointer">Porušený</label>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label class="block text-normal">Vid</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="communication.vision" inputId="vision-normal" value="normal" />
                <label for="vision-normal" class="text-normal cursor-pointer">Normálny</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="communication.vision" inputId="vision-impaired" value="impaired" />
                <label for="vision-impaired" class="text-normal cursor-pointer">Porušený</label>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Reč:</label>
          <InputText v-model="communication.speech" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="communication.otherCommunicationNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Psychological Needs Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Psychické potreby</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Psychické potreby - existujú</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="psychologicalNeeds.needsExist" inputId="psych-no" value="no" />
              <label for="psych-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="psychologicalNeeds.needsExist" inputId="psych-yes" value="yes" />
              <label for="psych-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div v-if="psychologicalNeeds.needsExist === 'yes'" class="flex flex-col gap-2">
          <label class="block text-normal">Psychický stav pacienta</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="psychologicalNeeds.anxiety" inputId="psych-anxiety" value="anxiety" />
              <label for="psych-anxiety" class="text-normal cursor-pointer">Úzkosť</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="psychologicalNeeds.depression" inputId="psych-depression" value="depression" />
              <label for="psych-depression" class="text-normal cursor-pointer">Depresia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="psychologicalNeeds.anger" inputId="psych-anger" value="anger" />
              <label for="psych-anger" class="text-normal cursor-pointer">Hnev</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="psychologicalNeeds.fear" inputId="psych-fear" value="fear" />
              <label for="psych-fear" class="text-normal cursor-pointer">Strach</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="psychologicalNeeds.confusion" inputId="psych-confusion" value="confusion" />
              <label for="psych-confusion" class="text-normal cursor-pointer">Zmätenie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="psychologicalNeeds.lowSelfEsteem" inputId="psych-esteem" value="esteem" />
              <label for="psych-esteem" class="text-normal cursor-pointer">Nízka sebavedomosť</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="psychologicalNeeds.suicidalIdeation" inputId="psych-suicidal" value="suicidal" />
              <label for="psych-suicidal" class="text-normal cursor-pointer">Suicídne myšlienky</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="psychologicalNeeds.otherPsychologicalNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Social Needs Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Sociálne potreby</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sociálne potreby - existujú</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="socialNeeds.needsExist" inputId="social-no" value="no" />
              <label for="social-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="socialNeeds.needsExist" inputId="social-yes" value="yes" />
              <label for="social-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div v-if="socialNeeds.needsExist === 'yes'" class="flex flex-col gap-2">
          <label class="block text-normal">Sociálne problémy</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="socialNeeds.familySeparation" inputId="social-separation" value="separation" />
              <label for="social-separation" class="text-normal cursor-pointer">Oddelenie od rodiny</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="socialNeeds.roleChange" inputId="social-role" value="role" />
              <label for="social-role" class="text-normal cursor-pointer">Zmena spoločenskej úlohy</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="socialNeeds.socialIsolation" inputId="social-isolation" value="isolation" />
              <label for="social-isolation" class="text-normal cursor-pointer">Sociálna izolácia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="socialNeeds.economicConcern" inputId="social-economic" value="economic" />
              <label for="social-economic" class="text-normal cursor-pointer">Ekonomické obavy</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="socialNeeds.workConcern" inputId="social-work" value="work" />
              <label for="social-work" class="text-normal cursor-pointer">Pracovné záležitosti</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="socialNeeds.otherSocialNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Spiritual Needs Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Duchovné potreby</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Duchovné potreby - existujú</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="spiritualNeeds.needsExist" inputId="spirit-no" value="no" />
              <label for="spirit-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="spiritualNeeds.needsExist" inputId="spirit-yes" value="yes" />
              <label for="spirit-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div v-if="spiritualNeeds.needsExist === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Viera:</label>
          <InputText v-model="spiritualNeeds.faith" type="text" class="flex-1 border-none!" />
        </div>

        <div v-if="spiritualNeeds.needsExist === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Náboženská prax:</label>
          <InputText v-model="spiritualNeeds.religiousPractice" type="text" class="flex-1 border-none!" />
        </div>

        <div v-if="spiritualNeeds.needsExist === 'yes'" class="flex flex-col gap-2">
          <label class="block text-normal">Náboženský život počas hospitalizácie</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="spiritualNeeds.religiousService" inputId="spirit-service" value="service" />
              <label for="spirit-service" class="text-normal cursor-pointer">Návšteva zboru/kostola</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="spiritualNeeds.spiritualConflict" inputId="spirit-conflict" value="conflict" />
              <label for="spirit-conflict" class="text-normal cursor-pointer">Duchovný konflikt</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="spiritualNeeds.needsChaplain" inputId="spirit-chaplain" value="chaplain" />
              <label for="spirit-chaplain" class="text-normal cursor-pointer">Potreba navštívenia kňazom/duchovným</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="spiritualNeeds.otherSpiritualNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Learning Needs Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Edukácia pacienta</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Edukačné potreby - existujú</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="learningNeeds.needsExist" inputId="learn-no" value="no" />
              <label for="learn-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="learningNeeds.needsExist" inputId="learn-yes" value="yes" />
              <label for="learn-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div v-if="learningNeeds.needsExist === 'yes'" class="flex flex-col gap-2">
          <label class="block text-normal">Schopnosť učenia sa</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="learningNeeds.learningAbility" inputId="learn-ability-good" value="good" />
              <label for="learn-ability-good" class="text-normal cursor-pointer">Dobrá</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="learningNeeds.learningAbility" inputId="learn-ability-limited" value="limited" />
              <label for="learn-ability-limited" class="text-normal cursor-pointer">Limitovaná</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="learningNeeds.learningAbility" inputId="learn-ability-poor" value="poor" />
              <label for="learn-ability-poor" class="text-normal cursor-pointer">Slabá</label>
            </div>
          </div>
        </div>

        <div v-if="learningNeeds.needsExist === 'yes'" class="flex flex-col gap-2">
          <label class="block text-normal">Preferovaná metóda učenia sa</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="learningNeeds.preferredMethod" inputId="learn-method-verbal" value="verbal" />
              <label for="learn-method-verbal" class="text-normal cursor-pointer">Verbálne vysvetľovanie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="learningNeeds.preferredMethod" inputId="learn-method-visual" value="visual" />
              <label for="learn-method-visual" class="text-normal cursor-pointer">Vizuálne materiály</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="learningNeeds.preferredMethod" inputId="learn-method-practical" value="practical" />
              <label for="learn-method-practical" class="text-normal cursor-pointer">Praktické trénovanie</label>
            </div>
          </div>
        </div>

        <div v-if="learningNeeds.needsExist === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Témy záujmu:</label>
          <InputText v-model="learningNeeds.topicsOfInterest" type="text" class="flex-1 border-none!" />
        </div>

        <div v-if="learningNeeds.needsExist === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Kultúrne aspekty:</label>
          <InputText v-model="learningNeeds.culturalConsiderations" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="learningNeeds.otherLearningNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      <!-- Nursing Diagnoses Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Ošetrovateľské diagnózy</h3>

        <div class="flex flex-col gap-4">
          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap flex-shrink-0">1. Diagnóza:</label>
            <InputText v-model="nursingDiagnoses.diagnosis1" type="text" class="flex-1 border-none!" />
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap flex-shrink-0">2. Diagnóza:</label>
            <InputText v-model="nursingDiagnoses.diagnosis2" type="text" class="flex-1 border-none!" />
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap flex-shrink-0">3. Diagnóza:</label>
            <InputText v-model="nursingDiagnoses.diagnosis3" type="text" class="flex-1 border-none!" />
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap flex-shrink-0">4. Diagnóza:</label>
            <InputText v-model="nursingDiagnoses.diagnosis4" type="text" class="flex-1 border-none!" />
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap flex-shrink-0">5. Diagnóza:</label>
            <InputText v-model="nursingDiagnoses.diagnosis5" type="text" class="flex-1 border-none!" />
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-normal">Ďalšie diagnózy</label>
          <Textarea v-model="nursingDiagnoses.additionalDiagnoses" rows="3" class="border-none! w-full" />
        </div>
      </section>

      <!-- Nursing Plan Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Ošetrovateľský plán</h3>

        <div class="flex flex-col gap-2">
          <label class="text-normal">Prioritné ciele ošetrovania</label>
          <Textarea v-model="nursingPlan.priorityGoals" rows="4" class="border-none! w-full" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-normal">Ošetrovateľské intervencie a opatrenia</label>
          <Textarea v-model="nursingPlan.interventions" rows="5" class="border-none! w-full" />
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-normal">Dodatočné poznámky</label>
          <Textarea v-model="nursingPlan.additionalNotes" rows="3" class="border-none! w-full" />
        </div>
      </section>

      <!-- Signature Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Podpis a overenie záznamu</h3>

        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="text-normal text-accent">Zaznamenaný záznam</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <label class="text-normal whitespace-nowrap">Dátum a čas:</label>
                <DatePicker v-model="signatureSection.recordedDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
              </div>
              <div class="flex items-center gap-2">
                <label class="text-normal whitespace-nowrap">Sestra:</label>
                <InputText v-model="signatureSection.recordedBy" type="text" class="flex-1 border-none!" />
              </div>
              <div class="flex items-center gap-2">
                <label class="text-normal whitespace-nowrap">Funkcia:</label>
                <InputText v-model="signatureSection.recordedByTitle" type="text" class="flex-1 border-none!" />
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label class="text-normal text-accent">Overený záznam</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <label class="text-normal whitespace-nowrap">Dátum a čas:</label>
                <DatePicker v-model="signatureSection.supervisedDate" dateFormat="dd.mm.yy" :showIcon="false" class="flex-1" inputClass="!w-full border-none!" />
              </div>
              <div class="flex items-center gap-2">
                <label class="text-normal whitespace-nowrap">Sestra:</label>
                <InputText v-model="signatureSection.supervisedBy" type="text" class="flex-1 border-none!" />
              </div>
              <div class="flex items-center gap-2">
                <label class="text-normal whitespace-nowrap">Funkcia:</label>
                <InputText v-model="signatureSection.supervisedByTitle" type="text" class="flex-1 border-none!" />
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-normal">Poznámky</label>
          <Textarea v-model="signatureSection.notes" rows="3" class="border-none! w-full" />
        </div>
      </section>

      <!-- Additional Notes Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Dodatočné informácie a súhlas</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Typ záznamu</label>
          <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
              <Checkbox inputId="record-initial" value="initial" />
              <label for="record-initial" class="text-normal cursor-pointer">Počiatočný záznam</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox inputId="record-update" value="update" />
              <label for="record-update" class="text-normal cursor-pointer">Aktualizácia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox inputId="record-discharge" value="discharge" />
              <label for="record-discharge" class="text-normal cursor-pointer">Prepustenie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox inputId="record-emergency" value="emergency" />
              <label for="record-emergency" class="text-normal cursor-pointer">Havarijný</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Všeobecné poznámky</label>
          <Textarea rows="4" class="border-none! w-full" />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox inputId="patient-consent" />
          <label for="patient-consent" class="text-normal cursor-pointer">Pacient(ka) bol(a) informovaný(á) a vyjadril(a) súhlas so spracovaním osobných údajov a ošetrovateľskými opatreniami</label>
        </div>
      </section>

      <!-- Submit Button -->
       <div class="flex justify-end">
        <Button
          type="submit"
          class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white w-100"
        >
          Generovať dokument
          <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
        </Button>
      </div>
    </form>
  </div>
</template>