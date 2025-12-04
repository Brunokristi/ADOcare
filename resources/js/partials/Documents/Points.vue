<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

type CoverSheet = {
  batchNumber: number;
  fileName: string;
  amount: number;        
  periodFrom: string;    
  periodTo: string;
  performedBy: string;
  performedDate: string;
  companyName: string;
  branchName: string;
};

const route = useRoute();

const batchNumber = computed(() => Number(route.params.id ?? 3032));

const sheet = computed<CoverSheet>(() => ({
  batchNumber: batchNumber.value,
  fileName: `davka.${batchNumber.value}.txt`,
  amount: 1002.32,
  periodFrom: '2025-02-01',
  periodTo: '2025-02-28',
  performedBy: 'Erika Kaszová',
  performedDate: '2025-03-10',
  companyName: 'ADOS ADANED s.r.o.',
  branchName: 'Lučenec, Mierová 1A',
}));

const formattedAmount = computed(() =>
  sheet.value.amount.toLocaleString('sk-SK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + '€'
);

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('sk-SK');
}

// Placeholder handlers for buttons
function downloadFile() {
  // later: call your backend endpoint
  // e.g. window.open(`/api/batches/${sheet.value.batchNumber}/file`, '_blank');
  console.log('download txt for', sheet.value.batchNumber);
}

function printPage() {
  window.print();
}
</script>



<template>
  <div class="flex flex-col gap-4">
    <div class="bg-tag3 justify-between flex items-center p-6 rounded-md">
      <div>
        <i class="bi bi-file-earmark"></i>
        davka.3024.txt
      </div>

      <Button
        icon="bi bi-download"
        class="!bg-accent !border-accent"
        @click="downloadPdf"
      />
    </div>

    <Toolbar class="bg-transparent! border-0! p-0! py-3! shadow-none! flex items-center justify-between">
        <template #start class="text-heading-accent">
            Sprievodný list
        </template>
        <template #end>
                <div class="flex items-center gap-2 ">
                    <Button 
                        icon="bi bi-download" 
                        class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey!"
                    />

                    <Button
                        icon="bi bi-printer"
                        class="bg-accent! border-accent! hover:bg-darkgrey! hover:border-darkgrey!"
                    />
                </div>
            </template>
        </Toolbar>

        <!-- “Paper” preview -->
    <div
      class="bg-white rounded-md shadow-xl/30 p-8 flex-1 flex justify-center items-start overflow-auto"
    >
      <div
        id="cover-sheet"
        class="w-full max-w-[800px] min-h-[900px] p-8"
      >
        <!-- Title -->
        <div class="text-center font-bold text-lg mb-4">
          SPRIEVODNÝ LIST
        </div>

        <!-- Top row: file name on the right -->
        <table class="w-full border-collapse text-sm mb-2">
          <tbody>
            <tr>
              <td class="border border-black p-2 align-top w-1/2"></td>
              <td class="border border-black p-2 align-top w-1/2 text-right">
                Sprievodný list k:
                <strong>{{ sheet.fileName }}</strong>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Main info table -->
        <table class="w-full border-collapse text-sm">
          <tbody>
            <tr>
              <td class="border border-black p-2 align-top w-1/2">
                <strong>Vykázaná suma:</strong><br />
                {{ formattedAmount }}
              </td>
              <td class="border border-black p-2 align-top w-1/2">
                <strong>Obdobie:</strong><br />
                {{ formatDate(sheet.periodFrom) }} - {{ formatDate(sheet.periodTo) }}
              </td>
            </tr>

            <tr>
              <td class="border border-black p-2 align-top">
                <strong>Vykázal:</strong><br />
                {{ sheet.performedBy }}
              </td>
              <td class="border border-black p-2 align-top">
                <strong>Vykázané dňa:</strong><br />
                {{ formatDate(sheet.performedDate) }}
              </td>
            </tr>

            <tr>
              <td class="border border-black p-2 align-top">
                <strong>Spoločnosť:</strong><br />
                {{ sheet.companyName }}
              </td>
              <td class="border border-black p-2 align-top">
                <strong>Prevádzka:</strong><br />
                {{ sheet.branchName }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
