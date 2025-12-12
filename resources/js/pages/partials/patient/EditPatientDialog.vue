<script setup lang="ts">


import { computed, onMounted, ref } from 'vue';
import { usePatientStore } from '@/stores/patientStore';
import { formatBirthNumber } from '@/utils/formatUtils';
import { type InsuranceCompany, type Doctor, type Patient } from '@/types/models';
import api from '@/services/api';
import useAuthStore from '@/stores/auth';

const patientStore = usePatientStore();
const props = defineProps<{
    patient: Patient;
    visible: boolean;
}>();
const emit = defineEmits<{
    (e: 'update:patient', patient: Patient): void;
}>();
const submitted = ref(false);
const sexOptions = [
    { label: 'Muž', value: 'male' },
    { label: 'Žena', value: 'female' },
];


const patientDoctor = ref<Doctor>();
const patientInsuranceCompany = ref<InsuranceCompany>();

const doctorOptions = ref<{ id: number; name: string }[]>([]);
const insuranceOptions = ref<{ code: string; name: string }[]>([]);


const currentBranchId = useAuthStore().currentBranch?.id || 0;

onMounted(async () => {
    if (!currentBranchId) return;
    // Fetch doctor options from API
    const doctors = await api.fetchEntities<Doctor>(`v1/branches/${currentBranchId}/doctors`, { all: true });
    const insuranceCompanies = await api.fetchEntities<InsuranceCompany>(`v1/insurance-companies`, { all: true });

    doctorOptions.value = doctors.map(doc => ({ id: doc.id, name: `${doc.first_name} ${doc.last_name}` }));
    insuranceOptions.value = insuranceCompanies.map(ic => ({ code: ic.code ?? '<Chýbajúci kód>', name: ic.name ?? '<Neznáma poisťovňa>' }));


});

computed(async () => {

    if (!props.patient.id) return;

    const fetchedPatient = await api.fetchEntity<Patient>(`v1/patients/${props.patient.id}`);

    patientDoctor.value = fetchedPatient.doctor;
    patientInsuranceCompany.value = fetchedPatient.insurance_company;
});

const savePatient = () => {
    submitted.value = true;

    // basic validation
    if (!props.patient.first_name || !props.patient.last_name) {
        return;
    }


    // use api to save patient
    patientStore.savePatient(props.patient).then((savedPatient) => {
        emit('update:patient', props.patient);
    });

};

</script>


<template>
    <Dialog :visible="visible" :style="{ width: '90%' }" header="Pacient" :modal="true">

        <div class="flex flex-col gap-6">

            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <label class="block text-normal text-accent">Osobné údaje</label>
                </div>
                <div class="col-span-4">
                    <label class="block text-normal mb-1">Meno</label>
                    <InputText v-model.trim="patient.first_name" fluid :invalid="submitted && !patient.first_name" />
                    <small v-if="submitted && !patient.first_name" class="text-warning">
                        Meno je povinné.
                    </small>
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Priezvisko</label>
                    <InputText v-model.trim="patient.last_name" fluid :invalid="submitted && !patient.last_name" />
                    <small v-if="submitted && !patient.last_name" class="text-warning">
                        Priezvisko je povinné.
                    </small>
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Titul</label>
                    <InputText v-model.trim="patient.title" fluid />
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Rodné číslo</label>
                    <InputText v-model.trim="patient.personal_number" maxlength="11" fluid />
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Pohlavie</label>
                    <Dropdown v-model="patient.sex" :options="sexOptions" optionLabel="label" optionValue="value"
                        fluid />
                </div>

                <div class="col-span-4">
                    <label class="block text-normal mb-1">Kontakt</label>
                    <InputText v-model.trim="patient.contact" fluid />
                </div>
            </div>





            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <label class="block text-normal text-accent">Zdravotné detaily</label>
                </div>
                <div class="col-span-6">
                    <label class="block text-normal mb-1">Lekár</label>
                    <Dropdown v-model="patient.doctor_id" :options="doctorOptions" optionLabel="name" optionValue="id"
                        fluid filter>
                        <template #footer>
                            <div class="p-2">
                                <Button label="Pridať" fluid variant="text" size="small" icon="bi bi-plus"
                                    class="text-accent! bg-tag3! hover:bg-accent! hover:text-white!" />
                            </div>
                        </template>
                    </Dropdown>

                </div>

                <div class="col-span-6">
                    <label class="block text-normal mb-1">Poisťovňa</label>
                    <Dropdown v-model="patient.insurance_company" :options="insuranceOptions" optionLabel="name"
                        optionValue="code" fluid />
                </div>
            </div>





            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <label class="block text-normal text-accent">Adresa</label>
                </div>
                <div class="col-span-4 flex flex-col gap-4">
                    <div>
                        <label class="block text-normal mb-1">Ulica</label>
                        <InputText v-model.trim="patient.address" fluid />
                    </div>

                    <div>
                        <label class="block text-normal mb-1">Mesto</label>
                        <InputText v-model.trim="patient.city" fluid />
                    </div>

                    <div>
                        <label class="block text-normal mb-1">PSČ</label>
                        <InputText v-model.trim="patient.zip" fluid />
                    </div>
                </div>

                <div class="col-span-8">
                    <div id="patient-map" class="w-full h-full rounded-md overflow-hidden"></div>
                </div>
            </div>

        </div>
        <template #footer>
            <Button label="Uložiť" class="bg-accent! px-md! text-white! hover:bg-darkgrey! border-0!"
                @click="savePatient" />
        </template>
    </Dialog>
</template>
