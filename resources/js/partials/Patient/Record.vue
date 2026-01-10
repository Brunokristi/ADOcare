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
const selectedPatient = ref<Patient | null>(null)

const medicalDiagnosis = ref<Diagnosis | null>(null)
const filteredDiagnoses = ref<Diagnosis[]>([])
const recommendedPharmacy = ref('')
const treatingDoctor = ref('')
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
  socialConditions: null as string | null,
  otherFindings: '',
  socialStatus: null as string | null,

  contacts: {
    children: false,
    relatives: false,
    friends: false,
    neighbors: false,
    selfHelpGroups: false,
    careService: false
  },

  culturalSituation: null as string | null,
  media: {
    tv: false,
    radio: false,
    newspaper: false
  }
})

const socialConditionOptions = [
  { label: 'Žije sám (a)', value: 'alone' },
  { label: 'S rodinou', value: 'with_family' },
  { label: 'V zar. soc. služieb (ZSS)', value: 'zss' }
]

const socialStatusOptions = [
  { label: 'Zamestnaný (á)', value: 'employed' },
  { label: 'Nezamestnaný (á)', value: 'unemployed' },
  { label: 'Dôchodca', value: 'pensioner' },
  { label: 'Invalidný (á) dôchodca', value: 'disabled_pensioner' },
  { label: 'MD', value: 'md' }
]

const culturalOptions = [
  { label: 'Uprednostňuje samotu', value: 'prefers_solitude' },
  { label: 'Spoločnosť', value: 'company' }
]


// Health perception
const healthPerception = ref('')

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
  disorientation: {
    time: false,
    place: false
  },
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
  weightKg: '',

  problemExists: null as 'yes' | 'no' | null,

  obesity: false,
  cachexia: false,
  weightChange: null as 'loss' | 'gain' | null,

  appetiteLoss: false,
  nausea: false,
  vomiting: false,
  swallowingDisorder: false,
  heartburn: false,

  nutritionalSupplements: false,
  nutritionalSupplementsType: '',

  appetite: null as 'adequate' | 'limited' | null,

  foodIntake: null as 'alone' | 'help' | 'tube' | null,
  nasogastricTubeDate: null as Date | null,

  gastrostomy: false,
  gastrostomyDate: null as Date | null,

  peg: false,
  pegDate: null as Date | null,

  fluidIntake: '',
  fluids: {
    enteral: false,
    parenteral: false,
    sipping: false
  },

  cvk: false,
  cvkDate: null as Date | null,

  peripheralAccess: false,
  peripheralAccessDate: null as Date | null,

  dentures: null as 'yes' | 'no' | null,

  otherNotes: ''
})

// Elimination
const elimination = ref({
  // Defekácia
  defecationProblem: null as 'yes' | 'no' | null,
  defecationIrregular: false,
  bowelMovementDiarrhea: false,
  constipation: false,
  stoolAdmixtures: false,
  fecalIncontinence: false,
  hemorrhoids: false,
  stoma: false,
  stomaDate: null as Date | null,

  stomaCareHelp: null as 'yes' | 'no' | null,
  bowelRegulation: null as 'yes' | 'no' | null,
  bowelRegulationMethods: {
    tea: false,
    suppository: false,
    enema: false
  },

  defecationOtherNotes: '',

  // Močenie
  micturitionDiuresis: '',
  micturitionProblem: null as 'yes' | 'no' | null,
  dysuria: false,
  retention: false,
  urinaryIncontinence: false,
  absorptivePads: false,

  catheter: false,
  catheterDate: null as Date | null,

  urineColor: '',

  urostomy: false,
  urostomyDate: null as Date | null,

  peritonealDialysis: false,
  hemodialysis: false,
  hemodialysisDate: null as Date | null,

  urinaryCondomSystem: false,

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
  skinProblem: null as 'yes' | 'no' | null,
  temperature: null as 'warm' | 'cold' | null,
  moisture: null as 'dry' | 'sweaty' | null,

  color: null as 'pink' | 'pale' | 'jaundice' | 'cyanotic' | null,
  turgor: null as 'normal' | 'low' | null,
  integrity: null as 'intact' | 'damaged' | null,

  rashes: false,
  itching: false,
  peeling: false,
  maceration: false,
  bruising: false,
  inflammation: false,

  wounds: {
    superficial: false,
    open: false,
    surgical: false
  },
  woundLocation: null as 'abdominal' | 'vaginal' | null,

  bleeding: false,
  ulcusCruris: false,
  gangrene: false,
  decubitus: false,

  localization: '',
  defectSizeCm: '',
  postOpDay: '',

  edemaExists: null as 'yes' | 'no' | null,
  edemaType: null as 'local' | 'general' | null,
  edemaMeasures: {
    bandageLowerExtremity: false,
    antiemboliStockings: false,
    vascularExercise: false
  },

  mucousProblem: null as 'yes' | 'no' | null,
  mucousFindings: {
    poorPerfusion: false,
    bleeding: false,
    infection: false,
    oralCavityChanges: false
  },

  hygieneStatus: null as 'adequate' | 'neglected' | null,
  hygienePerformedBy: null as 'self' | 'help' | 'dependent' | null,

  otherNotes: ''
})

// Postpartum
const postpartum = ref({
  parity: null as 'primipara' | 'multipara' | 'grandmultipara' | null,
  deliveryDate: null as Date | null,

  deliveryType: null as 'spontaneous' | 'operative' | null,

  complications: null as 'yes' | 'no' | null,
  complicationsCharacter: '',

  fundusUteri: '',
  lochiaAppearance: '',
  lochiaAmount: '',

  woundHealing: null as 'per_primam' | 'per_sekundam' | null,

  breasts: {
    free: false,
    redness: false,
    nippleCracks: false,
    pain: false,
    milkRetention: false
  },

  lactation: null as 'yes' | 'partial' | 'no' | null,

  newbornSex: null as 'boy' | 'girl' | null,
  newbornWeightG: '',
  newbornLengthCm: '',
  headCircumferenceCm: '',
  chestCircumferenceCm: '',

  otherNotes: ''
})

// Pain
const pain = ref({
  painExists: null as 'yes' | 'no' | null,
  painType: null as 'acute' | 'chronic' | null,
  localization: '',
  character: '',
  otherNotes: ''
})


// Communication
const communication = ref({
  type: null as 'verbal' | 'nonverbal' | null,
  problemExists: null as 'yes' | 'no' | null,
  problemType: null as 'speech_disorder' | 'impossible' | null,
  otherNotes: ''
})

// Learning
const learning = ref({
  problemExists: null as 'yes' | 'no' | null,

  senseChanges: {
    vision: false,
    hearing: false,
    speech: false
  },

  senseChangesDetail: {
    exists: null as 'yes' | 'no' | null,
    description: ''
  },

  compensatory: {
    glasses: false,
    lenses: false,
    hearingAid: false
  },

  otherNotes: '',

  knowledgeAboutDisease: null as 'sufficient' | 'insufficient' | null,

  education: {
    homeCare: false,
    postOpCare: false,
    sixWeeksCare: false,
    palliativeCare: false,
    postChemoCare: false
  }
})


