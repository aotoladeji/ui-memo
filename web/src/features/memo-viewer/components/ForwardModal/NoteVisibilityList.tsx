import { Eye, EyeOff, Lock } from 'lucide-react';
import type { AuthorInfo, Note } from '@/shared/types';

export interface NoteVisibilityListProps {
  notes: Note[]; authors: Record<string, AuthorInfo>; replyCounts: Record<number, number>;
  isVisible: (n: Note) => boolean; onToggle: (id: number, show: boolean) => void;
}

/** One row per thread with an eye toggle. Row state mirrors the server's precedence rules. */
export function NoteVisibilityList({ notes, authors, replyCounts, isVisible, onToggle }: NoteVisibilityListProps) {
  if (notes.length === 0) return <p className="text-sm text-ink-soft">This memo has no notes yet.</p>;
  return (
    <ul className="max-h-64 space-y-2 overflow-y-auto" aria-label="Note visibility">
      {notes.map((n) => {
        const show = isVisible(n); const a = authors[n.author_id];
        return (
          <li key={n.id} className={`flex items-start gap-3 rounded-lg border p-3 ${show ? 'border-surface-line bg-white' : 'border-dashed border-surface-line bg-surface text-ink-soft'}`}>
            <button className="mt-0.5 rounded p-1 hover:bg-brand-50" aria-pressed={show} aria-label={`${show ? 'Hide' : 'Show'} note by ${a?.name ?? 'staff'} to recipient`} onClick={() => onToggle(n.id, !show)}>
              {show ? <Eye size={18} className="text-brand-600" /> : <EyeOff size={18} />}
            </button>
            <div className="min-w-0 flex-1 text-sm">
              <p className="flex items-center gap-1 font-bold">{n.classification === 'executive' && <Lock size={12} />}{a?.name ?? 'Staff'}{a?.title ? <span className="font-normal text-ink-soft"> · {a.title}</span> : null}</p>
              <p className="truncate">{n.body}</p>
              <p className="text-xs">Page {n.page_number}{replyCounts[n.id] ? ` · ${replyCounts[n.id]} repl${replyCounts[n.id] === 1 ? 'y' : 'ies'} (follow the thread)` : ''}</p>
            </div>
          </li>
        );
      })}
    </ul>
  );
}
