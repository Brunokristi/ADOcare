import { ref, computed } from 'vue'
import api from '@/services/api'

type RemoteLoadResultLocal<T = any> = { items: T[]; total: number }

function extractRemote<T = any>(res: any): { items: T[]; total: number } {
    // Your API wrapper seems to be: { message, data: ... }
    const wrapped = res?.data ?? res

    // 1) BaseCollection style:
    // { data: { items: [...], meta: { total } } }
    const baseItems = wrapped?.items
    const baseTotal = wrapped?.meta?.total

    if (Array.isArray(baseItems) && typeof baseTotal === 'number') {
        return { items: baseItems as T[], total: baseTotal }
    }

    // 2) Laravel paginator style:
    // { data: { data: [...], total: number, ... } }
    // (this is exactly what your patient-points index returns)
    const paginator = wrapped
    const pagedItems = paginator?.data
    const pagedTotal = paginator?.total

    if (Array.isArray(pagedItems) && typeof pagedTotal === 'number') {
        return { items: pagedItems as T[], total: pagedTotal }
    }

    // 3) Sometimes paginator is nested one level deeper:
    // { data: { data: { data: [...], total } } }
    const deepPaginator = wrapped?.data
    if (deepPaginator && Array.isArray(deepPaginator?.data) && typeof deepPaginator?.total === 'number') {
        return { items: deepPaginator.data as T[], total: deepPaginator.total }
    }

    // 4) Fallbacks: try common locations
    const candidates = [
        wrapped?.data?.items,
        wrapped?.data?.data,
        wrapped?.items,
        wrapped?.data,
    ]

    for (const c of candidates) {
        if (Array.isArray(c)) {
            return { items: c as T[], total: c.length }
        }
    }

    return { items: [], total: 0 }
}

export function useRemoteTable<T = any>(
    endpointUrl: string,
    customOptions: { defaultPageSize?: number; extraParams?: Record<string, any> } = {},
) {
    const opts = customOptions || {}

    const loading = ref(false)
    const items = ref<T[]>([])
    const total = ref<number>(0)

    const page = ref<number>(1)
    const per_page = ref<number>(opts.defaultPageSize ?? 10)
    const q = ref<string>('')
    const sort = ref<string | undefined>(undefined)

    const params = computed(() => {
        const p: Record<string, any> = {
            paginate: true,
            page: Number(page.value),
            per_page: Number(per_page.value),
            ...opts.extraParams,
        }

        const qq = typeof q.value === 'string' ? q.value.trim() : ''
        if (qq.length > 0) p.q = qq
        else delete p.q

        const ss = typeof sort.value === 'string' ? sort.value.trim() : ''
        if (ss.length > 0) p.sort = ss
        else delete p.sort

        return p
    })

    async function loadPage(p: number = 1): Promise<RemoteLoadResultLocal<T> | void> {
        if (!endpointUrl || endpointUrl.trim().length === 0) {
            items.value = []
            total.value = 0
            return { items: [], total: 0 }
        }

        loading.value = true
        page.value = p

        try {
            console.log('[useRemoteTable] GET', endpointUrl, params.value)

            const res = (await api.get(endpointUrl, { params: params.value })).data
            console.log('[useRemoteTable] loadPage response', res)

            // IMPORTANT: your real payload is inside res.data
            // so parse res.data first, but keep compatibility
            const payload = res?.data ?? res

            const parsed = extractRemote<T>(payload)

            items.value = parsed.items
            total.value = parsed.total

            return parsed
        } catch (err) {
            console.error('[useRemoteTable] load failed', err)
            items.value = []
            total.value = 0
        } finally {
            loading.value = false
        }
    }

    function reload() {
        return loadPage(page.value)
    }

    function setSearch(v: string) {
        q.value = v ?? ''
    }

    function setSort(s: string | undefined) {
        sort.value = s
    }

    function setPerPage(n: number) {
        per_page.value = n
    }

    function setExtraParams(p: Record<string, any>) {
        opts.extraParams = p
    }

    function setExtraParam(key: string, value: any) {
        if (!opts.extraParams) opts.extraParams = {}
        opts.extraParams[key] = value
    }

    return {
        loading,
        items,
        total,
        page,
        per_page,
        q,
        sort,
        params,
        loadPage,
        reload,
        setSearch,
        setSort,
        setPerPage,
        setExtraParams,
        setExtraParam,
    }
}
