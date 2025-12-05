import { ref, computed } from 'vue';
import api from '@/services/api';
type RemoteLoadResultLocal<T = any> = { items: T[]; total: number };

export function useRemoteTable<T = any>(endpointUrl: string, opts: { defaultPageSize?: number; extraParams?: Record<string, any> } = {}) {
  const loading = ref(false);
  const items = ref<T[]>([]);
  const total = ref<number>(0);

  const page = ref<number>(1);
  const per_page = ref<number>(opts.defaultPageSize ?? 10);
  const q = ref<string>('');
  const sort = ref<string | undefined>(undefined);

  const params = computed(() => ({
    paginate: true,
    page: page.value,
    per_page: per_page.value,
    q: q.value || '',
    sort: sort.value || undefined,
    ...opts.extraParams,
  }));

  async function loadPage(p: number = 1): Promise<RemoteLoadResultLocal<T> | void> {
    loading.value = true;
    page.value = p;
    try {
      const res = (await api.get(endpointUrl, { params: params.value })).data as IPaginatedIndexSuccessResponse<T>;
    console.log('[useRemoteTable] loadPage response', res);

      const payload = res.data;

        items.value = payload.items;
        total.value = payload.meta.total;


      return { items: items.value, total: total.value } as RemoteLoadResultLocal<T>;
    } catch (err) {
      console.error('[useRemoteTable] load failed', err);
      items.value = [];
      total.value = 0;
    } finally {
      loading.value = false;
    }
  }

  function setSearch(v: string) {
    q.value = v;
  }

  function setSort(s: string | undefined) {
    sort.value = s;
  }

  function setPerPage(n: number) {
    per_page.value = n;
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
  };
}
