import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { FilePlus2 } from 'lucide-react';
import { api, errorText } from '@/shared/api';
import { useDocumentTitle } from '@/shared/useDocumentTitle';
import { useEffect, useState } from 'react';

interface Row { id: number; ref_no: string; subject: string; priority: string; status: string; unread?: boolean; author: { name: string; title?: string | null; department?: string | null } }
interface PaginationMeta { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
interface PaginatedMemos { data: Row[]; meta?: PaginationMeta }
export type Box = 'inbox' | 'sent' | 'drafts';

const PER_PAGE = 20;

const COPY: Record<Box, { title: string; empty: string }> = {
  inbox: { title: 'Inbox', empty: 'No memos for you yet.' },
  sent: { title: 'Sent', empty: 'You have not sent any memos.' },
  drafts: { title: 'Drafts', empty: 'No drafts. Start a new memo.' },
};

type Category = 'all' | 'vc' | 'dvc' | 'dean' | 'hod' | 'units' | 'other';

const CATEGORIES: { value: Category; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'vc', label: "VC's Office" },
  { value: 'dvc', label: "DVC's Office" },
  { value: 'dean', label: "Dean's Office" },
  { value: 'hod', label: "HOD's Office" },
  { value: 'units', label: 'Units' },
  { value: 'other', label: 'Other' },
];

function categorizeRow(row: Row): Category {
  const dept = (row.author.department || '').toLowerCase();
  const title = (row.author.title || '').toLowerCase();
  
  if (dept.includes('vice-chancellor') || title.includes('vice-chancellor') || dept.includes('vc office')) return 'vc';
  if (dept.includes('deputy vice-chancellor') || title.includes('deputy vice-chancellor') || dept.includes('dvc office')) return 'dvc';
  if (title.includes('dean') || dept.includes('dean')) return 'dean';
  if (title.includes('head of department') || title.includes('hod') || dept.includes('hod')) return 'hod';
  if (dept.includes('unit') || dept.includes('centre') || dept.includes('division')) return 'units';
  return 'other';
}

export default function MemoListPage({ box }: { box: Box }) {
  useDocumentTitle(COPY[box].title);
  const [activeCategory, setActiveCategory] = useState<Category>('all');
  const [page, setPage] = useState(1);

  useEffect(() => {
    setPage(1);
    setActiveCategory('all');
  }, [box]);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['memos', box, page],
    queryFn: async () => (await api.get<PaginatedMemos>('/memos', { params: { box, page, per_page: PER_PAGE } })).data,
  });

  const data = res?.data;
  const meta = res?.meta;

  const filteredData = data?.filter((m) => {
    if (activeCategory === 'all') return true;
    return categorizeRow(m) === activeCategory;
  });

  return (
    <div className="mx-auto max-w-4xl p-4 sm:p-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-bold">{COPY[box].title}</h1>
        <Link to="/memos/new" className="btn btn-primary"><FilePlus2 size={16} /> New memo</Link>
      </div>
      
      {/* Category Tabs */}
      {box !== 'drafts' && (
        <div className="mb-4 flex flex-wrap gap-2 border-b border-surface-line pb-2">
          {CATEGORIES.map((cat) => (
            <button
              key={cat.value}
              onClick={() => setActiveCategory(cat.value)}
              className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                activeCategory === cat.value
                  ? 'bg-brand-600 text-white'
                  : 'bg-surface text-ink-soft hover:bg-surface-raised'
              }`}
            >
              {cat.label}
              {data && cat.value !== 'all' && (
                <span className="ml-1.5 text-xs opacity-75">
                  ({data.filter(m => categorizeRow(m) === cat.value).length})
                </span>
              )}
            </button>
          ))}
        </div>
      )}
      
      {isLoading ? <p className="text-ink-soft">Loading…</p> : error ? <p role="alert" className="text-red-700">{errorText(error)}</p> : filteredData?.length === 0 ? (
        <p className="card p-8 text-center text-ink-soft">{activeCategory === 'all' ? COPY[box].empty : `No memos in this category.`}</p>
      ) : (
        <ul className="space-y-2">
          {filteredData?.map((m) => (
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

      {meta && meta.last_page > 1 && (
        <nav aria-label="Memo pagination" className="mt-4 flex items-center justify-between border-t border-surface-line pt-3 text-sm">
          <span className="text-ink-soft">
            Page {meta.current_page} of {meta.last_page} ({meta.total} total)
          </span>
          <div className="flex gap-2">
            <button
              type="button"
              className="btn btn-ghost"
              disabled={meta.current_page <= 1 || isLoading}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              Previous
            </button>
            <button
              type="button"
              className="btn btn-ghost"
              disabled={meta.current_page >= meta.last_page || isLoading}
              onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
            >
              Next
            </button>
          </div>
        </nav>
      )}
    </div>
  );
}
