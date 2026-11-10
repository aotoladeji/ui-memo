import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/shared/api';
import { useDocumentTitle } from '@/shared/useDocumentTitle';

interface Row { id: number; name: string; staff_no: string; title: string | null; role: string; is_active: boolean; unit?: { name: string } }
const ROLE: Record<string, string> = { super_admin: 'SuperAdmin', admin: 'Admin', unit_head: 'Unit Head', staff: 'Staff' };

export default function PeoplePage() {
  useDocumentTitle('People');
  const [q, setQ] = useState('');
  const { data } = useQuery({ queryKey: ['admin-people', q], queryFn: async () => (await api.get<{ data: Row[] }>('/admin/users', { params: { q } })).data.data });
  return (
    <div className="mx-auto max-w-4xl p-4 sm:p-6">
      <h1 className="mb-4 text-xl font-bold">People</h1>
      <input className="field mb-4" placeholder="Search name or staff no." value={q} onChange={(e) => setQ(e.target.value)} aria-label="Search people" />
      <div className="card overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-surface-line bg-surface"><tr><th className="p-3">Name</th><th className="p-3">Staff no.</th><th className="p-3">Role</th><th className="p-3">Unit</th><th className="p-3">Status</th></tr></thead>
          <tbody>{data?.map((u) => (
            <tr key={u.id} className="border-b border-surface-line last:border-0">
              <td className="p-3"><span className="font-bold">{u.name}</span>{u.title && <span className="block text-ink-soft">{u.title}</span>}</td>
              <td className="p-3">{u.staff_no}</td><td className="p-3">{ROLE[u.role] ?? u.role}</td><td className="p-3">{u.unit?.name ?? '—'}</td>
              <td className="p-3">{u.is_active ? 'Active' : 'Inactive'}</td>
            </tr>))}</tbody>
        </table>
      </div>
    </div>
  );
}
