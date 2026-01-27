import { openModal } from '@/composables/useModal';
import { openPriceAlertModal } from '@/helpers/modalHelpers';
import router from '@/router';
import api from '@/services/api';
import type { Branch, User } from '@/types/models';
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        storeStatus: 'ready' as 'ready' | 'initializing',
        token: (localStorage.getItem('api_token') as string | null) || null,
        user: null as null | User,
        currentRole: null as null | string,
        currentBranch: null as null | Branch,
    }),
    getters: {
        isAuthenticated: (state) => !!state.user,
        isManager: (state) => state.currentRole === 'manager',
    },
    actions: {
        async waitUntilInitialized() {
            if (this.storeStatus === 'ready') return;
            return new Promise<void>((resolve) => {
                const checkInterval = setInterval(() => {
                    if (this.storeStatus === 'ready') {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 50);
            });
        },

        async init() {
            this.storeStatus = 'initializing';

            try {
                if (!this.token) {
                    this.user = null;
                    this.currentRole = null;
                    this.currentBranch = null;
                    return;
                }

                this.user = await this.fetchUserProfile();

                const savedRole = localStorage.getItem('current_role');
                if (savedRole && this.user?.role_names.includes(savedRole)) {
                    this.currentRole = savedRole;
                    console.log(`Restored saved role: ${savedRole}`);

                } else if (this.user?.role_names.length) {
                    this.currentRole = this.user.role_names[0] ?? null;
                }

                const savedBranchId = localStorage.getItem('current_branch_id');
                if (savedBranchId) {
                    const branch = this.user?.branches.find(b => b.id === parseInt(savedBranchId));
                    if (branch) this.currentBranch = branch;
                } else if (this.user?.branches.length) {
                    this.currentBranch = this.user.branches[0] ?? null;
                }
            } catch {
                this.clearAuth();
                this.user = null;
                this.currentRole = null;
                this.currentBranch = null;
                router.push({ name: 'login' });
            } finally {
                this.storeStatus = 'ready';
                openPriceAlertModal();
            }
        },

        async fetchUserProfile() {
            const user = await api.fetchEntity<User>('/auth/profile');
            return user;
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

        setCurrentBranchById(branchId: number) {
            const branch = this.user?.branches.find(b => b.id === branchId);
            if (!branch) throw new Error('Branch not found');
            this.currentBranch = branch;
            localStorage.setItem('current_branch_id', branchId.toString());

        },

        clearAuth() {
            this.token = null;
            this.user = null;
            this.currentRole = null;
            this.currentBranch = null;
            localStorage.removeItem('api_token');
        },

        clearCurrentBranch() {
            this.currentBranch = null;
            localStorage.removeItem('current_branch_id');
        }
    },
});

export default useAuthStore;
