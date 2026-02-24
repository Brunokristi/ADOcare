<script setup lang="ts">
import { ref } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import api from '@/services/api';
import { useToast } from 'primevue/usetoast';

interface Props {
  visible: boolean;
  documentId: number | null;
  documentUrl?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  'update:visible': [value: boolean];
  'close': [];
  'deleted': [];
}>();

const toast = useToast();
const isDeleting = ref(false);

function closeDialog() {
  emit('update:visible', false);
  emit('close');
}

function viewDocument() {
  if (props.documentId) {
    if (props.documentUrl) {
      window.open(props.documentUrl.replace('{id}', props.documentId.toString()), '_blank');
    }
  }
}

async function deleteDocument() {
  if (!props.documentId) return;

  isDeleting.value = true;
  try {
    await api.delete(`/v1/documents/${props.documentId}`);
    toast.add({
      severity: 'success',
      summary: 'Úspešne vymazané',
      detail: 'Dokument bol úspešne vymazaný.',
      life: 3000,
    });
    emit('update:visible', false);
    emit('deleted');
  } catch (err: any) {
    console.error('Failed to delete document:', err);
    const message =
      err?.response?.data?.message || 'Nepodarilo sa vymazať dokument.';
    toast.add({
      severity: 'error',
      summary: 'Chyba',
      detail: message,
      life: 5000,
    });
  } finally {
    isDeleting.value = false;
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="emit('update:visible', $event)"
    :style="{ width: '600px' }"
    :modal="true"
    :closable="true"
    :header="'Upozornenie'"
  >

    <div class="flex items-center justify-between w-full">
        <span class="text-heading">
          Dokument pre dané obdobie už existuje.
        </span>

        <div class="flex items-center gap-2">
          <Button
            label="Zobrazit"
            text
            @click="viewDocument"
            class="!bg-accent !px-4 !text-white hover:!bg-darkgrey !border-0"
          />
          <Button
            label="Vymazať"
            text
            @click="deleteDocument"
            class="!bg-warning !px-4 !text-white"
          />
        </div>
    </div>

    <div class="w-full flex justify-end mt-4">
        <Button label="Pokračovať v tvorení dokumentu" text @click="closeDialog" class="text-accent! px-2!" />
    </div>
  </Dialog>
</template>
