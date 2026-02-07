/**
 * Comprehensive translation dictionary for form option values.
 * Extracted from PatientRecordPage form specification.
 * Maps field value codes to their human-readable Slovak labels.
 */

export const valueTranslations: Record<string, Record<string, string>> = {
    // =====================
    // ALLERGIES
    // =====================
    allergies: {
        medicines: 'lieky',
        food: 'potraviny',
        disinfectants: 'dezinfekčné prípravky',
        plaster: 'leukoplast',
        bite: 'uštipnutie',
    },

    // =====================
    // ABUSES
    // =====================
    abuses: {
        caffeine: 'kofeín',
        nicotine: 'nikotín',
        alcohol: 'alkohol',
        medicines: 'lieky',
        drugs: 'drogy',
    },

    // =====================
    // FAMILY ANAMNESIS
    // =====================
    familyAnamnesis: {
        im: 'IM',
        dm: 'DM',
        ichs: 'ICHS',
        tbc: 'TBC',
        ca: 'CA',
    },

    // =====================
    // SOCIAL CONDITIONS
    // =====================
    socialConditions: {
        alone: 'žije sám (a)',
        with_family: 's rodinou',
        zss: 'v zar. soc. služieb (ZSS)',
    },

    // =====================
    // SOCIAL STATUS
    // =====================
    socialStatus: {
        unemployed: 'nezamestnaný',
        employed: 'zamestnaný',
        retired: 'dôchodca',
        disabled_retired: 'invalidný dôchodca',
        md: 'MD',
    },

    // =====================
    // SOCIAL CONTACTS
    // =====================
    socialContacts: {
        children: 'deti',
        relatives: 'príbuzní',
    },

    // =====================
    // SUPPORT SYSTEMS
    // =====================
    supportSystems: {
        friends: 'priatelia',
        neighbors: 'susedia',
        self_help_groups: 'svojpomocné skupiny',
        care_service: 'opatrovateľská služba',
    },

    // =====================
    // SOCIAL CULTURE
    // =====================
    socialCulture: {
        prefersSolitude: 'uprednostňuje samotu',
        company: 'spoločnosť',
    },

    // =====================
    // SOCIAL MEDIA
    // =====================
    socialMedia: {
        tv: 'TV',
        radio: 'rádio',
        newspapers: 'dennú tlač',
    },

    // =====================
    // NURSING CARE RECOMMENDED BY
    // =====================
    'nursing.caredRecommendedBy': {
        general_practitioner: 'všeobecný lekár',
        lspp_doctor: 'lekár LSPP',
        emergency_medical_service: 'ZZS',
    },

    // =====================
    // CONSCIOUSNESS
    // =====================
    consciousness: {
        conscious: 'pri vedomí',
        somnolence: 'somnolencia',
        semicoma: 'semikóma',
        coma: 'kóma',
    },

    // =====================
    // ORIENTATION
    // =====================
    orientation: {
        oriented: 'orientovaný',
        disoriented: 'dezorientovaný',
    },

    // =====================
    // CIRCULATION PROBLEM EXISTS
    // =====================
    'circulation.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // HYPOTENSION / HYPERTENSION
    // =====================
    hypotensionHypertension: {
        hypotension: 'hypotenzia',
        hypertension: 'hypertenzia',
    },

    // =====================
    // IRREGULAR PULSE
    // =====================
    irregularPulse: {
        irregularPulse: 'pulz nepravidelný/ slabo hmatný/ nitkovitý',
        cardiacPacemaker: 'kardiostimulátor',
    },

    // =====================
    // BREATHING PROBLEM EXISTS
    // =====================
    'breathing.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // IRREGULARITIES (BREATHING)
    // =====================
    irregularities: {
        irregular: 'nepravidelné',
        fastBreathing: 'rýchle',
        slowBreathing: 'pomalé',
        difficult: 'sťažené',
        shallow: 'plytké',
        deepened: 'prehĺbené',
        apneicPauses: 'apnoické pauzy',
        stridor: 'stridor',
        dyspneaAtRest: 'dýchavica v kľude',
        cough: 'kašeľ produktívny/neproduktívny',
        tracheostomy: 'tracheostómia',
    },

    // =====================
    // SUCTIONING, OXYGEN THERAPY, etc
    // =====================
    suctioning: {
        yes: 'áno',
        no: 'nie',
    },

    oxygenTherapy: {
        yes: 'áno',
        no: 'nie',
    },

    mechanicalVentilation: {
        yes: 'áno',
        no: 'nie',
    },

    inhalation: {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // NUTRITION WEIGHT TREND
    // =====================
    'nutrition.weightTrend': {
        increase: 'prírastok',
        decrease: 'úbytok',
    },

    // =====================
    // NUTRITION PROBLEM EXISTS
    // =====================
    'nutrition.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // NUTRITION SYMPTOMS
    // =====================
    'nutrition.symptoms': {
        obesity: 'obezita',
        cachexia: 'kachexia',
        appetiteLoss: 'nechutenstvo',
        nausea: 'nauzea',
        vomiting: 'zvracanie',
        swallowingDifficulties: 'porucha prehĺtania',
        heartburn: 'pálenie záhy',
    },

    // =====================
    // NUTRITION FEEDING TYPE
    // =====================
    'nutrition.feedingType': {
        enteral: 'enterálne',
        nutritional: 'nutričné',
    },

    // =====================
    // NUTRITION APPETITE
    // =====================
    'nutrition.appetite': {
        average: 'priemerná',
        limited: 'obmedzená',
    },

    // =====================
    // NUTRITION INTAKE
    // =====================
    'nutrition.intake': {
        alone: 'sám',
        withHelp: 's pomocou',
        tube: 'nazog. sonda',
    },

    // =====================
    // NUTRITION GASTROSTOMY / PEG / CVK / PERIPHERAL IV
    // =====================
    'nutrition.gastrostomy': {
        yes: 'áno',
    },

    'nutrition.peg': {
        yes: 'áno',
    },

    'nutrition.cvk': {
        yes: 'áno',
    },

    'nutrition.peripheralIVAccess': {
        yes: 'áno',
    },

    // =====================
    // NUTRITION ROUTE
    // =====================
    'nutrition.nutritionRoute': {
        enteral: 'enterálne',
        parenteral: 'parenterálne',
        sipping: 'sipping',
    },

    // =====================
    // NUTRITION DENTURE
    // =====================
    'nutrition.denture': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // DEFECATION PROBLEM EXISTS
    // =====================
    'defecation.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // DEFECATION SYMPTOMS
    // =====================
    'defecation.symptoms': {
        irregular: 'nepravidelná',
        diarrhea: 'hnačka',
        constipation: 'zápcha',
        withAdmixtures: 's prímesami',
        incontinence: 'inkontinencia',
        hemorrhoids: 'hemoroidy',
    },

    // =====================
    // DEFECATION STOMA CARE
    // =====================
    'defecation.stomaCare': {
        stoma: 'stómia',
    },

    // =====================
    // DEFECATION STOMA ASSISTANCE
    // =====================
    'defecation.stomaAssistanceNeeded': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // DEFECATION REGULATION USED
    // =====================
    'defecation.regulationUsed': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // DEFECATION REGULATION METHODS
    // =====================
    'defecation.regulationMethods': {
        tea: 'čaj',
        suppository: 'čípek',
        enema: 'klyzma',
    },

    // =====================
    // URINATION PROBLEM EXISTS
    // =====================
    'urination.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // URINATION SYMPTOMS
    // =====================
    'urination.symptoms': {
        dysuria: 'dyzúria',
        retention: 'retencia',
        incontinence: 'inkontinencia',
        absorbentAids: 'absorpčné pomôcky',
    },

    // =====================
    // URINATION CATHETER
    // =====================
    'urination.catheter': {
        pkInserted: 'zavedený',
    },

    // =====================
    // URINATION UROSTOMY / DIALYSIS / CONDOM SYSTEM
    // =====================
    'urination.urostomy': {
        yes: 'áno',
    },

    'urination.dialysis': {
        peritoneal: 'peritoneálna',
        hemodialysis: 'hemodialýza',
    },

    'urination.condomSystem': {
        yes: 'áno',
    },

    // =====================
    // SLEEP PROBLEM EXISTS
    // =====================
    'sleep.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // SLEEP FINDINGS
    // =====================
    'sleep.findings': {
        insomnia: 'nespavosť',
        nightAwakening: 'nočné budenie',
        pharmacotherapy: 'farmakoterapia',
    },

    // =====================
    // MOBILITY LEVEL
    // =====================
    'mobility.level': {
        full: '1 – plná mobilita',
        mildly_limited: '2 – mobilita mierne obmedzená',
        severely_limited: '3 – mobilita veľmi obmedzená',
        immobile: '4 – imobilita',
    },

    // =====================
    // MOBILITY COMPENSATORY AIDS
    // =====================
    'mobility.compensatoryAids': {
        usesAids: 'používa',
    },

    // =====================
    // MOVEMENT PROBLEM EXISTS
    // =====================
    'movement.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // MOVEMENT FINDINGS
    // =====================
    'movement.findings': {
        deformity: 'deformácia',
        limbNumbness: 'trpnutie končatín',
        fracture: 'zlomenina',
        paralysis: 'ochrnutie',
        amputation: 'amputácia',
    },

    // =====================
    // SKIN PROBLEM EXISTS
    // =====================
    'skin.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // SKIN TEMPERATURE
    // =====================
    'skin.temperature': {
        warm: 'teplá',
        cold: 'studená',
    },

    // =====================
    // SKIN MOISTURE
    // =====================
    'skin.moisture': {
        dry: 'suchá',
        sweaty: 'spotená',
    },

    // =====================
    // SKIN COLOR
    // =====================
    'skin.color': {
        pink: 'ružová',
        pale: 'bledá',
        icteric: 'ikterická',
        cyanotic: 'cyanotická',
    },

    // =====================
    // SKIN TURGOR
    // =====================
    'skin.turgor': {
        normal: 'primeraný',
        decreased: 'znížený',
    },

    // =====================
    // SKIN INTEGRITY
    // =====================
    'skin.integrity': {
        intact: 'nenarušená',
        impaired: 'narušená',
    },

    // =====================
    // SKIN CHANGES
    // =====================
    'skin.changes': {
        rash: 'kožné vyrážky',
        itching: 'svrbenie',
        peeling: 'olupovanie',
        chafing: 'zaparenia',
        bruises: 'modriny',
        inflammation: 'zápal',
        superficialInjury: 'povrchové poranenie',
        openWound: 'otvorená rana',
        surgicalWound: 'operačná rana',
        abdominal: 'abdominálna',
        vaginal: 'vaginálna',
        bleeding: 'krvácanie',
        ulcusCruris: 'ulcus cruris',
        gangrene: 'gangréna',
        pressureUlcers: 'dekubity',
    },

    // =====================
    // EDEMA PROBLEM EXISTS
    // =====================
    'edema.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // EDEMA TYPE
    // =====================
    'edema.type': {
        local: 'miestne',
        general: 'celkové',
    },

    // =====================
    // EDEMA MEASURES
    // =====================
    'edema.measures': {
        lowerLimbBandage: 'bandáž DK',
        antithromboticStockings: 'antitrombotické pančuchy',
        vascularExercises: 'cievna gymnastika',
    },

    // =====================
    // MUCOSA PROBLEM EXISTS
    // =====================
    'mucosa.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // MUCOSA FINDINGS
    // =====================
    'mucosa.findings': {
        notCongested: 'neprekrvené',
        bleeding: 'krvácanie',
        infection: 'infekcia',
        oralMucosaChanges: 'zmeny na sliznici dutiny ústnej',
    },

    // =====================
    // HYGIENE STATUS ON ADMISSION
    // =====================
    'hygiene.statusOnAdmission': {
        adequate: 'primeraný',
        neglected: 'zanedbaný',
    },

    // =====================
    // HYGIENE SELF CARE
    // =====================
    'hygiene.selfCare': {
        independent: 'samostatne',
        withHelp: 's pomocou',
        fullyDependent: 'je úplne závislý (á)',
    },

    // =====================
    // POSTPARTUM PARITY
    // =====================
    'postpartum.parity': {
        primipara: 'prvorodička',
        secundipara: 'druhorodička',
        multipara: 'viacrodička',
    },

    // =====================
    // POSTPARTUM DELIVERY TYPE
    // =====================
    'postpartum.deliveryType': {
        spontaneous: 'spontánny',
        operative: 'operatívny',
    },

    // =====================
    // POSTPARTUM COMPLICATIONS
    // =====================
    'postpartum.complications': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // POSTPARTUM WOUND HEALING
    // =====================
    'postpartum.woundHealing': {
        perPrimam: 'per primam',
        perSekundam: 'per sekundam',
    },

    // =====================
    // POSTPARTUM BREASTS
    // =====================
    'postpartum.breasts': {
        soft: 'voľné',
        redness: 'začervenanie',
        nippleCracks: 'trhlinky bradaviek',
        painful: 'bolestivosť',
        milkRetention: 'retencia mlieka',
    },

    // =====================
    // POSTPARTUM LACTATION
    // =====================
    'postpartum.lactation': {
        yes: 'áno',
        partial: 'čiastočne',
        no: 'nie',
    },

    // =====================
    // POSTPARTUM NEWBORN SEX
    // =====================
    'postpartum.newbornSex': {
        male: 'chlapec',
        female: 'dievča',
    },

    // =====================
    // PAIN PROBLEM EXISTS
    // =====================
    'pain.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // PAIN TYPE
    // =====================
    'pain.type': {
        acute: 'akútna',
        chronic: 'chronická',
    },

    // =====================
    // COMMUNICATION TYPE
    // =====================
    'communication.type': {
        verbal: 'verbálna',
        nonverbal: 'neverbálna',
    },

    // =====================
    // COMMUNICATION PROBLEM EXISTS
    // =====================
    'communication.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // COMMUNICATION ISSUES
    // =====================
    'communication.issues': {
        speechDisorders: 'poruchy reči',
        impossible: 'nemožná',
    },

    // =====================
    // LEARNING PROBLEM EXISTS
    // =====================
    'learning.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // LEARNING SENSORY CHANGES
    // =====================
    'learning.sensoryChanges': {
        vision: 'zrak',
        hearing: 'sluch',
        speech: 'reč',
    },

    // =====================
    // LEARNING SENSORY CHANGES EXIST
    // =====================
    'learning.sensoryChangesExist': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // LEARNING COMPENSATORY AIDS
    // =====================
    'learning.compensatoryAids': {
        glasses: 'okuliare',
        contactLenses: 'šošovky',
        hearingAid: 'načúvací aparát',
    },

    // =====================
    // LEARNING DISEASE KNOWLEDGE
    // =====================
    'learning.diseaseKnowledge': {
        sufficient: 'dostatok',
        insufficient: 'nedostatok',
    },

    // =====================
    // LEARNING EDUCATION TOPICS
    // =====================
    'learning.educationTopics': {
        homeCare: 'o ošetrovateľskej starostlivosti v domácom prostredí',
        postoperativeCare: 'o ošetrovateľskej starostlivosti v pooperačnom období',
        postpartumCare: 'o ošetrovateľskej starostlivosti v šestonedelí',
        palliativeCare: 'o paliatívnej starostlivosti',
        postChemotherapyCare: 'o ošetrovateľskej starostlivosti po chemoterapii',
    },

    // =====================
    // PSYCHOLOGICAL MOOD
    // =====================
    'psychological.mood': {
        adequate: 'primeraná',
        apathy: 'apatia',
        depression: 'depresia',
        euphoria: 'eufória',
        aggression: 'agresia',
    },

    // =====================
    // PSYCHOLOGICAL FEELINGS
    // =====================
    'psychological.feelings': {
        calm: 'kľudný',
        balanced: 'vyrovnaný',
        fear: 'strach',
        sadness: 'smútok',
        anxiety: 'úzkosť',
        anger: 'hnev',
        depression: 'depresia',
        hopelessness: 'beznádej',
        helplessness: 'bezmocnosť',
        confusion: 'zmätenosť',
        selfBlame: 'sebaobviňovanie',
        selfPity: 'sebaľútovanie',
    },

    // =====================
    // PSYCHOLOGICAL PROBLEM EXISTS
    // =====================
    'psychological.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // SOCIAL PROBLEM EXISTS
    // =====================
    'social.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // SOCIAL SUPPORT DEPENDENCY
    // =====================
    'social.supportDependency': {
        dependent: 'je odkázaný (á) na sociálnu pomoc',
        independent: 'nie odkázaný (á) na sociálnu pomoc',
    },

    // =====================
    // SPIRITUAL PROBLEM EXISTS
    // =====================
    'spiritual.problemExists': {
        yes: 'áno',
        no: 'nie',
    },

    // =====================
    // CARE DEFICITS AREAS
    // =====================
    'deficits.areas': {
        nutrition: 'výživy',
        elimination: 'vyprázdňovania',
        hygiene: 'hygieny',
        dressing: 'obliekania',
    },

    // =====================
    // CARE DEFICITS TREATMENTS
    // =====================
    'deficits.treatments': {
        chronicTreatment: 'chron. liečby',
        insulinSubcutaneous: 'aplik. INZ, s.c. inj.',
        oralMedication: 'pod. liekov per os',
        painElimination: 'eliminácie bolesti',
    },

    // =====================
    // CARE DEFICITS NURSING CARE
    // =====================
    'deficits.nursingCare': {
        woundCare: 'ranu',
        stomaCare: 'stómiu',
        pressureUlcerCare: 'dekubit',
        secondaryHealingWound: 'ranu hojaciu sa per sekundam',
        perineumCare: 'hrádzu',
        breastCare: 'prsníky',
        breastfeedingSupport: 'dojčenie',
        newbornCare: 'novorodenca',
    },

    // =====================
    // PATIENT INSTRUCTION TOPICS
    // =====================
    'instruction.topics': {
        rightsAndDuties: 'právach a povinnostiach hospitalizovaných pacientov',
        valuablesStorage: 'úschove peňazí/cenností',
        houseRules: 'domácom poriadku',
        prohibitions: 'zákaze fajčenia, užívania alkoholu, drog',
    },
}

/**
 * Get translated label for a field value.
 * @param fieldId - The field identifier (e.g., 'allergies', 'consciousness')
 * @param value - The value code to translate
 * @returns The translated Slovak label, or the original value if not found
 */
export function getValueLabel(fieldId: string, value: string): string {
    const fieldTranslations = valueTranslations[fieldId]
    if (fieldTranslations && fieldTranslations[value]) {
        return fieldTranslations[value]
    }
    return value
}

/**
 * Get translated labels for multiple values (for checkbox groups).
 * @param fieldId - The field identifier
 * @param values - Array of value codes to translate
 * @returns Array of translated Slovak labels
 */
export function getValueLabels(fieldId: string, values: string[]): string[] {
    return values.map(v => getValueLabel(fieldId, v))
}
