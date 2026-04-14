<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import Button from 'primevue/button'
import Tabs from 'primevue/tabs'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'

import StepChecklist from './steps/Checklist.vue'
import StepBeforeInvoices from './steps/BeforeInvoices.vue'
import StepInvoices from './steps/Invoices.vue'
import StepBeforeDocuments from './steps/BeforeDocuments.vue'
import StepDocuments from './steps/Documents.vue'
import StepFinal from './steps/Final.vue'
import StepBeforeEmail from './steps/BeforeEmail.vue'
import StepEmail from './steps/Email.vue'


type StepKey =
    | 'checklist'
    | 'before-invoices'
    | 'invoices'
    | 'before-documents'
    | 'documents'
    | 'final'
    | 'before-email'
    | 'email'


type WizardState = {
    checklistDone: boolean
    invoicesDone: boolean
    documentsDone: boolean
}

type StepDefinition = {
    value: StepKey
    label: string
    component: any
    isCompleted: () => boolean
}

const activeTab = ref<StepKey>('checklist')

const wizardState = ref<WizardState>({
    checklistDone: false,
    invoicesDone: false,
    documentsDone: false,
})

const steps: StepDefinition[] = [
    {
        value: 'checklist',
        label: 'Príprava',
        component: markRaw(StepChecklist),
        isCompleted: () => wizardState.value.checklistDone,
    },
    {
        value: 'before-invoices',
        label: 'Pred faktúrami',
        component: markRaw(StepBeforeInvoices),
        isCompleted: () => true,
    },
    {
        value: 'invoices',
        label: 'Faktúry',
        component: markRaw(StepInvoices),
        isCompleted: () => wizardState.value.invoicesDone,
    },
    {
        value: 'before-documents',
        label: 'Pred dokumentmi',
        component: markRaw(StepBeforeDocuments),
        isCompleted: () => true,
    },
    {
        value: 'documents',
        label: 'Manžérske dokumenty',
        component: markRaw(StepDocuments),
        isCompleted: () => wizardState.value.documentsDone,
    },
    {
        value: 'before-email',
        label: 'Pred emailom',
        component: markRaw(StepBeforeEmail),
        isCompleted: () => true,
    },
    {
        value: 'email',
        label: 'Email',
        component: markRaw(StepEmail),
        isCompleted: () => wizardState.value.documentsDone,
    },

    {
        value: 'final',
        label: 'Dokončenie',
        component: markRaw(StepFinal),
        isCompleted: () => false,
    },
]

const currentStepIndex = computed(() =>
    steps.findIndex((step) => step.value === activeTab.value)
)

const canGoPrev = computed(() => currentStepIndex.value > 0)
const canGoNext = computed(() => currentStepIndex.value < steps.length - 1)

function nextStep() {
    if (!canGoNext.value) {
        return
    }

    const nextStepIndex = currentStepIndex.value + 1
    const nextStep = steps[nextStepIndex]
    if (nextStep) {
        activeTab.value = nextStep.value
    }
}

function prevStep() {
    if (!canGoPrev.value) {
        return
    }

    const prevStepIndex = currentStepIndex.value - 1
    const prevStepItem = steps[prevStepIndex]
    if (prevStepItem) {
        activeTab.value = prevStepItem.value
    }
}

function goToStep(step: StepKey) {
    activeTab.value = step
}

function updateState(payload: Partial<WizardState>) {
    wizardState.value = {
        ...wizardState.value,
        ...payload,
    }
}
</script>

<template>
    <div class="py-6 pb-24">
        <div class="card">
            <Tabs :value="activeTab">
                <TabList>
                    <Tab
                        v-for="step in steps"
                        :key="step.value"
                        :value="step.value"
                        @click="goToStep(step.value)"
                    >
                        <div class="flex items-center gap-2">
                            <span>{{ step.label }}</span>
                            <span
                                v-if="step.isCompleted()"
                                class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-accent text-white text-xs"
                            >
                                ✓
                            </span>
                        </div>
                    </Tab>
                </TabList>

                <TabPanels>
                    <TabPanel
                        v-for="step in steps"
                        :key="step.value"
                        :value="step.value"
                    >
                        <component
                            :is="step.component"
                            :wizard-state="wizardState"
                            @next="nextStep"
                            @prev="prevStep"
                            @update-state="updateState"
                        />
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>

        <div class="mt-6 flex justify-between">
            <Button
                type="button"
                icon="bi bi-arrow-left"
                outlined
                :disabled="!canGoPrev"
                @click="prevStep"
                class="bg-accent! border-0! hover:bg-darkgrey! text-white! text-normal! px-5!"

            />

            <Button
                v-if="canGoNext"
                type="button"
                icon="bi bi-arrow-right"
                class="bg-accent! border-0! hover:bg-darkgrey! text-white! text-normal! px-5!"
                @click="nextStep"
            />
        </div>
    </div>
</template>