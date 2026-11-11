import { useRef, useState } from 'react';
import { CheckCircle2, Lock } from 'lucide-react';
import { useEditor, useEditorShallow } from '../../store/editorStore';
import { api, errorText } from '@/shared/api';
import { UserPicker } from '@/shared/UserPicker';
import type { Note, UserLite } from '@/shared/types';

/** Layer 2: HTML sticky-note pins at normalised (x, y) per page. Transparent to the canvas below except on pins. */
export function NoteLayer({ page, width, height }: { page: number; width: number; height: number }) {
  const tool = useEditor((s) => s.tool);
  const readOnly = useEditor((s) => s.readOnly);
  const notes = useEditorShallow((s) => Object.values(s.notes).filter((n) => n.page_number === page));
  const draft = useEditor((s) => (s.draft?.page === page ? s.draft : null));
  const setDraft = useEditor((s) => s.setDraft);
  const placing = !readOnly && tool === 'note';

  return (
    <div className="absolute inset-0 z-20" style={{ pointerEvents: placing ? 'auto' : 'none', cursor: placing ? 'crosshair' : 'default' }}
      onPointerDown={(e) => {
        if (!placing || e.target !== e.currentTarget) return;
        const r = e.currentTarget.getBoundingClientRect();
        setDraft({ page, x: (e.clientX - r.left) / width, y: (e.clientY - r.top) / height });
      }}>
      {notes.map((n) => <NotePin key={n.id} note={n} width={width} height={height} />)}
      {draft && <NoteComposer x={draft.x * width} y={draft.y * height} page={page} nx={draft.x} ny={draft.y} />}
    </div>
  );
}

function NotePin({ note, width, height }: { note: Note; width: number; height: number }) {
  const activeNoteId = useEditor((s) => s.activeNoteId);
  const setActive = useEditor((s) => s.setActive);
  const moveNote = useEditor((s) => s.moveNote);
  const deleteNote = useEditor((s) => s.deleteNote);
  const readOnly = useEditor((s) => s.readOnly);
  const replies = useEditorShallow((s) => s.replies[note.id] ?? []);
  const author = useEditor((s) => s.authors[note.author_id]);
  const assignee = useEditor((s) => note.assigned_to ? s.authors[note.assigned_to] : null);
  const drag = useRef<{ sx: number; sy: number; ox: number; oy: number; moved: boolean } | null>(null);
  const me = useAuthId();
  const canMove = !readOnly && note.author_id === me;
  const canDelete = !readOnly && note.author_id === me;
  const open = note.requires_action && note.status === 'open';
  
  const [editingHeader, setEditingHeader] = useState(false);
  const [editingFooter, setEditingFooter] = useState(false);
  const [headerText, setHeaderText] = useState('');
  const [footerText, setFooterText] = useState('');

  const handleHeaderClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    setHeaderText(assignee?.name || '');
    setEditingHeader(true);
  };

  const handleFooterClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    setFooterText(author?.name || '');
    setEditingFooter(true);
  };

  const saveHeader = async () => {
    // TODO: Backend API to update note header/receiver field
    setEditingHeader(false);
  };

  const saveFooter = async () => {
    // TODO: Backend API to update note footer/author display name
    setEditingFooter(false);
  };

  const handleDelete = async (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!canDelete) return;
    if (!confirm('Delete this note?')) return;
    try {
      await api.delete(`/notes/${note.id}`);
      deleteNote(note.id);
    } catch (err) {
      alert('Failed to delete note');
    }
  };

  return (
    <button
      className={`pointer-events-auto absolute -translate-x-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-left text-xs shadow-md ${activeNoteId === note.id ? 'ring-2 ring-brand-600' : 'ring-1 ring-black/10'} ${note.status === 'resolved' ? 'opacity-60' : ''}`}
      style={{ left: note.x * width, top: note.y * height, background: note.color, width: 120, touchAction: 'none', cursor: canMove ? 'move' : 'pointer' }}
      aria-label={`Note: ${note.body}`}
      onPointerDown={(e) => { 
        if (!canMove || editingHeader || editingFooter) return;
        (e.target as HTMLElement).setPointerCapture(e.pointerId); 
        drag.current = { sx: e.clientX, sy: e.clientY, ox: note.x, oy: note.y, moved: false }; 
      }}
      onPointerMove={(e) => {
        const d = drag.current; if (!d || !canMove) return;
        const dx = e.clientX - d.sx, dy = e.clientY - d.sy;
        if (Math.abs(dx) + Math.abs(dy) > 3) d.moved = true;
        if (d.moved) moveNote(note.id, note.page_number, clamp(d.ox + dx / width), clamp(d.oy + dy / height));
      }}
      onPointerUp={async () => {
        const d = drag.current; drag.current = null; if (!d) return;
        if (!d.moved) return setActive(note.id);
        if (!canMove) return;
        const n = useEditor.getState().notes[note.id];
        try { await api.patch(`/notes/${note.id}`, { page_number: n.page_number, x: n.x, y: n.y }); }
        catch { moveNote(note.id, note.page_number, d.ox, d.oy); }   // roll back
      }}>
      {open && <span className="absolute -right-1 -top-1 flex h-3 w-3"><span className="absolute inline-flex h-full w-full animate-ping2 rounded-full bg-brand-500" /><span className="relative inline-flex h-3 w-3 rounded-full bg-brand-600" /></span>}
      
      {/* Delete button for author */}
      {canDelete && (
        <button
          className="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-sm"
          onClick={handleDelete}
          title="Delete note"
        >
          ×
        </button>
      )}
      
      {/* Header: Receiver/Assignee - Editable with single click */}
      <div className="mb-1 border-b border-gray-400/30 pb-1 cursor-text" onClick={handleHeaderClick}>
        {editingHeader ? (
          <input
            type="text"
            className="w-full bg-white/80 px-1 text-[11px] font-bold border border-brand-500 rounded"
            value={headerText}
            onChange={(e) => setHeaderText(e.target.value)}
            onBlur={saveHeader}
            onKeyDown={(e) => { if (e.key === 'Enter') saveHeader(); }}
            placeholder="Addressed to"
            autoFocus
            onClick={(e) => e.stopPropagation()}
          />
        ) : (
          <span className="flex items-center gap-1 font-bold text-[11px]">
            {note.classification === 'executive' && <Lock size={11} aria-label="Executive note" />}
            {assignee ? assignee.name : <span className="text-gray-400 italic">Addressed to</span>}
          </span>
        )}
        {!editingHeader && assignee?.title && <span className="text-[9px] text-gray-600">{assignee.title}</span>}
      </div>
      
      {/* Note body */}
      <span className="block truncate text-gray-800">{note.body}</span>
      {note.status === 'resolved' && <div className="mt-1 flex items-center gap-1 text-[10px] text-green-700"><CheckCircle2 size={10} /> Resolved</div>}
      
      {/* Replies */}
      {replies.length > 0 && (
        <div className="mt-2 space-y-1 border-t border-gray-400 pt-1">
          {replies.map((reply) => {
            const replyAuthor = useEditor.getState().authors[reply.author_id];
            return (
              <div key={reply.id} className="text-[10px] text-gray-700">
                <span className="font-bold">{replyAuthor?.name ?? 'Staff'}: </span>
                <span>{reply.body}</span>
              </div>
            );
          })}
        </div>
      )}
      
      {/* Footer: Author - Editable with single click */}
      <div className="mt-1 pt-1 border-t border-gray-400/30 text-[9px] text-gray-600 cursor-text" onClick={handleFooterClick}>
        {editingFooter ? (
          <input
            type="text"
            className="w-full bg-white/80 px-1 text-[9px] border border-brand-500 rounded"
            value={footerText}
            onChange={(e) => setFooterText(e.target.value)}
            onBlur={saveFooter}
            onKeyDown={(e) => { if (e.key === 'Enter') saveFooter(); }}
            placeholder="From"
            autoFocus
            onClick={(e) => e.stopPropagation()}
          />
        ) : (
          <>From: {author?.name ?? <span className="text-gray-400 italic">Staff</span>}</>
        )}
      </div>
    </button>
  );
}

