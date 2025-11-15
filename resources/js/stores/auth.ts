import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: (localStorage.getItem('api_token') as string | null) || null,
        company: (localStorage.getItem('company_name') as string | null) || (localStorage.getItem('company') as string | null) || '',
        branch: (localStorage.getItem('branch_name') as string | null) || (localStorage.getItem('branch') as string | null) || '',
        role: (localStorage.getItem('role_name') as string | null) || (localStorage.getItem('role') as string | null) || '',
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {
        setAuth(payload: { token?: string | null; company?: string; branch?: string; role?: string }) {
            this.token = payload.token ?? null;
            if (payload.token) localStorage.setItem('api_token', payload.token);
            else localStorage.removeItem('api_token');

            if (payload.company !== undefined) {
                this.company = payload.company || '';
                if (payload.company) localStorage.setItem('company_name', payload.company);
                else localStorage.removeItem('company_name');
            }

            if (payload.branch !== undefined) {
                this.branch = payload.branch || '';
                if (payload.branch) localStorage.setItem('branch_name', payload.branch);
                else localStorage.removeItem('branch_name');
            }

            if (payload.role !== undefined) {
                this.role = payload.role || '';
                if (payload.role) localStorage.setItem('role_name', payload.role);
                else localStorage.removeItem('role_name');
            }
        },
        clearAuth() {
            this.token = null;
            this.company = '';
            this.branch = '';
            this.role = '';
            localStorage.removeItem('api_token');
            localStorage.removeItem('company_name');
            localStorage.removeItem('branch_name');
            localStorage.removeItem('role_name');
        },
    },
});

export default useAuthStore;