const needs = ref({
  psych: {
    problemExists: null as 'yes' | 'no' | null,
    mood: null as 'adequate' | 'apathy' | 'depression' | 'euphoria' | 'aggression' | null
  },

  safety: {
    calm: false,
    balanced: false,
    fear: false,
    sadness: false,
    anxiety: false,
    anger: false,
    depression: false,
    hopelessness: false,
    helplessness: false,
    confusion: false,
    selfBlame: false,
    selfHarm: false
  },

  social: {
    problemExists: null as 'yes' | 'no' | null,
    socialHelp: null as 'needed' | 'not_needed' | null
  },

  spiritual: {
    problemExists: null as 'yes' | 'no' | null
  },

  otherNotes: ''
})

const deficiency = ref({
  areas: {
    nutrition: false,
    elimination: false,
    hygiene: false,
    dressing: false
  },
  careType: null as string | null,
  woundCare: null as string | null,
  focus: null as string | null,
  otherNotes: ''
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
            <label class="block text-normal">Dátum a čas posúdenia</label>
            <DatePicker
              v-model="admissionDate"
              dateFormat="dd.mm.yy"
              showTime
              hourFormat="24"
              :showIcon="false"
              class="w-full"
              inputClass="w-full! border-none!"
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
          <Textarea
            v-model="familyAnamnesis.notes"
            class="w-full border-none!"
            rows="2"
            inputClass="w-full! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
          />
        </div>
        </div>

        
      </section>

      <!-- Sociálna anamnéza -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Sociálna anamnéza</h3>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Povolanie:</label>
          <InputText v-model="socialAnamnesis.profession" type="text" class="flex-1 border-none!" />
        </div>

        <!-- Sociálne podmienky (single choice) -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sociálne podmienky</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in socialConditionOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton
                v-model="socialAnamnesis.socialConditions"
                :inputId="`social-conditions-${idx}`"
                :value="option.value"
              />
              <label :for="`social-conditions-${idx}`" class="text-normal cursor-pointer">
                {{ option.label }}
              </label>
            </div>
          </div>
        </div>

        <!-- Iné zistenia (as in the screenshot, right under Sociálne podmienky) -->
        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="socialAnamnesis.otherFindings" type="text" class="flex-1 border-none!" />
        </div>

        <!-- Sociálne postavenie (single choice) -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sociálne postavenie</label>
          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in socialStatusOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton
                v-model="socialAnamnesis.socialStatus"
                :inputId="`social-status-${idx}`"
                :value="option.value"
              />
              <label :for="`social-status-${idx}`" class="text-normal cursor-pointer">
                {{ option.label }}
              </label>
            </div>
          </div>
        </div>

        <!-- Sociálny kontakt (MULTI choice = checkboxes) -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sociálny kontakt</label>

          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.contacts.children" inputId="social-children" :binary="true" />
              <label for="social-children" class="text-normal cursor-pointer">Deti</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.contacts.relatives" inputId="social-relatives" :binary="true" />
              <label for="social-relatives" class="text-normal cursor-pointer">Príbuzný</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.contacts.friends" inputId="social-friends" :binary="true" />
              <label for="social-friends" class="text-normal cursor-pointer">Priatelia</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.contacts.neighbors" inputId="social-neighbors" :binary="true" />
              <label for="social-neighbors" class="text-normal cursor-pointer">Susedia</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.contacts.selfHelpGroups" inputId="social-selfhelp" :binary="true" />
              <label for="social-selfhelp" class="text-normal cursor-pointer">Svojpomocné skupiny</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.contacts.careService" inputId="social-care" :binary="true" />
              <label for="social-care" class="text-normal cursor-pointer">Opatrovateľská služba</label>
            </div>
          </div>
        </div>

        <!-- Spoločensko-kultúrna situácia -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Spoločensko-kultúrna situácia</label>

          <div class="flex flex-col gap-2">
            <div v-for="(option, idx) in culturalOptions" :key="idx" class="flex items-center gap-2">
              <RadioButton
                v-model="socialAnamnesis.culturalSituation"
                :inputId="`cultural-${idx}`"
                :value="option.value"
              />
              <label :for="`cultural-${idx}`" class="text-normal cursor-pointer">
                {{ option.label }}
              </label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.media.tv" inputId="cultural-tv" :binary="true" />
              <label for="cultural-tv" class="text-normal cursor-pointer">TV</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.media.radio" inputId="cultural-radio" :binary="true" />
              <label for="cultural-radio" class="text-normal cursor-pointer">Rádio</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="socialAnamnesis.media.newspaper" inputId="cultural-newspaper" :binary="true" />
              <label for="cultural-newspaper" class="text-normal cursor-pointer">Dennú tlač</label>
            </div>
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
      </section>













      <!-- Mental Status Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Mentálny stav</h3>
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
    <div class="flex items-center gap-2">
      <RadioButton v-model="nursingAssessment.orientation" inputId="orientation-oriented" value="oriented" />
      <label for="orientation-oriented" class="text-normal cursor-pointer">Orientovaný</label>
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <div class="flex items-center gap-2">
        <RadioButton v-model="nursingAssessment.orientation" inputId="orientation-disoriented" value="disoriented" />
        <label for="orientation-disoriented" class="text-normal cursor-pointer">Dezorientovaný</label>
      </div>

      <!-- v čase / v priestore (only relevant when disoriented) -->
      <div class="flex items-center gap-2">
        <Checkbox
          v-model="nursingAssessment.disorientation.time"
          inputId="disorientation-time"
          :binary="true"
          :disabled="nursingAssessment.orientation !== 'disoriented'"
        />
        <label for="disorientation-time" class="text-normal cursor-pointer">v čase</label>
      </div>

      <div class="flex items-center gap-2">
        <Checkbox
          v-model="nursingAssessment.disorientation.place"
          inputId="disorientation-place"
          :binary="true"
          :disabled="nursingAssessment.orientation !== 'disoriented'"
        />
        <label for="disorientation-place" class="text-normal cursor-pointer">v priestore</label>
      </div>
    </div>
  </div>
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
            <div class="flex flex-col gap-2">
              <label class="block text-normal">Odsávanie z dýchacích ciest</label>
              <div class="flex items-center gap-2">
                <RadioButton v-model="breathing.suctioning" inputId="suctioning-yes" value="yes" />
                <label for="suctioning-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="breathing.suctioning" inputId="suctioning-no" value="no" />
                <label for="suctioning-no" class="text-normal cursor-pointer">Nie</label>
              </div>
            </div>
            <div class="flex flex-col gap-2">
              <label class="block text-normal">Oxygenoterapia</label>
              <div class="flex items-center gap-2">
                <RadioButton v-model="breathing.oxygenTherapy" inputId="oxygen-yes" value="yes" />
                <label for="oxygen-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="breathing.oxygenTherapy" inputId="oxygen-no" value="no" />
                <label for="oxygen-no" class="text-normal cursor-pointer">Nie</label>
              </div>
            </div>

            <div class="flex flex-col gap-2">
              <label class="block text-normal">UPV</label>
              <div class="flex items-center gap-2">
                <RadioButton v-model="breathing.mechanicalVentilation" inputId="upv-yes" value="yes" />
                <label for="upv-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="breathing.mechanicalVentilation" inputId="upv-no" value="no" />
                <label for="upv-no" class="text-normal cursor-pointer">Nie</label>
              </div>
            </div>

            <div class="flex flex-col gap-2">
              <label class="block text-normal">Inhalácia</label>
              <div class="flex items-center gap-2">
                <RadioButton v-model="breathing.inhalation" inputId="inhalation-yes" value="yes" />
                <label for="inhalation-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="breathing.inhalation" inputId="inhalation-no" value="no" />
                <label for="inhalation-no" class="text-normal cursor-pointer">Nie</label>
              </div>
            </div>
          </div>
        </div>
      </section>










      <!-- Nutrition Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Výživa</h3>

        <!-- Diéta + hmotnosť -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Diéta č.</label>
            <InputText v-model="nutrition.diet" type="text" class="flex-1 border-none!" />
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Hmotnosť</label>
            <InputText v-model="nutrition.weightKg" type="text" class="w-28 border-none!" />
            <label class="text-normal">kg</label>
          </div>
        </div>

        <!-- Problém -->
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

        <!-- Nutričný stav -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Nutričný stav</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.obesity" inputId="nutrition-obesity" :binary="true" />
              <label for="nutrition-obesity" class="text-normal cursor-pointer">Obezita</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.cachexia" inputId="nutrition-cachexia" :binary="true" />
              <label for="nutrition-cachexia" class="text-normal cursor-pointer">Kachexia</label>
            </div>

            <!-- zmena hmotnosti -->
            <div class="flex flex-col gap-2">
              <label class="block text-normal">Zmena hmotnosti</label>
              <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                  <RadioButton v-model="nutrition.weightChange" inputId="weight-loss" value="loss" />
                  <label for="weight-loss" class="text-normal cursor-pointer">Úbytok hmotnosti</label>
                </div>
                <div class="flex items-center gap-2">
                  <RadioButton v-model="nutrition.weightChange" inputId="weight-gain" value="gain" />
                  <label for="weight-gain" class="text-normal cursor-pointer">Prírastok hmotnosti</label>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.appetiteLoss" inputId="nutrition-anorexia" :binary="true" />
              <label for="nutrition-anorexia" class="text-normal cursor-pointer">Nechutenstvo</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.nausea" inputId="nutrition-nausea" :binary="true" />
              <label for="nutrition-nausea" class="text-normal cursor-pointer">Nauzea</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.vomiting" inputId="nutrition-vomiting" :binary="true" />
              <label for="nutrition-vomiting" class="text-normal cursor-pointer">Zvracanie</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.swallowingDisorder" inputId="nutrition-swallowing" :binary="true" />
              <label for="nutrition-swallowing" class="text-normal cursor-pointer">Porucha prehĺtania</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.heartburn" inputId="nutrition-heartburn" :binary="true" />
              <label for="nutrition-heartburn" class="text-normal cursor-pointer">Pálenie záhy</label>
            </div>

            <!-- Enterálne / nutričné prípravky + aké -->
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.nutritionalSupplements" inputId="nutrition-supplements" :binary="true" />
              <label for="nutrition-supplements" class="text-normal cursor-pointer whitespace-nowrap">
                Enterálne / nutričné prípravky, aké:
              </label>
              <InputText
                v-model="nutrition.nutritionalSupplementsType"
                type="text"
                class="flex-1 border-none!"
                :disabled="!nutrition.nutritionalSupplements"
              />
            </div>
          </div>
        </div>

        <!-- Chuť do jedla -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Chuť do jedla</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.appetite" inputId="appetite-adequate" value="adequate" />
              <label for="appetite-adequate" class="text-normal cursor-pointer">Primeraná</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.appetite" inputId="appetite-limited" value="limited" />
              <label for="appetite-limited" class="text-normal cursor-pointer">Obmedzená</label>
            </div>
          </div>
        </div>

        <!-- Príjem stravy -->
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
              <label for="intake-tube" class="text-normal cursor-pointer whitespace-nowrap">
                Nazog. sonda / dátum zavedenia:
              </label>
              <DatePicker
                v-if="nutrition.foodIntake === 'tube'"
                v-model="nutrition.nasogastricTubeDate"
                dateFormat="dd.mm.yy"
                :showIcon="false"
                class="w-40"
                inputClass="!w-full border-none!"
              />
            </div>
          </div>
        </div>

        <!-- Gastrostómia -->
        <div class="flex items-center gap-2">
          <Checkbox v-model="nutrition.gastrostomy" inputId="nutrition-gastrostomy" :binary="true" />
          <label for="nutrition-gastrostomy" class="text-normal cursor-pointer whitespace-nowrap">
            Gastrostómia / dátum zavedenia:
          </label>
          <DatePicker
            v-model="nutrition.gastrostomyDate"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="flex-1"
            inputClass="!w-full border-none!"
            :disabled="!nutrition.gastrostomy"
          />
        </div>

        <!-- PEG -->
        <div class="flex items-center gap-2">
          <Checkbox v-model="nutrition.peg" inputId="nutrition-peg" :binary="true" />
          <label for="nutrition-peg" class="text-normal cursor-pointer whitespace-nowrap">
            PEG / dátum zavedenia:
          </label>
          <DatePicker
            v-model="nutrition.pegDate"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="flex-1"
            inputClass="!w-full border-none!"
            :disabled="!nutrition.peg"
          />
        </div>

        <!-- Príjem tekutín + typ -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Príjem tekutín / 24 hod.:</label>
            <InputText v-model="nutrition.fluidIntake" type="text" class="w-28 border-none!" />
            <label class="text-normal">ml</label>
          </div>

          <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.fluids.enteral" inputId="fluids-enteral" :binary="true" />
              <label for="fluids-enteral" class="text-normal cursor-pointer">Enterálne</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.fluids.parenteral" inputId="fluids-parenteral" :binary="true" />
              <label for="fluids-parenteral" class="text-normal cursor-pointer">Parenterálne</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="nutrition.fluids.sipping" inputId="fluids-sipping" :binary="true" />
              <label for="fluids-sipping" class="text-normal cursor-pointer">Sipping</label>
            </div>
          </div>
        </div>

        <!-- CVK -->
        <div class="flex items-center gap-2">
          <Checkbox v-model="nutrition.cvk" inputId="nutrition-cvk" :binary="true" />
          <label for="nutrition-cvk" class="text-normal cursor-pointer whitespace-nowrap">
            CVK / dátum zavedenia:
          </label>
          <DatePicker
            v-model="nutrition.cvkDate"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="flex-1"
            inputClass="!w-full border-none!"
            :disabled="!nutrition.cvk"
          />
        </div>

        <!-- Periférny i.v. prístup -->
        <div class="flex items-center gap-2">
          <Checkbox v-model="nutrition.peripheralAccess" inputId="nutrition-peripheral" :binary="true" />
          <label for="nutrition-peripheral" class="text-normal cursor-pointer whitespace-nowrap">
            Periférny i.v. prístup / dátum zavedenia:
          </label>
          <DatePicker
            v-model="nutrition.peripheralAccessDate"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="flex-1"
            inputClass="!w-full border-none!"
            :disabled="!nutrition.peripheralAccess"
          />
        </div>

        <!-- Zubná protéza -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Kompenzačné pomôcky – zubná protéza</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.dentures" inputId="dentures-yes" value="yes" />
              <label for="dentures-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="nutrition.dentures" inputId="dentures-no" value="no" />
              <label for="dentures-no" class="text-normal cursor-pointer">Nie</label>
            </div>
          </div>
        </div>

        <!-- Iné zistenia -->
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

        <!-- Defekácia - príznaky -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Defekácia</label>

          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.defecationIrregular" inputId="defecation-irregular" :binary="true" />
              <label for="defecation-irregular" class="text-normal cursor-pointer">Nepravidelná</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.bowelMovementDiarrhea" inputId="defecation-diarrhea" :binary="true" />
              <label for="defecation-diarrhea" class="text-normal cursor-pointer">Hnačka</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.constipation" inputId="defecation-constipation" :binary="true" />
              <label for="defecation-constipation" class="text-normal cursor-pointer">Zápcha</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.stoolAdmixtures" inputId="defecation-admixtures" :binary="true" />
              <label for="defecation-admixtures" class="text-normal cursor-pointer">S prímesami</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.fecalIncontinence" inputId="defecation-incontinence" :binary="true" />
              <label for="defecation-incontinence" class="text-normal cursor-pointer">Inkontinencia</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.hemorrhoids" inputId="defecation-hemorrhoids" :binary="true" />
              <label for="defecation-hemorrhoids" class="text-normal cursor-pointer">Hemoroidy</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.stoma" inputId="defecation-stoma" :binary="true" />
              <label for="defecation-stoma" class="text-normal cursor-pointer whitespace-nowrap">
                Stómia ošetrená naposledy / dátum zavedenia:
              </label>
              <DatePicker
                v-model="elimination.stomaDate"
                dateFormat="dd.mm.yy"
                :showIcon="false"
                class="flex-1"
                inputClass="!w-full border-none!"
                :disabled="!elimination.stoma"
              />
            </div>
          </div>
        </div>

        <!-- Potreba pomoci + regulácia -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Potreba pomoci pri ošetrovaní stómie</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="elimination.stomaCareHelp" inputId="stoma-help-yes" value="yes" />
                <label for="stoma-help-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="elimination.stomaCareHelp" inputId="stoma-help-no" value="no" />
                <label for="stoma-help-no" class="text-normal cursor-pointer">Nie</label>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label class="block text-normal">Regulácia vyprázdňovania</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="elimination.bowelRegulation" inputId="bowel-reg-no" value="no" />
                <label for="bowel-reg-no" class="text-normal cursor-pointer">Nie</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="elimination.bowelRegulation" inputId="bowel-reg-yes" value="yes" />
                <label for="bowel-reg-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
            </div>
          </div>
        </div>

        <!-- Spôsob regulácie -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Spôsob regulácie vyprázdňovania</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.bowelRegulationMethods.tea" inputId="bowel-method-tea" :binary="true" :disabled="elimination.bowelRegulation !== 'yes'" />
              <label for="bowel-method-tea" class="text-normal cursor-pointer">Čaj</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.bowelRegulationMethods.suppository" inputId="bowel-method-supp" :binary="true" :disabled="elimination.bowelRegulation !== 'yes'" />
              <label for="bowel-method-supp" class="text-normal cursor-pointer">Čípok</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.bowelRegulationMethods.enema" inputId="bowel-method-enema" :binary="true" :disabled="elimination.bowelRegulation !== 'yes'" />
              <label for="bowel-method-enema" class="text-normal cursor-pointer">Klyzma</label>
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
          <label class="text-normal whitespace-nowrap">Diuréza / 24 hod.:</label>
          <InputText v-model="elimination.micturitionDiuresis" type="text" class="w-28 border-none!" />
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
              <Checkbox v-model="elimination.dysuria" inputId="micturition-dysuria" :binary="true" />
              <label for="micturition-dysuria" class="text-normal cursor-pointer">Dyzúria</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.retention" inputId="micturition-retention" :binary="true" />
              <label for="micturition-retention" class="text-normal cursor-pointer">Retencia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.urinaryIncontinence" inputId="micturition-incontinence" :binary="true" />
              <label for="micturition-incontinence" class="text-normal cursor-pointer">Inkontinencia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="elimination.absorptivePads" inputId="micturition-pads" :binary="true" />
              <label for="micturition-pads" class="text-normal cursor-pointer">Absorpčné pomôcky</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="elimination.catheter" inputId="micturition-catheter" :binary="true" />
          <label for="micturition-catheter" class="text-normal cursor-pointer whitespace-nowrap">RK / dátum zavedenia:</label>
          <DatePicker
            v-model="elimination.catheterDate"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="flex-1"
            inputClass="!w-full border-none!"
            :disabled="!elimination.catheter"
          />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Farba moču:</label>
          <InputText v-model="elimination.urineColor" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="elimination.urostomy" inputId="micturition-urostomy" :binary="true" />
          <label for="micturition-urostomy" class="text-normal cursor-pointer whitespace-nowrap">Urostómia / dátum zavedenia:</label>
          <DatePicker
            v-model="elimination.urostomyDate"
            dateFormat="dd.mm.yy"
            :showIcon="false"
            class="flex-1"
            inputClass="!w-full border-none!"
            :disabled="!elimination.urostomy"
          />
        </div>

        <!-- Dialýzy + kondómový systém -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <Checkbox v-model="elimination.peritonealDialysis" inputId="pd" :binary="true" />
            <label for="pd" class="text-normal cursor-pointer">Peritoneálna dialýza</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="elimination.hemodialysis" inputId="hd" :binary="true" />
            <label for="hd" class="text-normal cursor-pointer whitespace-nowrap">Hemodialýza / dátum zavedenia:</label>
            <DatePicker
              v-model="elimination.hemodialysisDate"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="flex-1"
              inputClass="!w-full border-none!"
              :disabled="!elimination.hemodialysis"
            />
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="elimination.urinaryCondomSystem" inputId="condom-system" :binary="true" />
            <label for="condom-system" class="text-normal cursor-pointer">Urinárny kondómový systém</label>
          </div>
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

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Úroveň mobility</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="mobility.mobilityLevel" inputId="mobility-1" value="full" />
              <label for="mobility-1" class="text-normal cursor-pointer">1 Plná</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="mobility.mobilityLevel" inputId="mobility-2" value="mild" />
              <label for="mobility-2" class="text-normal cursor-pointer">2 Mierne obm.</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="mobility.mobilityLevel" inputId="mobility-3" value="severe" />
              <label for="mobility-3" class="text-normal cursor-pointer">3 Veľmi obm.</label>
            </div>
            <div class="flex items-center gap-2">
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

        <!-- Koža: problém + teplota + vlhkosť (as in form row) -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>

          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.skinProblem" inputId="skin-problem-no" value="no" />
              <label for="skin-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>

            <div class="flex flex-wrap items-center gap-4">
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.skinProblem" inputId="skin-problem-yes" value="yes" />
                <label for="skin-problem-yes" class="text-normal cursor-pointer">Áno</label>
              </div>

              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.temperature" inputId="skin-temp-warm" value="warm" />
                <label for="skin-temp-warm" class="text-normal cursor-pointer">Teplá</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.temperature" inputId="skin-temp-cold" value="cold" />
                <label for="skin-temp-cold" class="text-normal cursor-pointer">Studená</label>
              </div>

              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.moisture" inputId="skin-moist-dry" value="dry" />
                <label for="skin-moist-dry" class="text-normal cursor-pointer">Suchá</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.moisture" inputId="skin-moist-sweaty" value="sweaty" />
                <label for="skin-moist-sweaty" class="text-normal cursor-pointer">Spotená</label>
              </div>
            </div>
          </div>
        </div>

        <!-- Farba -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Farba</label>
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
              <RadioButton v-model="skin.color" inputId="skin-color-jaundice" value="jaundice" />
              <label for="skin-color-jaundice" class="text-normal cursor-pointer">Ikterická</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.color" inputId="skin-color-cyanotic" value="cyanotic" />
              <label for="skin-color-cyanotic" class="text-normal cursor-pointer">Cyanotická</label>
            </div>
          </div>
        </div>

        <!-- Turgor + Celistvosť -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Turgor</label>
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

          <div class="flex flex-col gap-2">
            <label class="block text-normal">Celistvosť kože</label>
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
        </div>

        <!-- Zmeny na koži -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Zmeny na koži</label>
          <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.rashes" inputId="skin-rashes" :binary="true" />
              <label for="skin-rashes" class="text-normal cursor-pointer">Kožné vyrážky</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.itching" inputId="skin-itching" :binary="true" />
              <label for="skin-itching" class="text-normal cursor-pointer">Svrbenie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.peeling" inputId="skin-peeling" :binary="true" />
              <label for="skin-peeling" class="text-normal cursor-pointer">Olupovanie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.maceration" inputId="skin-maceration" :binary="true" />
              <label for="skin-maceration" class="text-normal cursor-pointer">Zaparenia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.bruising" inputId="skin-bruising" :binary="true" />
              <label for="skin-bruising" class="text-normal cursor-pointer">Modriny</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.inflammation" inputId="skin-inflammation" :binary="true" />
              <label for="skin-inflammation" class="text-normal cursor-pointer">Zápal</label>
            </div>
          </div>
        </div>

        <!-- Typ poranenia (form = checkboxes, not radios) -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Poranenie</label>
          <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.wounds.superficial" inputId="wound-superficial" :binary="true" />
              <label for="wound-superficial" class="text-normal cursor-pointer">Povrchové poranenie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.wounds.open" inputId="wound-open" :binary="true" />
              <label for="wound-open" class="text-normal cursor-pointer">Otvorená rana</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.wounds.surgical" inputId="wound-surgical" :binary="true" />
              <label for="wound-surgical" class="text-normal cursor-pointer">Operačná rana</label>
            </div>

            <!-- location radios shown on the same line in the form -->
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.woundLocation" inputId="wound-loc-abdominal" value="abdominal" />
              <label for="wound-loc-abdominal" class="text-normal cursor-pointer">Abdominálna</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.woundLocation" inputId="wound-loc-vaginal" value="vaginal" />
              <label for="wound-loc-vaginal" class="text-normal cursor-pointer">Vaginálna</label>
            </div>
          </div>
        </div>

        <!-- Komplikácie -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Komplikácie</label>
          <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.bleeding" inputId="skin-bleeding" :binary="true" />
              <label for="skin-bleeding" class="text-normal cursor-pointer">Krvácanie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.ulcusCruris" inputId="skin-ulcus" :binary="true" />
              <label for="skin-ulcus" class="text-normal cursor-pointer">Ulcus cruris</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.gangrene" inputId="skin-gangrene" :binary="true" />
              <label for="skin-gangrene" class="text-normal cursor-pointer">Gangréna</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="skin.decubitus" inputId="skin-decubitus" :binary="true" />
              <label for="skin-decubitus" class="text-normal cursor-pointer">Dekubity</label>
            </div>
          </div>
        </div>

        <!-- Lokalizácia + veľkosť -->
        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Lokalizácia:</label>
          <InputText v-model="skin.localization" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Veľkosť defektu:</label>
          <InputText v-model="skin.defectSizeCm" type="text" class="w-28 border-none!" />
          <label class="text-normal">cm</label>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Pacient/pacientka je:</label>
          <InputText v-model="skin.postOpDay" type="text" class="w-24 border-none!" />
          <label class="text-normal">deň po operácii</label>
        </div>

        <!-- Edémy -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Edémy</label>

          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.edemaExists" inputId="edema-no" value="no" />
              <label for="edema-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.edemaExists" inputId="edema-yes" value="yes" />
              <label for="edema-yes" class="text-normal cursor-pointer">Áno</label>
            </div>

            <div class="flex flex-wrap gap-4">
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.edemaType" inputId="edema-local" value="local" :disabled="skin.edemaExists !== 'yes'" />
                <label for="edema-local" class="text-normal cursor-pointer">Miestne</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="skin.edemaType" inputId="edema-general" value="general" :disabled="skin.edemaExists !== 'yes'" />
                <label for="edema-general" class="text-normal cursor-pointer">Celkové</label>
              </div>

              <div class="flex items-center gap-2">
                <Checkbox v-model="skin.edemaMeasures.bandageLowerExtremity" inputId="skin-bandage" :binary="true" :disabled="skin.edemaExists !== 'yes'" />
                <label for="skin-bandage" class="text-normal cursor-pointer">Bandáž DK</label>
              </div>
              <div class="flex items-center gap-2">
                <Checkbox v-model="skin.edemaMeasures.antiemboliStockings" inputId="skin-stockings" :binary="true" :disabled="skin.edemaExists !== 'yes'" />
                <label for="skin-stockings" class="text-normal cursor-pointer">Antitrombotické pančuchy</label>
              </div>
              <div class="flex items-center gap-2">
                <Checkbox v-model="skin.edemaMeasures.vascularExercise" inputId="skin-vascular" :binary="true" :disabled="skin.edemaExists !== 'yes'" />
                <label for="skin-vascular" class="text-normal cursor-pointer">Cievna gymnastika</label>
              </div>
            </div>
          </div>
        </div>

        <!-- Sliznice -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sliznice</label>

          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.mucousProblem" inputId="mucous-no" value="no" />
              <label for="mucous-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="skin.mucousProblem" inputId="mucous-yes" value="yes" />
              <label for="mucous-yes" class="text-normal cursor-pointer">Áno</label>
            </div>

            <div class="flex flex-wrap gap-4">
              <div class="flex items-center gap-2">
                <Checkbox v-model="skin.mucousFindings.poorPerfusion" inputId="mucous-poor-perf" :binary="true" :disabled="skin.mucousProblem !== 'yes'" />
                <label for="mucous-poor-perf" class="text-normal cursor-pointer">Neprekrvené</label>
              </div>
              <div class="flex items-center gap-2">
                <Checkbox v-model="skin.mucousFindings.bleeding" inputId="mucous-bleeding" :binary="true" :disabled="skin.mucousProblem !== 'yes'" />
                <label for="mucous-bleeding" class="text-normal cursor-pointer">Krvácanie</label>
              </div>
              <div class="flex items-center gap-2">
                <Checkbox v-model="skin.mucousFindings.infection" inputId="mucous-infection" :binary="true" :disabled="skin.mucousProblem !== 'yes'" />
                <label for="mucous-infection" class="text-normal cursor-pointer">Infekcia</label>
              </div>
              <div class="flex items-center gap-2">
                <Checkbox v-model="skin.mucousFindings.oralCavityChanges" inputId="mucous-oral" :binary="true" :disabled="skin.mucousProblem !== 'yes'" />
                <label for="mucous-oral" class="text-normal cursor-pointer">Zmeny na sliznici dutiny ústnej</label>
              </div>
            </div>
          </div>
        </div>

        <!-- Hygiena -->
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
              <label for="hygiene-dependent" class="text-normal cursor-pointer">Je úplne závislý (á)</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="skin.otherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>





      <!-- Postpartum Assessment (Šestonedieľka) -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Pôrodné posúdenie (šestonedieľka)</h3>

        <!-- Parita + dátum pôrodu -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Pôrod:</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="postpartum.parity" inputId="parity-primipara" value="primipara" />
                <label for="parity-primipara" class="text-normal cursor-pointer">Prvorodička</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="postpartum.parity" inputId="parity-multipara" value="multipara" />
                <label for="parity-multipara" class="text-normal cursor-pointer">Druhorodička</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="postpartum.parity" inputId="parity-grandmultipara" value="grandmultipara" />
                <label for="parity-grandmultipara" class="text-normal cursor-pointer">Viacrodička</label>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Dátum pôrodu:</label>
            <DatePicker
              v-model="postpartum.deliveryDate"
              dateFormat="dd.mm.yy"
              :showIcon="false"
              class="flex-1"
              inputClass="!w-full border-none!"
            />
          </div>
        </div>

        <!-- Spôsob pôrodu -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Pôrod:</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.deliveryType" inputId="delivery-spontaneous" value="spontaneous" />
              <label for="delivery-spontaneous" class="text-normal cursor-pointer">Spontánny</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.deliveryType" inputId="delivery-operative" value="operative" />
              <label for="delivery-operative" class="text-normal cursor-pointer">Operatívny</label>
            </div>
          </div>
        </div>

        <!-- Komplikácie -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="block text-normal">Komplikácie po pôrode:</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="postpartum.complications" inputId="comp-no" value="no" />
                <label for="comp-no" class="text-normal cursor-pointer">Nie</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="postpartum.complications" inputId="comp-yes" value="yes" />
                <label for="comp-yes" class="text-normal cursor-pointer">Áno</label>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Charakter komplikácií:</label>
            <InputText
              v-model="postpartum.complicationsCharacter"
              type="text"
              class="flex-1 border-none!"
              :disabled="postpartum.complications !== 'yes'"
            />
          </div>
        </div>

        <!-- Fundus + krvácanie/lochie -->
        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Fundus maternice:</label>
          <InputText v-model="postpartum.fundusUteri" type="text" class="flex-1 border-none!" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Stav krvácania/lochie – vzhľad:</label>
            <InputText v-model="postpartum.lochiaAppearance" type="text" class="flex-1 border-none!" />
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Množstvo:</label>
            <InputText v-model="postpartum.lochiaAmount" type="text" class="flex-1 border-none!" />
          </div>
        </div>

        <!-- Hojenie poranenia -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Hojenie popôrodného poranenia:</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.woundHealing" inputId="healing-per-primam" value="per_primam" />
              <label for="healing-per-primam" class="text-normal cursor-pointer">per primam</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.woundHealing" inputId="healing-per-sekundam" value="per_sekundam" />
              <label for="healing-per-sekundam" class="text-normal cursor-pointer">per sekundam</label>
            </div>
          </div>
        </div>

        <!-- Prsníky -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Prsníky:</label>
          <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
              <Checkbox v-model="postpartum.breasts.free" inputId="breasts-free" :binary="true" />
              <label for="breasts-free" class="text-normal cursor-pointer">Voľné</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="postpartum.breasts.redness" inputId="breasts-redness" :binary="true" />
              <label for="breasts-redness" class="text-normal cursor-pointer">Začervenanie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="postpartum.breasts.nippleCracks" inputId="breasts-cracks" :binary="true" />
              <label for="breasts-cracks" class="text-normal cursor-pointer">Trhlinky bradaviek</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="postpartum.breasts.pain" inputId="breasts-pain" :binary="true" />
              <label for="breasts-pain" class="text-normal cursor-pointer">Bolestivosť</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="postpartum.breasts.milkRetention" inputId="breasts-retention" :binary="true" />
              <label for="breasts-retention" class="text-normal cursor-pointer">Retencia mlieka</label>
            </div>
          </div>
        </div>

        <!-- Laktácia -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Laktácia/rozvinutie:</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.lactation" inputId="lact-yes" value="yes" />
              <label for="lact-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.lactation" inputId="lact-partial" value="partial" />
              <label for="lact-partial" class="text-normal cursor-pointer">Čiastočne</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.lactation" inputId="lact-no" value="no" />
              <label for="lact-no" class="text-normal cursor-pointer">Nie</label>
            </div>
          </div>
        </div>

        <!-- Novorodenec -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Novorodenec:</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.newbornSex" inputId="nb-boy" value="boy" />
              <label for="nb-boy" class="text-normal cursor-pointer">Chlapec</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="postpartum.newbornSex" inputId="nb-girl" value="girl" />
              <label for="nb-girl" class="text-normal cursor-pointer">Dievča</label>
            </div>
          </div>
        </div>

        <!-- Miery novorodenca -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Pôrodná hmotnosť:</label>
            <InputText v-model="postpartum.newbornWeightG" type="text" class="w-28 border-none!" />
            <label class="text-normal">g</label>
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Dĺžka:</label>
            <InputText v-model="postpartum.newbornLengthCm" type="text" class="w-28 border-none!" />
            <label class="text-normal">cm</label>
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Obvod hlavy:</label>
            <InputText v-model="postpartum.headCircumferenceCm" type="text" class="w-28 border-none!" />
            <label class="text-normal">cm</label>
          </div>

          <div class="flex items-center gap-2">
            <label class="text-normal whitespace-nowrap">Obvod hrudníka:</label>
            <InputText v-model="postpartum.chestCircumferenceCm" type="text" class="w-28 border-none!" />
            <label class="text-normal">cm</label>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="postpartum.otherNotes" type="text" class="flex-1 border-none!" />
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

        <div v-if="pain.painExists === 'yes'" class="flex flex-col gap-2">
          <label class="block text-normal">Typ bolesti</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="pain.painType" inputId="pain-acute" value="acute" />
              <label for="pain-acute" class="text-normal cursor-pointer">Akútna</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="pain.painType" inputId="pain-chronic" value="chronic" />
              <label for="pain-chronic" class="text-normal cursor-pointer">Chronická</label>
            </div>
          </div>
        </div>

        <div v-if="pain.painExists === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Lokalizácia:</label>
          <InputText v-model="pain.localization" type="text" class="flex-1 border-none!" />
        </div>

        <div v-if="pain.painExists === 'yes'" class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Charakter:</label>
          <InputText v-model="pain.character" type="text" class="flex-1 border-none!" />
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="pain.otherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>


      <!-- Communication Section -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Komunikácia</h3>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Typ komunikácie</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.type" inputId="comm-verbal" value="verbal" />
              <label for="comm-verbal" class="text-normal cursor-pointer">Verbálna</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.type" inputId="comm-nonverbal" value="nonverbal" />
              <label for="comm-nonverbal" class="text-normal cursor-pointer">Neverbálna</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Problém</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.problemExists" inputId="comm-problem-no" value="no" />
              <label for="comm-problem-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.problemExists" inputId="comm-problem-yes" value="yes" />
              <label for="comm-problem-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div v-if="communication.problemExists === 'yes'" class="flex flex-col gap-2">
          <label class="block text-normal">Druh pobemu</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.problemType" inputId="comm-speech-disorder" value="speech_disorder" />
              <label for="comm-speech-disorder" class="text-normal cursor-pointer">Poruchy reči</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="communication.problemType" inputId="comm-impossible" value="impossible" />
              <label for="comm-impossible" class="text-normal cursor-pointer">Nemožná</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="communication.otherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>




      <!-- Učenie, zmyslové vnímanie -->
    <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
      <h3 class="text-normal text-accent">Učenie, zmyslové vnímanie</h3>

      <!-- problém -->
      <div class="flex flex-col gap-2">
        <label class="block text-normal">Problém</label>
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <RadioButton v-model="learning.problemExists" inputId="learn-prob-no" value="no" />
            <label for="learn-prob-no" class="text-normal cursor-pointer">Nie</label>
          </div>
          <div class="flex items-center gap-2">
            <RadioButton v-model="learning.problemExists" inputId="learn-prob-yes" value="yes" />
            <label for="learn-prob-yes" class="text-normal cursor-pointer">Áno</label>
          </div>
        </div>
      </div>

      <!-- Zmeny v zmysloch -->
      <div class="flex flex-col gap-2">
        <label class="block text-normal">Zmeny v zmysloch</label>

        <div class="flex flex-wrap gap-4">
          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.senseChanges.vision" inputId="sense-vision" :binary="true" />
            <label for="sense-vision" class="text-normal cursor-pointer">Zrak</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.senseChanges.hearing" inputId="sense-hearing" :binary="true" />
            <label for="sense-hearing" class="text-normal cursor-pointer">Sluch</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.senseChanges.speech" inputId="sense-speech" :binary="true" />
            <label for="sense-speech" class="text-normal cursor-pointer">Reč</label>
          </div>
        </div>

        <!-- "nie / áno, aké:" -->
        <div class="flex flex-col gap-2 mt-2">
          <label class="block text-normal">Zmeny v zmysloch – upresnenie</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="learning.senseChangesDetail.exists" inputId="sense-detail-no" value="no" />
              <label for="sense-detail-no" class="text-normal cursor-pointer">Nie</label>
            </div>

            <div class="flex items-center gap-2">
              <RadioButton v-model="learning.senseChangesDetail.exists" inputId="sense-detail-yes" value="yes" />
              <label for="sense-detail-yes" class="text-normal cursor-pointer whitespace-nowrap">Áno, aké:</label>
              <InputText
                v-model="learning.senseChangesDetail.description"
                type="text"
                class="flex-1 border-none!"
                :disabled="learning.senseChangesDetail.exists !== 'yes'"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Kompenzačné pomôcky -->
      <div class="flex flex-col gap-2">
        <label class="block text-normal">Kompenzačné pomôcky</label>

        <div class="flex flex-wrap gap-4">
          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.compensatory.glasses" inputId="comp-glasses" :binary="true" />
            <label for="comp-glasses" class="text-normal cursor-pointer">Okuliare</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.compensatory.lenses" inputId="comp-lenses" :binary="true" />
            <label for="comp-lenses" class="text-normal cursor-pointer">Šošovky</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.compensatory.hearingAid" inputId="comp-hearing-aid" :binary="true" />
            <label for="comp-hearing-aid" class="text-normal cursor-pointer">Načúvací aparát</label>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
        <InputText v-model="learning.otherNotes" type="text" class="flex-1 border-none!" />
      </div>

      <!-- Vedomosti o chorobe -->
      <div class="flex flex-col gap-2">
        <label class="block text-normal">Vedomosti o chorobe</label>
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <RadioButton v-model="learning.knowledgeAboutDisease" inputId="knowledge-sufficient" value="sufficient" />
            <label for="knowledge-sufficient" class="text-normal cursor-pointer">Dostatok</label>
          </div>
          <div class="flex items-center gap-2">
            <RadioButton v-model="learning.knowledgeAboutDisease" inputId="knowledge-insufficient" value="insufficient" />
            <label for="knowledge-insufficient" class="text-normal cursor-pointer">Nedostatok</label>
          </div>
        </div>
      </div>

      <!-- Edukácia pacienta/pacientky -->
      <div class="flex flex-col gap-2">
        <label class="block text-normal">Edukácia pacienta/pacientky</label>

        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.education.homeCare" inputId="edu-home" :binary="true" />
            <label for="edu-home" class="text-normal cursor-pointer">O ošetrovateľskej starostlivosti v domácom prostredí</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.education.postOpCare" inputId="edu-postop" :binary="true" />
            <label for="edu-postop" class="text-normal cursor-pointer">O ošetrovateľskej starostlivosti v pooperačnom období</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.education.sixWeeksCare" inputId="edu-sixweeks" :binary="true" />
            <label for="edu-sixweeks" class="text-normal cursor-pointer">O ošetrovateľskej starostlivosti v šestonedelí</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.education.palliativeCare" inputId="edu-palliative" :binary="true" />
            <label for="edu-palliative" class="text-normal cursor-pointer">O paliatívnej starostlivosti</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="learning.education.postChemoCare" inputId="edu-chemo" :binary="true" />
            <label for="edu-chemo" class="text-normal cursor-pointer">O ošetrovateľskej starostlivosti po chemoterapii</label>
          </div>
        </div>
      </div>
    </section>





      <!-- Psychické / Sociálne / Duchovné potreby -->
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Psychické, sociálne a duchovné potreby</h3>

        <!-- Psychické potreby -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Psychické potreby – pobem</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.psych.problemExists" inputId="psych-prob-no" value="no" />
              <label for="psych-prob-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.psych.problemExists" inputId="psych-prob-yes" value="yes" />
              <label for="psych-prob-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="block text-normal">Nálada</label>
          <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.psych.mood" inputId="mood-adequate" value="adequate" />
              <label for="mood-adequate" class="text-normal cursor-pointer">Primeraná</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.psych.mood" inputId="mood-apathy" value="apathy" />
              <label for="mood-apathy" class="text-normal cursor-pointer">Apatia</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.psych.mood" inputId="mood-depression" value="depression" />
              <label for="mood-depression" class="text-normal cursor-pointer">Depresia</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.psych.mood" inputId="mood-euphoria" value="euphoria" />
              <label for="mood-euphoria" class="text-normal cursor-pointer">Eufória</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.psych.mood" inputId="mood-aggression" value="aggression" />
              <label for="mood-aggression" class="text-normal cursor-pointer">Agresia</label>
            </div>
          </div>
        </div>

        <!-- Istota, bezpečie -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Istota, bezpečie</label>
          <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.calm" inputId="safe-calm" :binary="true" />
              <label for="safe-calm" class="text-normal cursor-pointer">Kľudný</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.balanced" inputId="safe-balanced" :binary="true" />
              <label for="safe-balanced" class="text-normal cursor-pointer">Vyrovnaný</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.fear" inputId="safe-fear" :binary="true" />
              <label for="safe-fear" class="text-normal cursor-pointer">Strach</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.sadness" inputId="safe-sadness" :binary="true" />
              <label for="safe-sadness" class="text-normal cursor-pointer">Smútok</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.anxiety" inputId="safe-anxiety" :binary="true" />
              <label for="safe-anxiety" class="text-normal cursor-pointer">Úzkosť</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.anger" inputId="safe-anger" :binary="true" />
              <label for="safe-anger" class="text-normal cursor-pointer">Hnev</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.depression" inputId="safe-depression" :binary="true" />
              <label for="safe-depression" class="text-normal cursor-pointer">Depresia</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.hopelessness" inputId="safe-hopelessness" :binary="true" />
              <label for="safe-hopelessness" class="text-normal cursor-pointer">Beznádej</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.helplessness" inputId="safe-helplessness" :binary="true" />
              <label for="safe-helplessness" class="text-normal cursor-pointer">Bezmocnosť</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.confusion" inputId="safe-confusion" :binary="true" />
              <label for="safe-confusion" class="text-normal cursor-pointer">Zmätenosť</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.selfBlame" inputId="safe-selfblame" :binary="true" />
              <label for="safe-selfblame" class="text-normal cursor-pointer">Sebaobviňovanie</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="needs.safety.selfHarm" inputId="safe-selfharm" :binary="true" />
              <label for="safe-selfharm" class="text-normal cursor-pointer">Sebautláčanie</label>
            </div>
          </div>
        </div>

        <!-- Sociálne potreby -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Sociálne potreby – pobem</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.social.problemExists" inputId="social-prob-no" value="no" />
              <label for="social-prob-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.social.problemExists" inputId="social-prob-yes" value="yes" />
              <label for="social-prob-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>

          <div class="flex flex-col gap-2 mt-2">
            <label class="block text-normal">Sociálna pomoc</label>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <RadioButton v-model="needs.social.socialHelp" inputId="socialhelp-needed" value="needed" />
                <label for="socialhelp-needed" class="text-normal cursor-pointer">Je odkázaný (á) na sociálnu pomoc</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="needs.social.socialHelp" inputId="socialhelp-notneeded" value="not_needed" />
                <label for="socialhelp-notneeded" class="text-normal cursor-pointer">Nie odkázaný (á) na sociálnu pomoc</label>
              </div>
            </div>
          </div>
        </div>

        <!-- Duchovné potreby -->
        <div class="flex flex-col gap-2">
          <label class="block text-normal">Duchovné potreby – pobem</label>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.spiritual.problemExists" inputId="spirit-prob-no" value="no" />
              <label for="spirit-prob-no" class="text-normal cursor-pointer">Nie</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="needs.spiritual.problemExists" inputId="spirit-prob-yes" value="yes" />
              <label for="spirit-prob-yes" class="text-normal cursor-pointer">Áno</label>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="needs.otherNotes" type="text" class="flex-1 border-none!" />
        </div>
      </section>

      
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <h3 class="text-normal text-accent">Nedostatočnosť v oblasti</h3>

        <!-- Areas -->
        <div class="flex flex-wrap gap-4">
          <div class="flex items-center gap-2">
            <Checkbox v-model="deficiency.areas.nutrition" inputId="def-nutrition" />
            <label for="def-nutrition">výživy</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="deficiency.areas.elimination" inputId="def-elimination" />
            <label for="def-elimination">vyprázdňovania</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="deficiency.areas.hygiene" inputId="def-hygiene" />
            <label for="def-hygiene">hygieny</label>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="deficiency.areas.dressing" inputId="def-dressing" />
            <label for="def-dressing">obliekania</label>
          </div>
        </div>

        <!-- Treatment -->
        <div class="flex flex-col gap-2">
          <label class="text-normal">Starostlivosti o</label>

          <div class="flex flex-wrap gap-4">
            <RadioButton v-model="deficiency.careType" inputId="care-chronic" value="chronic" />
            <label for="care-chronic">chron. liečby</label>

            <RadioButton v-model="deficiency.careType" inputId="care-inz" value="inz" />
            <label for="care-inz">aplik. INZ, s.c. inj.</label>

            <RadioButton v-model="deficiency.careType" inputId="care-oral" value="oral" />
            <label for="care-oral">pod. liekov per os</label>

            <RadioButton v-model="deficiency.careType" inputId="care-pain" value="pain" />
            <label for="care-pain">eliminácie bolesti</label>
          </div>
        </div>

        <!-- Wound care -->
        <div class="flex flex-wrap gap-4">
          <RadioButton v-model="deficiency.woundCare" inputId="wound" value="wound" />
          <label for="wound">starostlivosti o ranu</label>

          <RadioButton v-model="deficiency.woundCare" inputId="stoma" value="stoma" />
          <label for="stoma">stómiu</label>

          <RadioButton v-model="deficiency.woundCare" inputId="decubitus" value="decubitus" />
          <label for="decubitus">dekubit</label>

          <RadioButton v-model="deficiency.woundCare" inputId="secondary" value="secondary" />
          <label for="secondary">starostlivosti o ranu hojaciu sa per sekundam</label>
        </div>

        <!-- Care focus -->
        <div class="flex flex-wrap gap-4">
          <RadioButton v-model="deficiency.focus" inputId="throat" value="throat" />
          <label for="throat">hrádzu</label>

          <RadioButton v-model="deficiency.focus" inputId="breasts" value="breasts" />
          <label for="breasts">prsníky</label>

          <RadioButton v-model="deficiency.focus" inputId="lactation" value="lactation" />
          <label for="lactation">dojčenie</label>

          <RadioButton v-model="deficiency.focus" inputId="newborn" value="newborn" />
          <label for="newborn">novorodenca</label>
        </div>

        <!-- Other findings -->
        <div class="flex items-center gap-2">
          <label class="text-normal whitespace-nowrap">Iné zistenia:</label>
          <InputText v-model="deficiency.otherNotes" class="flex-1" />
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
          <div class="flex flex-col gap-2">
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