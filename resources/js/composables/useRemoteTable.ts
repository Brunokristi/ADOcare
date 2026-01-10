import { ref, computed } from 'vue';
import api from '@/services/api';

type RemoteLoadResultLocal<T = any> = { items: T[]; total: number };

export function useRemoteTable<T = any>(
  endpointUrl: string,
  customOptions: { defaultPageSize?: number; extraParams?: Record<string, any> } = {}
) {

    const opts = customOptions || {};


  const loading = ref(false);
  const items = ref<T[]>([]);
  const total = ref<number>(0);

  const page = ref<number>(1);
  const per_page = ref<number>(opts.defaultPageSize ?? 10);
  const q = ref<string>('');
  const sort = ref<string | undefined>(undefined);

  const params = computed(() => {
    const p: Record<string, any> = {
      paginate: true,
      page: Number(page.value),
      per_page: Number(per_page.value),
      ...opts.extraParams,
    };

    // only send q if non-empty (prevents backend 422)
    const qq = typeof q.value === 'string' ? q.value.trim() : '';
    if (qq.length > 0) p.q = qq;
    else delete p.q;

    // only send sort if non-empty
    const ss = typeof sort.value === 'string' ? sort.value.trim() : '';
    if (ss.length > 0) p.sort = ss;
    else delete p.sort;

    return p;
  });

  async function loadPage(p: number = 1): Promise<RemoteLoadResultLocal<T> | void> {
    // skip if endpoint is empty (branch not ready)
    if (!endpointUrl || endpointUrl.trim().length === 0) {
      items.value = [];
      total.value = 0;
      return { items: [], total: 0 };
    }

    loading.value = true;
    page.value = p;

    try {
      console.log('[useRemoteTable] GET', endpointUrl, params.value);

      const res = (await api.get(endpointUrl, { params: params.value })).data as IPaginatedIndexSuccessResponse<T>;
      console.log('[useRemoteTable] loadPage response', res);

      const payload = res.data;

      items.value = payload.items;
      total.value = payload.meta.total;

      return { items: payload.items, total: payload.meta.total };
    } catch (err) {
      console.error('[useRemoteTable] load failed', err);
      items.value = [];
      total.value = 0;
    } finally {
      loading.value = false;
    }
  }

  function setSearch(v: string) {
    q.value = v ?? '';
  }

  function setSort(s: string | undefined) {
    sort.value = s;
  }

  function setPerPage(n: number) {
    per_page.value = n;
  }

  function setExtraParams(p: Record<string, any>) {
    opts.extraParams = p;
  }

  function setExtraParam(key: string, value: any) {
    if (!opts.extraParams) {
      opts.extraParams = {};
    }
    opts.extraParams[key] = value;
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
    setSearch,
    setSort,
    setPerPage,
    setExtraParams,
    setExtraParam,
  };
}
