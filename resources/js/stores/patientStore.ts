import { defineStore } from 'pinia';
import type { Doctor, InsuranceCompany, Patient } from '@/types/models';
import api from '@/services/api';
import useAuthStore from './auth';

const STORAGE_KEY = 'selected-patient';

export const usePatientStore = defineStore('patient', {
    state: () => ({
        current: null as Patient | null,
    }),

    actions: {
        setPatient(patient: Patient) {
            if (useAuthStore().isManager) return;
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

        async persistPatientData(patient: Patient) {
            try {
                const isNew = !patient.id

                if (isNew) {
                    const payload = {
                        first_name: patient.first_name,
                        last_name: patient.last_name,
                        title: patient.title,
                        personal_number: patient.personal_number,
                        sex: patient.sex,
                        contact: patient.contact,
                        doctor_id: patient.doctor_id,
                        insurance_company_id: patient.insurance_company_id,
                        country_id: patient.country_id,
                        address: patient.address,
                        city: patient.city,
                        zip: patient.zip,
                        latitude: patient.latitude,
                        longitude: patient.longitude,
                        reference_date: patient.reference_date,
                        death_date: patient.death_date,
                        dekurz_number: patient.dekurz_number || 1,
                        branch_id: patient.branch_id,
                        nurse_id: patient.nurse_id,
                    }
                    const response = await api.post('/v1/patients', payload)

                    const created = response.data.data as Patient
                    return created
                }

                // update
                let payload: Partial<Patient>
                if (useAuthStore().isManager) {
                    payload = {
                        branch_id: patient.branch_id,
                        nurse_id: patient.nurse_id,
                        death_date: patient.death_date,
                    }
                } else {
                    payload = {
                        first_name: patient.first_name,
                        last_name: patient.last_name,
                        title: patient.title,
                        personal_number: patient.personal_number,
                        sex: patient.sex,
                        contact: patient.contact,
                        doctor_id: patient.doctor_id,
                        insurance_company_id: patient.insurance_company_id,
                        country_id: patient.country_id,
                        address: patient.address,
                        city: patient.city,
                        zip: patient.zip,
                        latitude: patient.latitude,
                        longitude: patient.longitude,
                        reference_date: patient.reference_date,
                        death_date: patient.death_date,
                        dekurz_number: patient.dekurz_number,
                    }
                }

                await api.put(`/v1/patients/${patient.id}`, payload)

                // optional: reload fresh from backend
                const fresh = await this.fetchPatient(patient.id)
                return fresh
            } catch (error) {
                throw new Error('Failed to save patient: ' + error)
            }
        },


        async createPatient(patient: Patient, branchId: number) {
            try {
                const payload = {
                    first_name: patient.first_name,
                    last_name: patient.last_name,
                    title: patient.title,
                    personal_number: patient.personal_number,
                    sex: patient.sex,
                    contact: patient.contact,
                    doctor_id: patient.doctor_id,
                    insurance_company_id: patient.insurance_company_id,
                    country_id: patient.country_id,
                    address: patient.address,
                    city: patient.city,
                    zip: patient.zip,
                    latitude: patient.latitude,
                    longitude: patient.longitude,
                    reference_date: patient.reference_date,
                    death_date: patient.death_date,
                    dekurz_number: 1,
                }
                const response = await api.post(`/v1/branches/${branchId}/patients`, payload);
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

        async checkPatientDeath(patientId: number) {
            console.debug('[UDZS] Store: checkPatientDeath called', { patientId });
            try {
                const response = await api.get(`/v1/patients/${patientId}/death-check`);
                console.debug('[UDZS] Store: API response', { data: response.data });
                return response.data.data as {
                    status: 'alive' | 'dead' | 'unknown';
                    data: any;
                    reason?: string;
                    http_status?: number;
                };
            } catch (error) {
                console.error('[UDZS] Store: API error', error);
                throw new Error('Failed to check patient death status: ' + error);
            }
        },

        async softDeletePatient(patientId: number) {
            try {
                await api.delete(`/v1/patients/${patientId}`);

                if (this.current?.id === patientId) {
                    this.clear();
                }
            } catch (error) {
                throw new Error('Failed to delete patient: ' + error);
            }
        },


        clear() {
            this.current = null;
            localStorage.removeItem(STORAGE_KEY);
        },
    },
});
