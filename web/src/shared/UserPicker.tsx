import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { X } from 'lucide-react';
import { api } from './api';
import type { UserLite } from './types';

/** Search-as-you-type staff picker. value = selected users (kept as objects so chips can show names). */
export function UserPicker({ value, onChange, single, placeholder = 'Search name or staff no.' }:
  { value: UserLite[]; onChange: (v: UserLite[]) => void; single?: boolean; placeholder?: string }) {
  const [q, setQ] = useState('');
  const { data = [] } = useQuery({
    queryKey: ['user-search', q], enabled: q.length >= 2,
    queryFn: async () => (await api.get<UserLite[]>('/users/search', { params: { q } })).data,
  });
  const pick = (u: UserLite) => { onChange(single ? [u] : [...value.filter((v) => v.id !== u.id), u]); setQ(''); };
  return (
    <div>
      <div className="mb-2 flex flex-wrap gap-2">
        {value.map((u) => (
          <span key={u.id} className="inline-flex items-center gap-1 rounded-full bg-brand-100 px-3 py-1 text-sm">
            {u.name}
            <button aria-label={`Remove ${u.name}`} onClick={() => onChange(value.filter((v) => v.id !== u.id))}><X size={14} /></button>
          </span>
        ))}
      </div>
      <input className="field" value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} aria-label="Find staff" />
      {q.length >= 2 && (
        <ul className="mt-1 max-h-48 overflow-auto rounded-lg border border-surface-line bg-white">
          {data.length === 0 && <li className="px-3 py-2 text-sm text-ink-soft">No staff match “{q}”.</li>}
          {data.map((u) => (
            <li key={u.id}>
              <button className="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-brand-50" onClick={() => pick(u)}>
                <span className="font-bold">{u.name}</span>
                {u.title && <span className="text-ink-soft">{u.title}</span>}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
