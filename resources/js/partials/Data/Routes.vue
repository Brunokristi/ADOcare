<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

interface BatchType {
  code: string;
  name: string;
}

const authStore = useAuthStore();
const router = useRouter();
const toast = useToast();

const batchType = ref<BatchType | null>(null);
const dates = ref<Date | null>(null);
const submitted = ref(false);
const loading = ref(false);


const batchTypes = ref<BatchType[]>([
  { code: 'CP', name: 'Cestovný príkaz' },
  { code: 'DZC', name: 'Denný záznam ciest' },
]);

async function onSubmit() {
    submitted.value = true;

    const hasPeriod = !!dates.value;

    if (
        !batchType.value ||
        !hasPeriod
    ) {
        return;
    }

    if (!dates.value) {
        return;
    }

    const monthDate = dates.value as Date;
    const year = monthDate.getFullYear();
    const month = monthDate.getMonth();
    const startDate = new Date(year, month, 1);
    const endDate = new Date(year, month + 1, 0);

    loading.value = true;

    const formatLocalDate = (date: Date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    if (batchType.value.code === 'CP') {
        try {
            const res = await api.post('/v1/cps', {
            start: formatLocalDate(startDate),
            end: formatLocalDate(endDate),
            branch_id: authStore.currentBranch?.id,
            });

            const documentId = res.data?.document_id;
            
            toast.add({
            severity: 'success',
            summary: 'Úspech',
            detail: 'Cestovný príkaz bol úspešne vytvorený',
            life: 3000,
            });

            await router.push({
            path: `/documents/cp/${documentId}`,
            });
        } catch (error) {
            toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Nepodarilo sa vytvoriť cestovný príkaz',
            life: 3000,
            });
            console.error('Preview or navigation failed', error);
        } finally {
            loading.value = false;
        }
    }
    
    else if (batchType.value.code === 'DZC') {
        try {
            const res = await api.post('/v1/dzcs', {
            start: formatLocalDate(startDate),
            end: formatLocalDate(endDate),
            branch_id: authStore.currentBranch?.id,
            });

            const documentId = res.data?.document_id;
            
            toast.add({
            severity: 'success',
            summary: 'Úspech',
            detail: 'Denný záznam ciest bol úspešne vytvorený',
            life: 3000,
            });

            await router.push({
            path: `/documents/dzc/${documentId}`,
            });
        } catch (error) {
            toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: 'Nepodarilo sa vytvoriť denný záznam ciest',
            life: 3000,
            });
            console.error('Preview or navigation failed', error);
        } finally {
            loading.value = false;
        }
    }
}
</script>


<template>
  <div class="flex flex-col gap-6">
    <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
      <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-12 gap-4">
          <!-- Typ -->
          <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Typ dávky</label>
            <Select
              v-model="batchType"
              :options="batchTypes"
              optionLabel="name"
              fluid
              class="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
            />
            <small v-if="submitted && !batchType" class="text-warning">
              Typ cestovného je povinný.
            </small>
          </div>

    
          <!-- Obdobie -->
          <div class="col-span-12 md:col-span-6">
            <label class="block text-normal mb-1">Obdobie</label>
            <DatePicker
              v-model="dates"
              view="month"
              dateFormat="MM yy"
              :manualInput="false"
              inputClass="w-full! border-none! shadow-none! bg-white! focus:ring-0! focus:shadow-none!"
              fluid
            />

            <small
              v-if="submitted && !dates"
              class="text-warning"
            >
              Obdobie je povinné.
            </small>
          </div>
        </div>
      </section>

      <div class="flex justify-end">
        <Button
          type="submit"
          class="relative flex justify-center items-center bg-accent! border-0! hover:bg-darkgrey! px-4 py-2 rounded-md text-white w-100"
        >
          Vygenerovať
          <i
            class="bi bi-arrow-right absolute right-2 bg-white px-2 rounded-md text-accent"
          />
        </Button>
      </div>
    </form>
  </div>
</template>
