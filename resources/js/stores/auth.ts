import router from '@/router';
import api from '@/services/api';
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: (localStorage.getItem('api_token') as string | null) || null,
        user: null as null | IUser,
        currentRole: null as null | string,
        currentBranch: null as null | IBranch,
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {
        async init() {
            if (!this.token) return;
            try {
                this.user = await this.fetchUserProfile();
            } catch (error) {
                // this.clearAuth();
                router.push({ name: 'login' });
            }

            const savedRole = localStorage.getItem('current_role');
            if (savedRole && this.user?.roles_list.includes(savedRole)) {
                this.currentRole = savedRole;
            } else if (this.user?.roles_list.length) {
                this.currentRole = this.user.roles_list[0] ?? null;
            }

            const savedBranchId = localStorage.getItem('current_branch_id');
            if (savedBranchId) {
                const branch = this.user?.branches.find(b => b.id === parseInt(savedBranchId));
                if (branch) {
                    this.currentBranch = branch;
                }
            } else if (this.user?.branches.length) {
                this.currentBranch = this.user.branches[0] ?? null;
            }
        },

        async fetchUserProfile() {
            const user = await api.get('/auth/profile');
            return user.data?.data;
        },

        async setAuth(token: string) {
            if (!token) return;
            this.token = token;
            localStorage.setItem('api_token', token);
            this.init();
        },

        setCurrentRole(role: string) {
            this.currentRole = role;
            localStorage.setItem('current_role', role);
        },

        setCurrentBranch(branchId: number) {
            const branch = this.user?.branches.find(b => b.id === branchId);
            if (!branch) return;
            this.currentBranch = branch;
            localStorage.setItem('current_branch_id', branchId.toString());

        },

        clearAuth() {
            this.token = null;
            localStorage.removeItem('api_token');
        },
    },
});

export default useAuthStore;
