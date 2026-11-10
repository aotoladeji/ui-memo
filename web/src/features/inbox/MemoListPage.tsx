import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { FilePlus2 } from 'lucide-react';
import { api, errorText } from '@/shared/api';
import { useDocumentTitle } from '@/shared/useDocumentTitle';

interface Row { id: number; ref_no: string; subject: string; priority: string; status: string; unread?: boolean; author: { name: string; title?: string | null } }
export type Box = 'inbox' | 'sent' | 'drafts';

const COPY: Record<Box, { title: string; empty: string }> = {
  inbox: { title: 'Inbox', empty: 'No memos for you yet.' },
  sent: { title: 'Sent', empty: 'You have not sent any memos.' },
  drafts: { title: 'Drafts', empty: 'No drafts. Start a new memo.' },
};

export default function MemoListPage({ box }: { box: Box }) {
  useDocumentTitle(COPY[box].title);
  const { data, isLoading, error } = useQuery({ queryKey: ['memos', box], queryFn: async () => (await api.get<{ data: Row[] }>('/memos', { params: { box } })).data.data });
  return (
    <div className="mx-auto max-w-4xl p-4 sm:p-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-bold">{COPY[box].title}</h1>
        <Link to="/memos/new" className="btn btn-primary"><FilePlus2 size={16} /> New memo</Link>
      </div>
      {isLoading ? <p className="text-ink-soft">Loading…</p> : error ? <p role="alert" className="text-red-700">{errorText(error)}</p> : data?.length === 0 ? (
        <p className="card p-8 text-center text-ink-soft">{COPY[box].empty}</p>
      ) : (
        <ul className="space-y-2">
          {data?.map((m) => (
            <li key={m.id}><Link to={`/memos/${m.id}`} className="card flex items-center justify-between gap-4 p-4 hover:border-brand-400">
              <span className="min-w-0">
                <span className={`block truncate ${m.unread ? 'font-bold' : ''}`}>{m.unread && <span className="mr-2 inline-block h-2 w-2 rounded-full bg-brand-600 align-middle" aria-label="Unread" />}{m.subject}</span>
                <span className="text-sm text-ink-soft">{m.author.name}{m.author.title ? `, ${m.author.title}` : ''} · {m.ref_no}</span>
              </span>
              {m.priority === 'urgent' && <span className="rounded-full bg-brand-600 px-3 py-0.5 text-xs font-bold text-white">Urgent</span>}
            </Link></li>
          ))}
        </ul>
      )}
    </div>
  );
}
