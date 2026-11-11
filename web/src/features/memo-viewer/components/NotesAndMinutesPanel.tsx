import { useState } from 'react';
import { CheckCircle2, Clock, Lock, Send, FileText, StickyNote } from 'lucide-react';
import { useEditor, useEditorShallow } from '../store/editorStore';
import { api, errorText } from '@/shared/api';
import { useAuth } from '@/features/auth/authStore';
import type { Note, Reply, Minute, MinuteReply } from '@/shared/types';

const EMPTY_REPLIES: Reply[] = [];
const EMPTY_MINUTE_REPLIES: MinuteReply[] = [];

type Tab = 'notes' | 'minutes';

export function NotesAndMinutesPanel() {
  const [tab, setTab] = useState<Tab>('notes');
  const notes = useEditorShallow((s) => Object.values(s.notes).sort((a, b) => a.page_number - b.page_number || a.y - b.y));
  const minutes = useEditorShallow((s) => Object.values(s.minutes || {}).sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()));
  const [onlyAction, setOnlyAction] = useState(false);
  const list = onlyAction ? notes.filter((n) => n.requires_action && n.status === 'open') : notes;
  const pending = notes.filter((n) => n.requires_action && n.status === 'open').length;

  return (
    <aside className="flex h-full w-80 shrink-0 flex-col border-l border-surface-line bg-white print-panel" aria-label="Notes and Minutes">
      {/* Tabs */}
      <div className="flex border-b border-surface-line print:hidden">
        <button
          className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold transition-colors ${
            tab === 'notes' ? 'border-b-2 border-brand-600 text-brand-700 bg-brand-50' : 'text-ink-soft hover:text-ink hover:bg-surface-raised'
          }`}
          onClick={() => setTab('notes')}
        >
          <StickyNote size={16} />
          Notes ({notes.length})
        </button>
        <button
          className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold transition-colors ${
            tab === 'minutes' ? 'border-b-2 border-brand-600 text-brand-700 bg-brand-50' : 'text-ink-soft hover:text-ink hover:bg-surface-raised'
          }`}
          onClick={() => setTab('minutes')}
        >
          <FileText size={16} />
          Minutes ({minutes.length})
        </button>
      </div>

      {/* Tab Content */}
      {tab === 'notes' && (
        <>
          <div className="flex items-center justify-between border-b border-surface-line px-4 py-2 print:hidden">
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={onlyAction} onChange={(e) => setOnlyAction(e.target.checked)} />
              Needs action{pending > 0 && <span className="rounded-full bg-brand-600 px-2 text-xs text-white">{pending}</span>}
            </label>
          </div>
          <h2 className="hidden print:block print:text-xl print:font-bold print:p-4">Notes</h2>
          <div className="flex-1 space-y-3 overflow-y-auto p-3">
            {list.length === 0 && <p className="p-2 text-sm text-ink-soft print:hidden">{onlyAction ? 'Nothing needs action.' : 'No notes yet. Pick the Note tool and click the page to pin one.'}</p>}
            {list.map((n) => <NoteThread key={n.id} note={n} />)}
          </div>
        </>
      )}

      {tab === 'minutes' && (
        <>
          <h2 className="hidden print:block print:text-xl print:font-bold print:p-4">Minutes</h2>
          <div className="flex-1 space-y-3 overflow-y-auto p-3">
            {minutes.length === 0 && <p className="p-2 text-sm text-ink-soft print:hidden">No minutes yet. Use the Minute tool to add annotations to the memo.</p>}
            {minutes.map((m) => <MinuteThread key={m.id} minute={m} />)}
          </div>
        </>
      )}
      
      {/* Print: Show both sections */}
      <div className="hidden print:block">
        <h2 className="text-xl font-bold p-4 border-t-2 border-black mt-4">All Notes</h2>
        <div className="space-y-3 p-3">
          {notes.map((n) => <NoteThread key={`print-note-${n.id}`} note={n} />)}
        </div>
        
        <h2 className="text-xl font-bold p-4 border-t-2 border-black mt-4">All Minutes</h2>
        <div className="space-y-3 p-3">
          {minutes.map((m) => <MinuteThread key={`print-minute-${m.id}`} minute={m} />)}
        </div>
      </div>
    </aside>
  );
}

