import { defineStore } from 'pinia';
import type { Patient } from '@/types/models';

const STORAGE_KEY = 'selected-patient';

export const usePatientStore = defineStore('patient', {
    state: () => ({
        current: null as Patient | null,
    }),

    actions: {
        setPatient(patient: Patient) {
            this.current = patient;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(patient));
        },

        loadFromStorage() {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return;

            try {
                const patient = JSON.parse(raw) as Patient;
                this.current = patient;
            } catch (e) {
                console.error('Failed to parse stored patient', e);
                localStorage.removeItem(STORAGE_KEY);
            }
        },

        clear() {
            this.current = null;
            localStorage.removeItem(STORAGE_KEY);
        },
    },
});
