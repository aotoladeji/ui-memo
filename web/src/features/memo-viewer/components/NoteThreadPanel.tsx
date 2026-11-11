import { useState } from 'react';
import { CheckCircle2, Lock, Send } from 'lucide-react';
import { useEditor, useEditorShallow } from '../store/editorStore';
import { api, errorText } from '@/shared/api';
import { useAuth } from '@/features/auth/authStore';
import type { Note, Reply } from '@/shared/types';

const EMPTY: Reply[] = [];

export function NoteThreadPanel() {
  const notes = useEditorShallow((s) => Object.values(s.notes).sort((a, b) => a.page_number - b.page_number || a.y - b.y));
  const [onlyAction, setOnlyAction] = useState(false);
  const list = onlyAction ? notes.filter((n) => n.requires_action && n.status === 'open') : notes;
  const pending = notes.filter((n) => n.requires_action && n.status === 'open').length;

  return (
    <aside className="flex h-full w-80 shrink-0 flex-col border-l border-surface-line bg-white" aria-label="Note threads">
      <div className="flex items-center justify-between border-b border-surface-line px-4 py-3">
        <h2 className="font-bold">Notes ({notes.length})</h2>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={onlyAction} onChange={(e) => setOnlyAction(e.target.checked)} />
          Needs action{pending > 0 && <span className="rounded-full bg-brand-600 px-2 text-xs text-white">{pending}</span>}
        </label>
      </div>
      <div className="flex-1 space-y-3 overflow-y-auto p-3">
        {list.length === 0 && <p className="p-2 text-sm text-ink-soft">{onlyAction ? 'Nothing needs action.' : 'No notes yet. Pick the Note tool and click the page to pin one.'}</p>}
        {list.map((n) => <Thread key={n.id} note={n} />)}
      </div>
    </aside>
  );
}

function Thread({ note }: { note: Note }) {
  const activeNoteId = useEditor((s) => s.activeNoteId);
  const setActive = useEditor((s) => s.setActive);
  const replies = useEditor((s) => s.replies[note.id] ?? EMPTY);
  const author = useEditor((s) => s.authors[note.author_id]);
  const authors = useEditor((s) => s.authors, (a, b) => a === b);
  const addReply = useEditor((s) => s.addReply);
  const upsertNote = useEditor((s) => s.upsertNote);
  const readOnly = useEditor((s) => s.readOnly);
  const routeId = useEditor((s) => s.routeId);
  const me = useAuth((s) => s.me)!;
  const [text, setText] = useState(''); const [err, setErr] = useState('');
  const [collapsed, setCollapsed] = useState(false);
  const active = activeNoteId === note.id;
  const open = note.requires_action && note.status === 'open';

  // Sticky note colors based on classification
  const stickyColor = note.classification === 'executive' 
    ? 'bg-yellow-200 border-yellow-300 shadow-yellow-100' 
    : note.requires_action 
      ? 'bg-pink-200 border-pink-300 shadow-pink-100' 
      : 'bg-yellow-100 border-yellow-200 shadow-yellow-50';

  const send = async () => {
    try { const { data } = await api.post<Reply>(`/notes/${note.id}/replies`, { body: text, route_id: routeId }); addReply(data); setText(''); setErr(''); }
    catch (e) { setErr(errorText(e)); }
  };
  const setStatus = async (status: 'open' | 'resolved') => {
    try { const { data } = await api.patch<Note>(`/notes/${note.id}`, { status, route_id: routeId }); upsertNote({ ...note, status: data.status }); }
    catch (e) { setErr(errorText(e)); }
  };

  return (
    <article 
      className={`rounded-sm border-t-8 p-3 text-sm shadow-md transition-all ${stickyColor} ${active ? 'ring-2 ring-brand-500' : ''}`} 
      style={{ 
        fontFamily: "'Segoe Print', 'Bradley Hand', cursive",
        transform: collapsed ? 'scale(0.98)' : 'rotate(-0.5deg)',
      }}
      onClick={() => setActive(note.id)}
    >
      <header className="mb-1 flex items-center justify-between gap-2">
        <span className="flex items-center gap-1 font-bold text-gray-800">
          {note.classification === 'executive' && <Lock size={12} aria-label="Executive note" />}
          {author?.name ?? 'Staff'}
        </span>
        <div className="flex items-center gap-2">
          <span className="text-xs text-gray-600">Page {note.page_number}</span>
          <button 
            className="text-gray-600 hover:text-gray-900 transition-transform"
            onClick={(e) => { e.stopPropagation(); setCollapsed(!collapsed); }}
            aria-label={collapsed ? 'Expand note' : 'Collapse note'}
            style={{ transform: collapsed ? 'rotate(180deg)' : 'rotate(0deg)' }}
          >
            ▼
          </button>
        </div>
      </header>
      
      {!collapsed && (
        <>
          {author?.title && <p className="text-xs text-gray-600 mb-1">{author.title}</p>}
          <p className="mt-1 whitespace-pre-wrap text-gray-800">{note.body}</p>
          {open && <p className="mt-2 inline-block rounded bg-red-400 px-2 py-0.5 text-xs font-bold text-white shadow-sm">Requires action or reply</p>}
          {note.status === 'resolved' && <p className="mt-2 flex items-center gap-1 text-xs text-green-700"><CheckCircle2 size={12} /> Resolved</p>}

          {replies.length > 0 && (
            <ul className="mt-3 space-y-2 border-l-2 border-gray-400 pl-3">
              {replies.map((r) => (
                <li key={r.id} className="text-gray-800">
                  <span className="font-bold">{authors[r.author_id]?.name ?? (r.author_id === me.id ? 'You' : 'Staff')}</span>
                  <p className="whitespace-pre-wrap text-sm">{r.body}</p>
                </li>
              ))}
            </ul>
          )}
          {active && !readOnly && (
            <div className="mt-3 space-y-2" onClick={(e) => e.stopPropagation()}>
              <div className="flex gap-2">
                <input 
                  className="field bg-white bg-opacity-70" 
                  value={text} 
                  onChange={(e) => setText(e.target.value)} 
                  placeholder="Reply" 
                  onKeyDown={(e) => e.key === 'Enter' && text.trim() && send()} 
                />
                <button className="btn btn-primary px-3" aria-label="Send reply" disabled={!text.trim()} onClick={send}><Send size={14} /></button>
              </div>
              {note.requires_action && (note.author_id === me.id || note.assigned_to === me.id) && (
                <button className="btn btn-ghost w-full bg-white bg-opacity-50 hover:bg-opacity-70" onClick={() => setStatus(note.status === 'open' ? 'resolved' : 'open')}>
                  {note.status === 'open' ? 'Mark resolved' : 'Reopen'}
                </button>
              )}
              {err && <p role="alert" className="text-xs text-red-700">{err}</p>}
            </div>
          )}
        </>
      )}
      
      {collapsed && (
        <p className="text-xs text-gray-600 italic truncate">{note.body}</p>
      )}
    </article>
  );
}
