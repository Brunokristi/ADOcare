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

        async persistPatientData(patient: Patient, branchId?: number) {
            try {
                const isNew = !patient.id

                if (isNew) {
                    // create
                    const response = await api.post('/v1/patients', {
                        ...patient,
                        ...(branchId ? { branch_id: branchId } : {}),
                    })

                    const created = response.data.data as Patient
                    return created
                }

                // update
                await api.put(`/v1/patients/${patient.id}`, patient)

                // optional: reload fresh from backend
                const fresh = await this.fetchPatient(patient.id)
                return fresh
            } catch (error) {
                throw new Error('Failed to save patient: ' + error)
            }
        },


        async createPatient(patient: Patient, branchId: number) {
            try {
                const response = await api.post(`/v1/branches/${branchId}/patients`, {
                    ...patient,
                    dekurz_number: patient.dekurz_number || 1
                });
                const created = response.data.data as Patient;
                return created;
            } catch (error) {
                throw new Error('Failed to create patient: ' + error);
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
