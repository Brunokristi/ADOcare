import { defineStore } from 'pinia';
import type { Doctor, InsuranceCompany, Patient } from '@/types/models';
import api from '@/services/api';

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

        fetchPatient(patientId: number) {
            return api.get(`/v1/patients/${patientId}`)
                .then((response) => {
                    const patient = response.data.data as Patient;
                    this.setPatient(patient);
                    return patient;
                })
                .catch((error) => {
                    throw new Error('Failed to fetch patient: ' + error);
                });
        },

        async savePatient(patient: Patient) {
            try {
                await api.put(`/v1/patients/${patient.id}`, patient);
                const fresh = await this.fetchPatient(patient.id);
                return fresh;
            } catch (error) {
                throw new Error('Failed to save patient: ' + error);
            }
        },


        async fetchDoctor(patientId: number) {
            try {
                const response = await api.get(`/v1/patients/${patientId}/doctor`);
                return response.data.data as Doctor;
            } catch (error) {
                throw new Error('Failed to fetch doctor: ' + error);
            }
        },

        async fetchInsuranceCompany(patientId: number) {
            try {
                const response = await api.get(`/v1/patients/${patientId}/insurance-company`);
                return response.data.data as InsuranceCompany
            } catch (error) {
                throw new Error('Failed to fetch insurance company: ' + error);
            }
        },


        clear() {
            this.current = null;
            localStorage.removeItem(STORAGE_KEY);
        },
    },
});
