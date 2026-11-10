import { useState } from 'react';
import { useDocumentTitle } from '@/shared/useDocumentTitle';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, errorText } from '@/shared/api';
import { Switch } from '@/shared/ui';

interface Row { id: number; name: string; staff_no: string; title: string | null; role: string; can_send_global_broadcast: boolean; unit?: { name: string } }

/** SuperAdmin only. Every toggle writes a row to permission_audit_logs on the server. */
export default function PermissionManager() {
  useDocumentTitle('Permissions');
  const qc = useQueryClient(); const [q, setQ] = useState(''); const [err, setErr] = useState('');
  const { data } = useQuery({ queryKey: ['admin-users', q], queryFn: async () => (await api.get<{ data: Row[] }>('/admin/users', { params: { q } })).data.data });
  const toggle = useMutation({
    mutationFn: (v: { id: number; on: boolean }) => api.patch(`/admin/users/${v.id}/permissions`, { can_send_global_broadcast: v.on }),
    onSuccess: () => { setErr(''); qc.invalidateQueries({ queryKey: ['admin-users'] }); }, onError: (e) => setErr(errorText(e)),
  });
  return (
    <div className="mx-auto max-w-3xl p-6">
      <h1 className="text-xl font-bold">Global broadcast permission</h1>
      <p className="mb-4 text-sm text-ink-soft">Only staff switched on here can send a memo to all staff.</p>
      <input className="field mb-4" placeholder="Search name or staff no." value={q} onChange={(e) => setQ(e.target.value)} aria-label="Search staff" />
      {err && <p role="alert" className="mb-3 text-sm text-red-700">{err}</p>}
      <ul className="space-y-2">
        {data?.map((u) => (
          <li key={u.id} className="card flex items-center justify-between gap-4 p-4">
            <span><span className="block font-bold">{u.name}</span><span className="text-sm text-ink-soft">{u.title ?? u.role} · {u.staff_no}{u.unit ? ` · ${u.unit.name}` : ''}</span></span>
            <Switch checked={u.can_send_global_broadcast} label={`Allow ${u.name} to send global broadcasts`} disabled={toggle.isPending} onChange={(on) => toggle.mutate({ id: u.id, on })} />
          </li>
        ))}
      </ul>
    </div>
  );
}
