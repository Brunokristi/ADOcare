import api from './api';
import { useAuthStore } from '@/stores/auth';

export interface LoginPayload {
    code?: string;
    pin?: string;
}

export async function login(payload: LoginPayload) {
    const res = await api.post('/auth/login', payload);
    const token = res.data?.data?.token
    const company = res.data?.data?.company ?? null
    const branch = res.data?.data?.branch ?? null
    const role = res.data?.data?.role ?? null

    const store = useAuthStore();
    store.setAuth({ token: token ?? null, company: company ?? undefined, branch: branch ?? undefined, role: role ?? undefined });

    return res;
}

export function logout() {
    const store = useAuthStore();
    store.clearAuth();
}

export function getToken() {
    const store = useAuthStore();
    return store.token;
}

export default { login, logout, getToken };
