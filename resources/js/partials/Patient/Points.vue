<script setup lang="ts">
import { ref } from 'vue';
import Calendar from 'primevue/calendar';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';

type Option = {
  code: string;
  name: string;
};

const emit = defineEmits<{
  (e: 'submit', payload: {
    date: Date | null;
    diagnosis: Option | null;
    procedure: Option | null;
    referralDate: Date | null;
  }): void;
}>();

// date fields
const date = ref<Date | null>(null);
const referralDate = ref<Date | null>(null);

// diagnoses
const diagnosis = ref<Option | null>(null);
const diagnoses = ref<Option[]>([
  { code: 'I10', name: 'Hypertenzná choroba (I10)' },
  { code: 'E11', name: 'Diabetes mellitus 2. typu (E11)' },
  { code: 'J45', name: 'Astma (J45)' },
]);

const filteredDiagnoses = ref<Option[]>([]);

function searchDiagnoses(event: { query: string }) {
  const q = event.query.toLowerCase();
  filteredDiagnoses.value = diagnoses.value.filter(d =>
    d.code.toLowerCase().includes(q) ||
    d.name.toLowerCase().includes(q),
  );
}

// procedures
const procedure = ref<Option | null>(null);
const procedures = ref<Option[]>([
  { code: '213', name: 'Ošetrenie v domácom prostredí' },
  { code: '310', name: 'Kontrolné vyšetrenie' },
  { code: '411', name: 'Odber biologického materiálu' },
]);

const filteredProcedures = ref<Option[]>([]);

function searchProcedures(event: { query: string }) {
  const q = event.query.toLowerCase();
  filteredProcedures.value = procedures.value.filter(p =>
    p.code.toLowerCase().includes(q) ||
    p.name.toLowerCase().includes(q),
  );
}

const submitted = ref(false);

function onSubmit() {
  submitted.value = true;

  // You can add stronger validation here if you want
  emit('submit', {
    date: date.value,
    diagnosis: diagnosis.value,
    procedure: procedure.value,
    referralDate: referralDate.value,
  });
}
</script>

<template>
  <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
    <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
      <div class="grid grid-cols-12 gap-4">
        <!-- date picker -->
        <div class="col-span-3">
          <label class="block text-normal mb-1">Dátum</label>
          <Calendar
            v-model="date"
            dateFormat="dd.mm.yy"
            :showIcon="true"
            class="w-full"
          />
          <small v-if="submitted && !date" class="text-warning">
            Dátum je povinný.
          </small>
        </div>

        <!-- search and select from diagnoses -->
        <div class="col-span-3">
          <label class="block text-normal mb-1">Diagnóza</label>
          <AutoComplete
            v-model="diagnosis"
            :suggestions="filteredDiagnoses"
            field="name"
            :minLength="1"
            @complete="searchDiagnoses"
            class="w-full"
          />
          <small v-if="submitted && !diagnosis" class="text-warning">
            Diagnóza je povinná.
          </small>
        </div>

        <!-- search and select from procedures -->
        <div class="col-span-3">
          <label class="block text-normal mb-1">Výkon</label>
          <AutoComplete
            v-model="procedure"
            :suggestions="filteredProcedures"
            field="name"
            :minLength="1"
            @complete="searchProcedures"
            class="w-full"
          />
          <small v-if="submitted && !procedure" class="text-warning">
            Výkon je povinný.
          </small>
        </div>

        <!-- datepicker -->
        <div class="col-span-3">
          <label class="block text-normal mb-1">Dátum odporučenia</label>
          <Calendar
            v-model="referralDate"
            dateFormat="dd.mm.yy"
            :showIcon="true"
            class="w-full"
          />
          <small v-if="submitted && !referralDate" class="text-warning">
            Dátum odporučenia je povinný.
          </small>
        </div>
      </div>
    </section>

    <Button
      type="submit"
      class="relative w-100 flex justify-center items-center !bg-accent !border-0 hover:!bg-darkgrey"
    >
      Pridať
      <i class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent" />
    </Button>
  </form>
</template>
