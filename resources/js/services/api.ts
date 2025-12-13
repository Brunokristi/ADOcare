import axios from 'axios';
import { useAuthStore } from '@/stores/auth';

const baseURL = import.meta.env.VITE_API_BASE_URL ?? '/api';

const api = axios.create({
    baseURL,
    headers: {
        Accept: 'application/json',
    },
});

// Attach token from Pinia if present
api.interceptors.request.use((config) => {
    try {
        const store = useAuthStore();
        const token = store.token;
        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`;
        }
    } catch (e) {
        // store may not be initialized yet in some contexts; fallback to localStorage
        const token = localStorage.getItem('api_token');
        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`;
        }
    }
    return config;
});

// Simple response error handling
api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response && err.response.status === 401) {
            // emit an event or handle globally
            window.dispatchEvent(new CustomEvent('unauthenticated'));
        }
        return Promise.reject(err);
    }
);


interface FetchEntityOptions {
    with?: string[];
};

interface FetchEntitiesOptions {
    with?: string[];
    all?: boolean;
    limit?: number;
    sort?: string;
    q?: string;
    filter?: Record<string, any>;
};

type FetchEntitiesPaginatedOptions = /*without limit*/ Omit<FetchEntitiesOptions, 'limit'> & {
    per_page?: number;
    page?: number;
};

// Fetch entity helper
declare module 'axios' {
    interface AxiosInstance {
        fetchEntity<T>(url: string, options?: FetchEntityOptions): Promise<T>;
        fetchEntities<T>(url: string, options?: FetchEntitiesOptions): Promise<T[]>;
        fetchEntitiesPaginated<T>(url: string, options?: FetchEntitiesPaginatedOptions): Promise<IIndexSuccessPaginatedResponsePayload<T>>;
    }
}

api.fetchEntity = async function <T>(url: string, options?: FetchEntityOptions): Promise<T> {
    try {
        const response = await api.get(url, {
            params: options ?? {},
        });
        const responseData = response.data as IShowSuccessResponse<T>;
        return responseData.data;
    } catch (error: IErrorResponse | any) {
        throw new Error('Failed to fetch entity: ' + error);
    }
}

api.fetchEntities = async function <T>(url: string, options?: FetchEntitiesOptions): Promise<T[]> {
    const params = { ...options, paginate: false };
    try {
        const response = await api.get(url, {
            params: params,
        });
        const responseData = response.data as IIndexSuccessResponse<T>;
        return responseData.data.items;
    } catch (error: IErrorResponse | any) {
        throw new Error('Failed to fetch entities: ' + error);
    }
}

api.fetchEntitiesPaginated = async function <T>(url: string, options?: FetchEntitiesPaginatedOptions) {
    const params = { ...options, paginate: true };
    try {
        const response = await api.get(url, {
            params: params,
        });
        const responseData = response.data as IPaginatedIndexSuccessResponse<T>;
        return responseData.data;
    } catch (error: IErrorResponse | any) {
        throw new Error('Failed to fetch paginated entities: ' + error);
    }
}


export default api;
