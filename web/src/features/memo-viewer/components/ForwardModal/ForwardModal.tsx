import { useMemo, useState } from 'react';
import { api, errorText } from '@/shared/api';
import { Dialog } from '@/shared/ui';
import { UserPicker } from '@/shared/UserPicker';
import { useEditor, useEditorShallow } from '../../store/editorStore';
import { useAuth } from '@/features/auth/authStore';
import type { ForwardPayload, Note, UserLite } from '@/shared/types';
import { NoteVisibilityList } from './NoteVisibilityList';
import { VisibilityFilter } from './VisibilityFilter';

export interface ForwardModalProps { open: boolean; onClose: () => void; onForwarded: (forwardId: number) => void }

interface RecipientPreview {
  recipient_id: number;
  recipient?: { id: number; name: string; title?: string | null };
  notes: { id: number }[];
  annotations: { id: number }[];
  replies: { id: number }[];
  minutes?: { id: number }[];
  minute_replies?: { id: number }[];
}

interface Preview {
  notes: { id: number }[];
  annotations: { id: number }[];
  replies: { id: number }[];
  minutes?: { id: number }[];
  minute_replies?: { id: number }[];
  recipients?: RecipientPreview[];
}

export function ForwardModal({ open, onClose, onForwarded }: ForwardModalProps) {
  const me = useAuth((s) => s.me)!;
  const memoId = useEditor((s) => s.memoId);
  const routeId = useEditor((s) => s.routeId);
  const authors = useEditor((s) => s.authors);
  const notes = useEditor((s) => s.notes);
  const replies = useEditor((s) => s.replies);
  const annCount = useEditor((s) => Object.keys(s.annotations).length);
  const noteList = useMemo(() => Object.values(notes).sort((a, b) => a.page_number - b.page_number), [notes]);
  const replyCounts = useMemo(() => Object.fromEntries(Object.entries(replies).map(([k, v]) => [Number(k), v.length])), [replies]);
  const execCount = noteList.filter((n) => n.classification === 'executive').length;

  const [to, setTo] = useState<UserLite[]>([]); const [message, setMessage] = useState('');
  const [includeAnn, setIncludeAnn] = useState(true); const [noteMode, setNoteMode] = useState<'all' | 'none'>('all');
  const [hideExec, setHideExec] = useState(me.is_executive || execCount > 0);
  const [overrides, setOverrides] = useState<Record<number, 'include' | 'exclude'>>({});
  const [preview, setPreview] = useState<Preview | null>(null); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);

  /** Same precedence as the server: explicit override > mode > classification mask. */
  const isVisible = (n: Note) =>
    overrides[n.id] ? overrides[n.id] === 'include' : noteMode === 'none' ? false : !(hideExec && n.classification === 'executive');

  const toggle = (id: number, show: boolean) => { setPreview(null); setOverrides((o) => ({ ...o, [id]: show ? 'include' : 'exclude' })); };

  const payload = (): ForwardPayload => ({
    recipient_ids: to.map((u) => u.id), message: message || undefined, include_annotations: includeAnn, note_mode: noteMode,
    hidden_classifications: hideExec && noteMode === 'all' ? ['executive'] : [],
    rules: Object.entries(overrides).map(([id, effect]) => ({ target_type: 'sticky_note' as const, target_id: Number(id), effect })),
  });

  const run = async (kind: 'preview' | 'send') => {
    setBusy(true); setErr('');
    try {
      const body = { ...payload(), from_route_id: routeId };
      if (kind === 'preview') setPreview((await api.post<Preview>(`/memos/${memoId}/forward-preview`, body)).data);
      else { const { data } = await api.post(`/memos/${memoId}/forward`, body); onForwarded(data.forward_id); onClose(); }
    } catch (e) { setErr(errorText(e)); } finally { setBusy(false); }
  };

  return (
    <Dialog open={open} onClose={onClose} title="Forward memo" wide>
      <div className="space-y-5">
        <section><h3 className="mb-2 text-sm font-bold">To</h3><UserPicker value={to} onChange={(v) => { setTo(v); setPreview(null); }} /></section>
        <section><label className="text-sm font-bold" htmlFor="fwd-msg">Message</label>
          <textarea id="fwd-msg" className="field mt-1 h-16 resize-none" value={message} onChange={(e) => setMessage(e.target.value)} placeholder="Optional instruction for the recipient" /></section>

        <VisibilityFilter includeAnnotations={includeAnn} onIncludeAnnotations={(v) => { setIncludeAnn(v); setPreview(null); }} annotationCount={annCount}
          noteMode={noteMode} onNoteMode={(m) => { setNoteMode(m); setPreview(null); }} hideExecutive={hideExec} onHideExecutive={(v) => { setHideExec(v); setPreview(null); }} executiveCount={execCount} />

        <section>
          <h3 className="mb-2 text-sm font-bold">Choose note by note</h3>
          <NoteVisibilityList notes={noteList} authors={authors} replyCounts={replyCounts} isVisible={isVisible} onToggle={toggle} />
        </section>

        <p className="rounded-lg bg-brand-50 p-3 text-sm">Hidden items stay in the permanent record. The recipient, and anyone they forward to, will not see them.</p>
        {preview && (() => {
          const entries = preview.recipients?.length
            ? preview.recipients
            : [{ recipient_id: to[0]?.id ?? 0, recipient: to[0], notes: preview.notes, annotations: preview.annotations, replies: preview.replies, minutes: preview.minutes, minute_replies: preview.minute_replies }];
          return (
            <div role="status" className="space-y-1.5 rounded-lg border border-surface-line bg-surface p-3 text-sm">
              {entries.map((entry) => {
                const name = entry.recipient?.name || to.find((u) => u.id === entry.recipient_id)?.name || `User #${entry.recipient_id}`;
                const minuteCount = entry.minutes?.length ?? 0;
                const totalReplies = entry.replies.length + (entry.minute_replies?.length ?? 0);
                return (
                  <p key={entry.recipient_id}>
                    <b>{name}</b> will see {entry.notes.length} note{entry.notes.length === 1 ? '' : 's'}, {totalReplies} repl{totalReplies === 1 ? 'y' : 'ies'}, {minuteCount} minute{minuteCount === 1 ? '' : 's'} and {entry.annotations.length} drawing{entry.annotations.length === 1 ? '' : 's'}.
                  </p>
                );
              })}
            </div>
          );
        })()}
        {err && <p role="alert" className="text-sm text-red-700">{err}</p>}
        <div className="flex justify-end gap-2">
          <button className="btn btn-ghost" disabled={!to.length || busy} onClick={() => run('preview')}>Preview as recipient</button>
          <button className="btn btn-primary" disabled={!to.length || busy} onClick={() => run('send')}>{busy ? 'Working…' : 'Forward'}</button>
        </div>
      </div>
    </Dialog>
  );
}
