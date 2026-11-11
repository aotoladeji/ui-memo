import { useRef, useState } from 'react';
import { Lock } from 'lucide-react';
import { useEditor, useEditorShallow } from '../../store/editorStore';
import { api, errorText } from '@/shared/api';
import { useAuth } from '@/features/auth/authStore';
import type { Minute } from '@/shared/types';

/** Minute Layer: Text overlays directly on the document (like inline annotations) */
export function MinuteLayer({ page, width, height }: { page: number; width: number; height: number }) {
  const tool = useEditor((s) => s.tool);
  const readOnly = useEditor((s) => s.readOnly);
  const minutes = useEditorShallow((s) => Object.values(s.minutes).filter((m) => m.page_number === page));
  const minuteDraft = useEditor((s) => (s.minuteDraft?.page === page ? s.minuteDraft : null));
  const setMinuteDraft = useEditor((s) => s.setMinuteDraft);
  const placing = !readOnly && tool === 'minute';

  return (
    <div className="absolute inset-0 z-20" style={{ pointerEvents: placing ? 'auto' : 'none', cursor: placing ? 'text' : 'default' }}
      onPointerDown={(e) => {
        if (!placing || e.target !== e.currentTarget) return;
        const r = e.currentTarget.getBoundingClientRect();
        setMinuteDraft({ page, x: (e.clientX - r.left) / width, y: (e.clientY - r.top) / height });
      }}>
      {minutes.map((m) => <MinuteText key={m.id} minute={m} width={width} height={height} />)}
      {minuteDraft && <MinuteComposer x={minuteDraft.x * width} y={minuteDraft.y * height} page={page} nx={minuteDraft.x} ny={minuteDraft.y} />}
    </div>
  );
}

