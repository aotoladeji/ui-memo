import { useState } from 'react';
import { api, errorText } from '@/shared/api';
import { Dialog } from '@/shared/ui';
import { MemoTemplate, pageDims, templatePageCount } from '@/shared/memoTemplate';
import { TemplateFields, TemplateForm } from '@/features/memo-template/TemplateForm';

/** Edit the text of a draft template memo. Header, FROM and logos stay fixed. */
export function EditTemplateDialog({ open, onClose, memoId, template, onSaved }: { open: boolean; onClose: () => void; memoId: number; template: MemoTemplate; onSaved: () => void }) {
  const [f, setF] = useState<TemplateFields>({ subject: template.subject, to: template.to, thru: template.thru, date: template.date, office_line: template.office_line, body: template.body });
  const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const save = async () => {
    setBusy(true); setErr('');
    try {
      const n = templatePageCount(f.body);
      await api.patch(`/memos/${memoId}/template`, { subject: f.subject, page_count: n, page_dims: pageDims(n), template: { to: f.to, thru: f.thru, date: f.date, office_line: f.office_line, body: f.body } });
      onSaved(); onClose();
    } catch (e) { setErr(errorText(e)); } finally { setBusy(false); }
  };
  return (
    <Dialog open={open} onClose={onClose} title="Edit memo text" wide>
      <TemplateForm value={f} onChange={setF} />
      {err && <p role="alert" className="mt-3 text-sm text-red-700">{err}</p>}
      <div className="mt-5 flex justify-end gap-2">
        <button className="btn btn-ghost" onClick={onClose}>Cancel</button>
        <button className="btn btn-primary" disabled={busy || !f.subject.trim() || !f.to.trim()} onClick={save}>Save changes</button>
      </div>
    </Dialog>
  );
}