function NoteThread({ note }: { note: Note }) {
  const activeNoteId = useEditor((s) => s.activeNoteId);
  const setActive = useEditor((s) => s.setActive);
  const replies = useEditor((s) => s.replies[note.id] ?? EMPTY_REPLIES);
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
        <div className="flex-1">
          <span className="flex items-center gap-1 font-bold text-gray-800">
            {note.classification === 'executive' && <Lock size={12} aria-label="Executive note" />}
            {author?.name ?? 'Staff'}
          </span>
          {author?.title && <p className="text-xs text-gray-600">{author.title}</p>}
        </div>
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

function MinuteThread({ minute }: { minute: Minute }) {
  const minuteReplies = useEditor((s) => s.minuteReplies?.[minute.id] ?? EMPTY_MINUTE_REPLIES);
  const author = useEditor((s) => s.authors[minute.author_id]);
  const authors = useEditor((s) => s.authors, (a, b) => a === b);
  const addMinuteReply = useEditor((s) => s.addMinuteReply);
  const readOnly = useEditor((s) => s.readOnly);
  const routeId = useEditor((s) => s.routeId);
  const me = useAuth((s) => s.me)!;
  const [text, setText] = useState('');
  const [err, setErr] = useState('');
  const [collapsed, setCollapsed] = useState(false);

  const send = async () => {
    try { 
      const { data } = await api.post<MinuteReply>(`/minutes/${minute.id}/replies`, { body: text, route_id: routeId }); 
      addMinuteReply(data); 
      setText(''); 
      setErr(''); 
    }
    catch (e) { setErr(errorText(e)); }
  };

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', { 
      month: 'short', 
      day: 'numeric', 
      hour: '2-digit', 
      minute: '2-digit' 
    });
  };

  return (
    <article className="rounded-lg border border-surface-line bg-white p-4 shadow-sm hover:shadow-md transition-shadow">
      <header className="mb-2 flex items-start justify-between gap-2">
        <div className="flex-1">
          <div className="flex items-center gap-2">
            <span className="font-bold text-ink">{author?.name ?? 'Staff'}</span>
            {minute.classification === 'executive' && (
              <span className="flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                <Lock size={10} /> Executive
              </span>
            )}
          </div>
          {author?.title && <p className="text-xs text-ink-soft">{author.title}</p>}
          <div className="mt-1 flex items-center gap-1 text-xs text-ink-soft">
            <Clock size={12} />
            {formatDate(minute.created_at)}
          </div>
        </div>
        <button 
          className="text-ink-soft hover:text-ink transition-transform"
          onClick={() => setCollapsed(!collapsed)}
          aria-label={collapsed ? 'Expand minute' : 'Collapse minute'}
          style={{ transform: collapsed ? 'rotate(180deg)' : 'rotate(0deg)' }}
        >
          ▼
        </button>
      </header>

      {!collapsed && (
        <>
          <p className="mt-2 whitespace-pre-wrap text-sm text-ink">{minute.body}</p>

          {minuteReplies.length > 0 && (
            <ul className="mt-4 space-y-3 border-l-2 border-surface-line pl-4">
              {minuteReplies.map((r) => (
                <li key={r.id}>
                  <div className="flex items-start gap-2">
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-bold">{authors[r.author_id]?.name ?? (r.author_id === me.id ? 'You' : 'Staff')}</span>
                        <span className="text-xs text-ink-soft">{formatDate(r.created_at)}</span>
                      </div>
                      <p className="mt-1 whitespace-pre-wrap text-sm text-ink-soft">{r.body}</p>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          )}

          {!readOnly && (
            <div className="mt-4 space-y-2">
              <div className="flex gap-2">
                <input 
                  className="field text-sm" 
                  value={text} 
                  onChange={(e) => setText(e.target.value)} 
                  placeholder="Add a reply..." 
                  onKeyDown={(e) => e.key === 'Enter' && text.trim() && send()} 
                />
                <button 
                  className="btn btn-primary px-3" 
                  aria-label="Send reply" 
                  disabled={!text.trim()} 
                  onClick={send}
                >
                  <Send size={14} />
                </button>
              </div>
              {err && <p role="alert" className="text-xs text-red-700">{err}</p>}
            </div>
          )}
        </>
      )}

      {collapsed && (
        <p className="text-xs text-ink-soft italic truncate mt-2">{minute.body}</p>
      )}
    </article>
  );
}
