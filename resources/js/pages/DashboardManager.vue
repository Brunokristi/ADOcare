<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { computed, ref } from 'vue';
import type { User } from '@/types/models';
import { useAuthStore } from '@/stores/auth';
import DatePicker from 'primevue/datepicker';

const authStore = useAuthStore();

const user = computed<User | null>(() => authStore.user as User | null);

const fullName = computed(() =>
  user.value
    ? `${user.value.first_name ?? ''}`.trim()
    : ''
);

// Set default to previous month
const dates = ref<Date>(new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1));
const submitted = ref(false);
</script>

<template>
    <div class="space-y-10 p-4">

        <!-- Greeting -->
        <div class="text-heading-accent">
            Dobrý deň {{ fullName }} 😊,<br />
            všetko je pre Vás pripravené.
        </div>

        <section class="bg-tag3 p-6 rounded-md flex flex-col gap-4">
        <div class="grid grid-cols-12 gap-4">

          <!-- Obdobie (month) -->
          <div class="col-span-12 md:col-span-3">
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

        
    </div>
</template>
