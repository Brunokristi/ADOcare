import { defineStore } from 'pinia';

export interface Patient {
    id: number;
    firstname: string;
    lastname: string;
    personalnumber: string;
    address: string;
    city: string;
    doctor: string;
}

const STORAGE_KEY = 'selected-patient';

export const usePatientStore = defineStore('patient', {
    state: () => ({
        current: null as Patient | null,
    }),

    actions: {
        setPatient(patient: Patient) {
            this.current = patient;

            // For now: store the whole object.
            // Later, when you have backend, change to just { id: patient.id }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(patient));
        },

        loadFromStorage() {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return;

            try {
                const patient = JSON.parse(raw) as Patient;
                this.current = patient;

                // 🔁 Later:
                // const { id } = JSON.parse(raw);
                // fetch(`/patients/${id}`) ...
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
