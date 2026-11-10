import { useState } from 'react';
import { api, errorText } from '@/shared/api';
import { Dialog } from '@/shared/ui';
import { UserPicker } from '@/shared/UserPicker';
import type { UserLite } from '@/shared/types';

const COPY = {
  peer: 'Choose who receives this memo.',
  internal_unit: 'Leave empty to send to everyone in your unit, or choose specific unit members.',
  global: 'This memo goes to all active staff. This cannot be undone.',
} as const;

export function SendDialog({ open, onClose, memoId, routing, onSent }: { open: boolean; onClose: () => void; memoId: number; routing: keyof typeof COPY; onSent: () => void }) {
  const [to, setTo] = useState<UserLite[]>([]); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const send = async () => {
    setBusy(true); setErr('');
    try { await api.post(`/memos/${memoId}/send`, { recipient_ids: to.map((u) => u.id) }); onSent(); onClose(); }
    catch (e) { setErr(errorText(e)); } finally { setBusy(false); }
  };
  return (
    <Dialog open={open} onClose={onClose} title="Send memo">
      <p className="mb-3 text-sm text-ink-soft">{COPY[routing]}</p>
      {routing !== 'global' && <UserPicker value={to} onChange={setTo} />}
      {err && <p role="alert" className="mt-3 text-sm text-red-700">{err}</p>}
      <div className="mt-5 flex justify-end gap-2">
        <button className="btn btn-ghost" onClick={onClose}>Cancel</button>
        <button className="btn btn-primary" disabled={busy || (routing === 'peer' && !to.length)} onClick={send}>{routing === 'global' ? 'Send to all staff' : 'Send memo'}</button>
      </div>
    </Dialog>
  );
}
