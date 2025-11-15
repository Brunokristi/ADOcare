import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: (localStorage.getItem('api_token') as string | null) || null,
        company: (localStorage.getItem('company_name') as string | null) || [],
        branches: (localStorage.getItem('branch_name') as string | null) || [],
        roles: (localStorage.getItem('role_name') as string | null) || [],
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {

        setAuth(payload: { token?: string | null; company?: string; branches?: string; roles?: string }) {
            this.token = payload.token ?? null;
            if (payload.token) localStorage.setItem('api_token', payload.token);
            else localStorage.removeItem('api_token');

            if (payload.company !== undefined) {
                this.company = payload.company || '';
                if (payload.company) localStorage.setItem('company_name', payload.company);
                else localStorage.removeItem('company_name');
            }

            if (payload.branches !== undefined) {
                this.branches = payload.branches || '';
                if (payload.branches) localStorage.setItem('branch_name', payload.branches);
                else localStorage.removeItem('branch_name');
            }

            if (payload.roles !== undefined) {
                this.roles = payload.roles || '';
                if (payload.roles) localStorage.setItem('role_name', payload.roles);
                else localStorage.removeItem('role_name');
            }
        },
        clearAuth() {
            this.token = null;
            localStorage.removeItem('api_token');

            this.company = '';
            localStorage.removeItem('company_name');

            this.branches = '';
            localStorage.removeItem('branch_name');

            this.roles = '';
            localStorage.removeItem('role_name');
        },
    },
});

export default useAuthStore;
