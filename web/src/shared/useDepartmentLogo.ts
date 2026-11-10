import { useQuery } from '@tanstack/react-query';
import { api } from './api';

/** Fetches a department logo through the authenticated API. Returns an object URL, or null when none was uploaded. */
export function useDepartmentLogo(departmentId: number | null | undefined, enabled = true): string | null {
  const { data } = useQuery({
    queryKey: ['dept-logo', departmentId], enabled: enabled && !!departmentId, staleTime: 5 * 60_000,
    queryFn: async () => {
      try { return URL.createObjectURL((await api.get<Blob>(`/departments/${departmentId}/logo`, { responseType: 'blob' })).data); }
      catch (e: any) { if (e?.response?.status === 404) return null; throw e; }
    },
  });
  return data ?? null;
}