function MinuteText({ minute, width, height }: { minute: Minute; width: number; height: number }) {
  const activeMinuteId = useEditor((s) => s.activeMinuteId);
  const setActiveMinute = useEditor((s) => s.setActiveMinute);
  const moveMinute = useEditor((s) => s.moveMinute);
  const deleteMinute = useEditor((s) => s.deleteMinute);
  const readOnly = useEditor((s) => s.readOnly);
  const minuteReplies = useEditorShallow((s) => s.minuteReplies?.[minute.id] ?? []);
  const author = useEditor((s) => s.authors[minute.author_id]);
  const me = useAuth((s) => s.me)!;
  const isActive = activeMinuteId === minute.id;
  const canMove = !readOnly && minute.author_id === me.id && isActive; // Only draggable when active
  const canDelete = !readOnly && minute.author_id === me.id;
  const drag = useRef<{ sx: number; sy: number; ox: number; oy: number; moved: boolean } | null>(null);
  
  const [editingHeader, setEditingHeader] = useState(false);
  const [editingFooter, setEditingFooter] = useState(false);
  const [headerText, setHeaderText] = useState('');
  const [footerText, setFooterText] = useState('');

  // Color coding based on classification
  const getTextColor = () => {
    if (minute.classification === 'executive') return 'text-amber-700';
    if (minute.classification === 'confidential') return 'text-orange-700';
    return 'text-blue-700';
  };

  const getBgColor = () => {
    if (minute.classification === 'executive') return 'bg-amber-50/80';
    if (minute.classification === 'confidential') return 'bg-orange-50/80';
    return 'bg-blue-50/80';
  };

  const handleHeaderClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    setHeaderText(''); // Receiver - currently no receiver field in minutes
    setEditingHeader(true);
  };

  const handleFooterClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    setFooterText(author?.name || '');
    setEditingFooter(true);
  };

  const saveHeader = async () => {
    // TODO: Backend API to update minute header/receiver field
    setEditingHeader(false);
  };

  const saveFooter = async () => {
    // TODO: Backend API to update minute footer/author display name
    setEditingFooter(false);
  };

  const handleDelete = async (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!canDelete) return;
    if (!confirm('Delete this minute?')) return;
    try {
      await api.delete(`/minutes/${minute.id}`);
      deleteMinute(minute.id);
    } catch (err) {
      alert('Failed to delete minute');
    }
  };

  return (
    <div
      className={`pointer-events-auto absolute ${isActive ? 'ring-2 ring-blue-400 rounded-sm' : ''}`}
      style={{ 
        left: minute.x * width, 
        top: minute.y * height,
        cursor: canMove ? 'move' : 'pointer',
        touchAction: 'none'
      }}
      role="button"
      tabIndex={0}
      aria-label={`Minute by ${author?.name ?? 'Staff'}: ${minute.body}`}
      onPointerDown={(e) => { 
        e.stopPropagation();
        // Don't allow drag while editing
        if (editingHeader || editingFooter) return;
        // First click: activate the minute
        if (!isActive) {
          setActiveMinute(minute.id);
          return;
        }
        // Second interaction: allow dragging if user is author
        if (!canMove) return;
        (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId); 
        drag.current = { sx: e.clientX, sy: e.clientY, ox: minute.x, oy: minute.y, moved: false }; 
      }}
      onPointerMove={(e) => {
        const d = drag.current; 
        if (!d || !canMove) return;
        e.stopPropagation();
        const dx = e.clientX - d.sx, dy = e.clientY - d.sy;
        if (Math.abs(dx) + Math.abs(dy) > 3) d.moved = true;
        if (d.moved) moveMinute(minute.id, minute.page_number, clamp(d.ox + dx / width), clamp(d.oy + dy / height));
      }}
      onPointerUp={async (e) => {
        const d = drag.current; 
        drag.current = null; 
        if (!d) return;
        e.stopPropagation();
        if (!d.moved) return; // Just a click, already handled in onPointerDown
        if (!canMove) return;
        
        // TODO: Backend minutes update API endpoint doesn't exist yet
        // For now, the position is saved locally in the editor state
        // Uncomment when backend endpoint is ready:
        /*
        const m = useEditor.getState().minutes[minute.id];
        try { 
          await api.patch(`/minutes/${minute.id}`, { page_number: m.page_number, x: m.x, y: m.y }); 
        } catch { 
          moveMinute(minute.id, minute.page_number, d.ox, d.oy); // roll back
        }
        */
      }}
    >
      {/* Delete button for author */}
      {canDelete && (
        <button
          className="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-sm z-10"
          style={{ pointerEvents: 'auto' }}
          onClick={handleDelete}
          title="Delete minute"
        >
          ×
        </button>
      )}
      
      {/* Inline text overlay - looks like it's typed directly on the memo */}
      <div 
        className={`${getBgColor()} ${getTextColor()} px-2 py-1 rounded-sm border-l-2 ${minute.classification === 'executive' ? 'border-amber-400' : minute.classification === 'confidential' ? 'border-orange-400' : 'border-blue-400'} shadow-sm`}
      >
        {/* Header: Receiver - Editable with single click */}
        <div 
          className="mb-1 border-b border-current/20 pb-1 cursor-text" 
          style={{ pointerEvents: 'auto' }}
          onClick={handleHeaderClick}
        >
          {editingHeader ? (
            <input
              type="text"
              className="w-full bg-white/90 px-1 text-[9px] font-semibold uppercase tracking-wide border border-blue-500 rounded"
              value={headerText}
              onChange={(e) => setHeaderText(e.target.value)}
              onBlur={saveHeader}
              onKeyDown={(e) => { if (e.key === 'Enter') saveHeader(); }}
              placeholder="Addressed to"
              autoFocus
              onClick={(e) => e.stopPropagation()}
            />
          ) : (
            <div className="flex items-center gap-1">
              {minute.classification === 'executive' && <Lock size={10} className="text-amber-600" aria-label="Executive" />}
              <span className="text-[9px] font-semibold uppercase tracking-wide opacity-75 italic text-gray-500">
                Addressed to
              </span>
            </div>
          )}
        </div>

        {/* Minute body */}
        <div className="text-sm font-medium leading-relaxed whitespace-pre-wrap max-w-md">
          {minute.body}
        </div>

        {/* Replies */}
        {minuteReplies.length > 0 && (
          <div className="mt-2 space-y-1 border-t border-current/20 pt-1">
            {minuteReplies.map((reply) => {
              const replyAuthor = useEditor.getState().authors[reply.author_id];
              return (
                <div key={reply.id} className="text-xs opacity-90">
                  <span className="font-semibold">{replyAuthor?.name ?? 'Staff'}: </span>
                  <span className="whitespace-pre-wrap">{reply.body}</span>
                </div>
              );
            })}
          </div>
        )}

        {/* Footer: Author - Editable with single click */}
        <div 
          className="mt-1 pt-1 border-t border-current/20 text-[9px] opacity-75 cursor-text" 
          style={{ pointerEvents: 'auto' }}
          onClick={handleFooterClick}
        >
          {editingFooter ? (
            <input
              type="text"
              className="w-full bg-white/90 px-1 text-[9px] border border-blue-500 rounded"
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
      </div>
    </div>
  );
}

function MinuteComposer({ x, y, page, nx, ny }: { x: number; y: number; page: number; nx: number; ny: number }) {
  const memoId = useEditor((s) => s.memoId);
  const routeId = useEditor((s) => s.routeId);
  const setMinuteDraft = useEditor((s) => s.setMinuteDraft);
  const addMinute = useEditor((s) => s.addMinute);
  const setActiveMinute = useEditor((s) => s.setActiveMinute);
  const me = useAuth((s) => s.me)!;
  const [body, setBody] = useState('');
  const [classification, setClassification] = useState<'standard' | 'executive' | 'confidential'>('standard');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const save = async () => {
    if (!body.trim()) return;
    setBusy(true);
    setErr('');
    try {
      const color = classification === 'executive' ? '#FEF08A' : classification === 'confidential' ? '#FED7AA' : '#E0E7FF';
      const { data } = await api.post<Minute>('/minutes', { 
        memo_id: memoId, 
        page_number: page, 
        x: nx, 
        y: ny, 
        body, 
        classification,
        color,
        route_id: routeId 
      });
      addMinute(data);
      setActiveMinute(data.id);
      setMinuteDraft(null);
    } catch (e) {
      setErr(errorText(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div 
      className="pointer-events-auto absolute z-30 rounded-lg border-2 border-blue-400 bg-white p-3 shadow-xl" 
      style={{ left: Math.max(8, x), top: y + 8, minWidth: 320 }}
    >
      <div className="mb-2 text-xs font-semibold text-blue-700">Add Minute</div>
      <textarea
        ref={textareaRef}
        autoFocus
        className="field w-full text-sm resize-none"
        placeholder="Type your minute here..."
        value={body}
        rows={3}
        onChange={(e) => setBody(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Escape') {
            setMinuteDraft(null);
          }
        }}
      />
      <div className="mt-2 flex items-center gap-2">
        <select 
          className="field text-xs flex-1" 
          value={classification} 
          onChange={(e) => setClassification(e.target.value as typeof classification)}
        >
          <option value="standard">Standard</option>
          {me.is_executive && <option value="executive">Executive</option>}
          <option value="confidential">Confidential</option>
        </select>
      </div>
      {err && <p role="alert" className="mt-2 text-xs text-red-700">{err}</p>}
      <div className="mt-3 flex justify-end gap-2">
        <button className="btn btn-ghost text-sm" onClick={() => setMinuteDraft(null)}>Cancel</button>
        <button className="btn btn-primary text-sm" disabled={!body.trim() || busy} onClick={save}>
          {busy ? 'Saving...' : 'Add Minute'}
        </button>
      </div>
    </div>
  );
}

const clamp = (v: number) => Math.min(1, Math.max(0, v));
