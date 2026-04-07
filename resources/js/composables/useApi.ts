import { ref } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

export function useApi() {
    const loading = ref(false)
    const error = ref<Error | null>(null)

    const authStore = useAuthStore()

    const companyIdFromStore = () => {
        // prefer explicit overrides in caller; fall back to current company context
        return authStore.currentCompanyId ?? authStore.user?.company?.id ?? null
    }

    const companyBasePath = (companyId?: number | null) => {
        const effectiveId = companyId ?? companyIdFromStore()
        if (authStore.isSuperadmin && effectiveId) {
            return `/companies/${effectiveId}`
        }
        return '/my-company'
    }

    const defaultVersion = 'v1'

    const versionedUrl = (url: string, version?: string) => {
        const ver = version ?? defaultVersion
        const trimmed = url.replace(/^\/*/, '')
        // if already starts with /v{num} then keep it
        if (/^v\d+\//.test(trimmed) || /^v\d+$/.test(trimmed)) {
            return `/${trimmed}`
        }
        return `/${ver}/${trimmed}`
    }

    const companyBranchBasePath = (companyId?: number | null, branchId?: number | null) => {
        // Branch scope is the most specific; always use the branch path when a branch is provided.
        // Avoid generating `/companies/x/branches/y` since the API expects `/branches/y/...`.
        if (branchId) {
            return `/branches/${branchId}`
        }

        return companyBasePath(companyId)
    }

    const scopedUrl = (
        path: string,
        opts?:
            | number
            | {
                companyId?: number | null
                branchId?: number | null
            },
    ) => {
        const trimmed = path.replace(/^\//, '')

        if (typeof opts === 'number') {
            return `${companyBasePath(opts)}/${trimmed}`
        }

        const companyId = opts?.companyId
        const branchId = opts?.branchId
        if (companyId && branchId) {
            // Branch scope is more specific than company scope, so ignore companyId when branchId is provided.
            // This should only happen during refactors; log it so it can be cleaned up.
             
            console.warn(
                'useApi.scopedUrl: received both companyId and branchId; using branch scope (ignored companyId)',
            )
        }
        return `${companyBranchBasePath(companyId, branchId)}/${trimmed}`
    }

    async function getScoped<T>(
        path: string,
        opts?:
            | number
            | {
                companyId?: number | null
                branchId?: number | null
            },
        params?: Record<string, any>,
    ) {
        return get<T>(scopedUrl(path, opts), params)
    }

    async function listScoped<T>(
        path: string,
        opts?:
            | number
            | {
                companyId?: number | null
                branchId?: number | null
            },
        options?: Record<string, any>,
    ) {
        return list<T>(scopedUrl(path, opts), options)
    }

    async function safe<T>(fn: () => Promise<T>) {
        loading.value = true
        error.value = null
        try {
            const data = await fn()
            return { data, error: null }
        } catch (err) {
            const e = err as Error
            error.value = e
            return { data: null as unknown as T, error: e }
        } finally {
            loading.value = false
        }
    }

    async function request<T>(config: Parameters<typeof api.request>[0]) {
        const { version, ...axiosConfig } = config as any
        if (version && axiosConfig.url) {
            axiosConfig.url = versionedUrl(axiosConfig.url, version)
        }
        return safe(() => api.request<T>(axiosConfig).then((res) => res.data))
    }

    async function get<T>(url: string, params?: Record<string, any>) {
        const versioned = versionedUrl(url)
        return safe(() => api.get<T>(versioned, { params }).then((res) => res.data))
    }

    async function post<T>(url: string, data?: Record<string, any>, config?: any) {
        const versioned = versionedUrl(url)
        return safe(() => api.post<T>(versioned, data, config).then((res) => res.data))
    }

    async function put<T>(url: string, data?: Record<string, any>, config?: any) {
        const versioned = versionedUrl(url)
        return safe(() => api.put<T>(versioned, data, config).then((res) => res.data))
    }

    async function patch<T>(url: string, data?: Record<string, any>, config?: any) {
        const versioned = versionedUrl(url)
        return safe(() => api.patch<T>(versioned, data, config).then((res) => res.data))
    }

    async function del<T>(url: string, config?: any) {
        const versioned = versionedUrl(url)
        return safe(() => api.delete<T>(versioned, config).then((res) => res.data))
    }

    async function list<T>(url: string, options?: Record<string, any>) {
        const versioned = versionedUrl(url)
        return safe(() => api.fetchEntities<T>(versioned, options))
    }

    async function getEntity<T>(url: string, options?: Record<string, any>) {
        const versioned = versionedUrl(url)
        return safe(() => api.fetchEntity<T>(versioned, options))
    }

    async function paginated<T>(url: string, options?: Record<string, any>) {
        const versioned = versionedUrl(url)
        return safe(() => api.fetchEntitiesPaginated<T>(versioned, options))
    }

    return {
        loading,
        error,
        safe,
        request,
        get,
        post,
        put,
        patch,
        delete: del,
        list,
        getEntity,
        paginated,
        companyBasePath,
        companyBranchBasePath,
        scopedUrl,
        getScoped,
        listScoped,
    }
}