function NoteComposer({ x, y, page, nx, ny }: { x: number; y: number; page: number; nx: number; ny: number }) {
  const memoId = useEditor((s) => s.memoId);
  const routeId = useEditor((s) => s.routeId);
  const setDraft = useEditor((s) => s.setDraft);
  const upsertNote = useEditor((s) => s.upsertNote);
  const setActive = useEditor((s) => s.setActive);
  const [body, setBody] = useState(''); const [req, setReq] = useState(false);
  const [assignee, setAssignee] = useState<UserLite[]>([]); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);

  const save = async () => {
    setBusy(true); setErr('');
    try {
      const { data } = await api.post<Note>(`/memos/${memoId}/notes`, { page_number: page, x: nx, y: ny, body, requires_action: req, assigned_to: assignee[0]?.id ?? null, route_id: routeId });
      upsertNote(data); setActive(data.id); setDraft(null);
    } catch (e) { setErr(errorText(e)); } finally { setBusy(false); }
  };

  return (
    <div className="pointer-events-auto absolute z-30 w-64 rounded-lg border border-surface-line bg-white p-3 shadow-xl" style={{ left: Math.max(8, x - 20), top: y + 8 }}>
      <textarea autoFocus className="field h-20 resize-none" placeholder="Write a note" value={body} onChange={(e) => setBody(e.target.value)} />
      <label className="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" checked={req} onChange={(e) => setReq(e.target.checked)} /> Requires action or reply</label>
      {req && <div className="mt-2"><UserPicker single value={assignee} onChange={setAssignee} placeholder="Assign to (optional)" /></div>}
      {err && <p role="alert" className="mt-2 text-xs text-red-700">{err}</p>}
      <div className="mt-3 flex justify-end gap-2">
        <button className="btn btn-ghost" onClick={() => setDraft(null)}>Cancel</button>
        <button className="btn btn-primary" disabled={!body.trim() || busy} onClick={save}>Pin note</button>
      </div>
    </div>
  );
}

import { useAuth } from '@/features/auth/authStore';
function useAuthId() { return useAuth((s) => s.me?.id); }
const clamp = (v: number) => Math.min(1, Math.max(0, v));
