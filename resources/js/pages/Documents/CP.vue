<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';
import LoadingOverlay from '@/components/LoadingOverlay.vue';

interface CPData {
    company_name: string;
    user_id: number;
    ico: string;
    city: string;
    user_name: string;
    start_date: string;
    end_date: string;
    month: string;
    year: string;
    car_model: string;
    car_license_plate: string;
    representative_name: string;
    lastday_previous_month: string;
}

const route = useRoute();
const loading = ref(false);

const cpData = ref<CPData>({
    company_name: '',
    user_id: 0,
    ico: '',
    city: '',
    user_name: '',
    start_date: '',
    end_date: '',
    month: '',
    year: '',
    car_model: '',
    car_license_plate: '',
    representative_name: '',
    lastday_previous_month: '',
});

onMounted(async () => {
  await loadCP(String(route.params.documentId));
});

async function loadCP(documentId: string) {
  loading.value = true;

  try {
    const res = await api.get(`/v1/cps/${documentId}`);
    const cp = res.data?.cp_data ?? {};

    cpData.value = {
        company_name: cp.company_name ?? '',
        ico: cp.ico ?? '',
        user_id: cp.user_id ?? '',
        city: cp.city ?? '',
        user_name: cp.user_name ?? '',
        start_date: cp.start_date ?? '',
        end_date: cp.end_date ?? '',
        month: cp.month ?? '',
        year: cp.year ?? '',
        car_model: cp.car_model ?? '',
        car_license_plate: cp.car_license_plate ?? '',
        representative_name: cp.representative_name ?? '',
        lastday_previous_month: cp.lastday_previous_month ?? '',
    };
  } catch (error) {
    console.error('Failed to load agreement:', error);
  } finally {
    loading.value = false;
  }
}

function formatDate(v?: string) {
  if (!v) return '';
  return new Date(v).toLocaleDateString('sk-SK');
}

function formatUserId(id?: number) {
  if (!id) return '';
  return String(id).padStart(3, '0');
}

function printPage() {
  requestAnimationFrame(() => window.print());
}
</script>

<template>
  <LoadingOverlay :show="loading" text="" />
  
  <div class="flex flex-col gap-4">
    <!-- Toolbar -->
    <Toolbar
      class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between no-print"
    >
      <template #start>
        <span class="text-heading-accent">
          Cestovný príkaz
        </span>
      </template>

      <template #end>
        <Button
          icon="bi bi-printer"
          class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey! h-7!"
          @click="printPage"
        />
      </template>
    </Toolbar>


    <div v-if="!loading" class="agreement-sheet-wrapper">
      <div id="agreement-sheet">
        <!-- TITLE -->
        <div class="text-center font-bold text-lg mb-4">
          CESTOVNÝ PRÍKAZ
        </div>

        <!-- DATE RANGE -->
        <table class="w-full border-collapse text-sm mb-2">
          <tbody>
            <tr>
              <td class="border border-black p-2 w-full" colspan="2">
                Cestovný príkaz:
                <strong>{{ `${formatUserId(cpData.user_id)}${cpData.month}${cpData.year}` }}</strong>
              </td>
            </tr>
            <tr>
                <td class="border border-black p-2 w-1/2">
                    <strong>Zamestnávateľ:</strong><br />
                    Názov: {{ cpData.company_name }} <br />
                    IČO: {{ cpData.ico }}
                </td>
                <td class="border border-black p-2 w-1/2">
                    <strong>Zamestnanec:</strong><br />
                    Meno: {{ cpData.user_name }}<br />
                    Funkcia: Terénna zdravotná sestra
                </td>
            </tr>
            <tr>
              <td class="border border-black p-2 w-full" colspan="2">
                <strong>Účel pracovných ciest:</strong><br />
                Zdravotná starostlivost o pacientov v domácom prostredí
              </td>
            </tr>
             <tr>
              <td class="border border-black p-2 w-full" colspan="2">
                <strong>Miesto výkonu práce: </strong><br />
                {{ cpData.city }} a okolie
              </td>
            </tr>
            <tr>
                <td class="border border-black p-2 w-1/2">
                    <strong>Obdobie platnosti:</strong><br />
                    od {{ formatDate(cpData.start_date) }} <br />
                    do {{ formatDate(cpData.end_date) }}
                </td>
                <td class="border border-black p-2 w-1/2">
                    <strong>Dopravný prostriedok:</strong><br />
                    {{ cpData.car_model }}<br />
                    ŠPZ: {{ cpData.car_license_plate }}
                </td>
            </tr>
            <tr>
              <td class="border border-black p-2 w-full" colspan="2">
                <strong>Predpokladané náklady:</strong><br />
                Podľa skutočného výkonu
              </td>
            </tr>
          </tbody>
        </table>

        <table class="w-full border-collapse text-sm mb-2">
          <tbody>
            <tr>
              <td class="border border-black p-2 w-1/2">
                Dátum:<br />
                <strong>{{ formatDate(cpData.lastday_previous_month) }}</strong>
              </td>
              <td class="border border-black p-2 w-1/2">
                Schválil:<br />
                <strong>{{ cpData.representative_name }}</strong>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="mt-16 grid grid-cols-2 gap-12 text-sm">
          <div class="text-center">
          </div>
          <div class="text-center">
            <div class="border-t border-black mb-2"></div>
            podpis schválujúceho
          </div>
        </div>
      </div>
    </div>
  </div>
</template>


<style scoped>
#agreement-sheet {
  width: 210mm;
  height: 297mm;
  margin: 0 auto;
  background: white;
  box-sizing: border-box;
  padding: 14mm;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.agreement-sheet-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

@page {
  size: A4;
  margin: 0;
}

@media print {
  body { margin: 0; padding: 0; }
  body * { visibility: hidden !important; }

  #agreement-sheet,
  #agreement-sheet * {
    visibility: visible !important;
  }

  #agreement-sheet {
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    margin: 0 auto;
    box-shadow: none;
  }

  .no-print,
  .p-toolbar {
    display: none !important;
  }
}

</style>