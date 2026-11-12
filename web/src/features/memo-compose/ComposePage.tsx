import { FormEvent, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FileText, Upload } from 'lucide-react';
import { api, errorText, USE_MOCK } from '@/shared/api';
import { useAuth } from '@/features/auth/authStore';
import { useDocumentTitle } from '@/shared/useDocumentTitle';
import { measureFile } from '@/features/memo-viewer/store/loadDocument';
import { MemoTemplate, pageDims, paginate, templatePageCount, todayIso } from '@/shared/memoTemplate';
import { TemplateFields, TemplateForm } from '@/features/memo-template/TemplateForm';
import { TemplateSheet } from '@/features/memo-template/TemplateSheet';
import { UserPicker } from '@/shared/UserPicker';
import type { UserLite } from '@/shared/types';

type Mode = 'template' | 'upload';

export default function ComposePage() {
  useDocumentTitle('New memo');
  const nav = useNavigate(); const me = useAuth((s) => s.me)!;
  const canTemplate = USE_MOCK || !!me.department_id; // Always enable in demo/presentation mode
  const [mode, setMode] = useState<Mode>('template');
  const [routing, setRouting] = useState<'internal_unit' | 'peer' | 'global'>('peer'); 
  const [priority, setPriority] = useState('normal');
  const [recipients, setRecipients] = useState<UserLite[]>([]);
  const [f, setF] = useState<TemplateFields>({ 
    subject: '', 
    to: '', 
    thru: [],  // Changed to empty array
    date: todayIso(), 
    office_line: '', 
    body: '',
    from_name: me.name,
    from_staff_no: me.staff_no || 'DEMO001',
    signature_name: me.name,
    signature_title: me.title || 'Staff Member',
    signature_unit: me.unit_name || 'ICT Unit'
  });
  const [file, setFile] = useState<File | null>(null);
  const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const canGlobal = USE_MOCK || me.role === 'super_admin' || me.can_send_global_broadcast; // Always enable in demo/presentation mode

  const preview: MemoTemplate = useMemo(() => ({
    v: 1, 
    department_id: me.department_id ?? 1, 
    department_name: me.department_name || 'Computer Science', 
    office_line: f.office_line,
    from_name: f.from_name, 
    from_staff_no: f.from_staff_no, 
    to: f.to, 
    thru: f.thru, 
    date: f.date, 
    subject: f.subject, 
    body: f.body,
    signature: { name: f.signature_name, title: f.signature_title, unit: f.signature_unit },
  }), [f, me]);
  const pages = paginate(f.body).length;

  const submit = async (e: FormEvent) => {
    e.preventDefault(); setBusy(true); setErr('');
    try {
      let id: number;
      if (mode === 'template') {
        const n = templatePageCount(f.body);
        id = (await api.post('/memos', { kind: 'template', subject: f.subject, routing_type: routing, priority, page_count: n, page_dims: pageDims(n),
          template: { 
            department_id: me.department_id ?? 1,
            department_name: me.department_name || 'Computer Science',
            to: f.to, 
            thru: f.thru, 
            date: f.date, 
            office_line: f.office_line, 
            body: f.body,
            from_name: f.from_name,
            from_staff_no: f.from_staff_no,
            signature_name: f.signature_name,
            signature_title: f.signature_title,
            signature_unit: f.signature_unit
          } })).data.id;
      } else {
        if (!file) return;
        const m = await measureFile(file); const fd = new FormData();
        fd.append('subject', f.subject); fd.append('routing_type', routing); fd.append('priority', priority); fd.append('file', file); fd.append('page_count', String(m.pageCount));
        m.dims.forEach((d, i) => { fd.append(`page_dims[${i}][w]`, String(d.w)); fd.append(`page_dims[${i}][h]`, String(d.h)); });
        id = (await api.post('/memos', fd)).data.id;
      }
      nav(`/memos/${id}`);
    } catch (x) { setErr(errorText(x)); } finally { setBusy(false); }
  };

  const tab = (m: Mode, label: string, hint: string, Icon: typeof FileText, disabled?: boolean) => (
    <button type="button" role="tab" aria-selected={mode === m} disabled={disabled} onClick={() => setMode(m)}
      className={`flex-1 rounded-xl border p-4 text-left disabled:opacity-50 ${mode === m ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-600' : 'border-surface-line bg-white hover:border-brand-400'}`}>
      <span className="flex items-center gap-2 font-bold"><Icon size={18} /> {label}</span><span className="text-sm text-ink-soft">{hint}</span>
    </button>
  );

  return (
    <form onSubmit={submit} className="mx-auto max-w-6xl p-4 sm:p-6">
      <h1 className="mb-4 text-xl font-bold">New memo</h1>
      <div role="tablist" aria-label="Memo format" className="mb-5 flex flex-col gap-3 sm:flex-row">
        {tab('template', 'Use the university memo template', 'Official letterhead. Write the text; the header is filled in for you.', FileText)}
        {tab('upload', 'Upload a document', 'PDF or image (up to 20 MB).', Upload)}
      </div>

      <div className={`grid gap-6 ${mode === 'template' ? 'lg:grid-cols-[minmax(0,1fr)_420px]' : 'max-w-xl'}`}>
        <div className="card space-y-4 p-5">
          {mode === 'template' ? <TemplateForm value={f} onChange={setF} /> : (
            <>
              <label className="block text-sm font-bold">Subject<input className="field mt-1" value={f.subject} onChange={(e) => setF({ ...f, subject: e.target.value })} required maxLength={255} /></label>
              <label className="block text-sm font-bold">Document (PDF or image, up to 20 MB)
                <input className="field mt-1" type="file" accept="application/pdf,image/png,image/jpeg" onChange={(e) => setFile(e.target.files?.[0] ?? null)} required /></label>
            </>
          )}
          <div className="grid grid-cols-2 gap-3">
            <label className="block text-sm font-bold">Send to
              <select className="field mt-1" value={routing} onChange={(e) => setRouting(e.target.value as typeof routing)}>
                <option value="peer">Specific people</option><option value="internal_unit">My unit only</option>
                <option value="global" disabled={!canGlobal}>All staff{canGlobal ? '' : ' (not permitted)'}</option>
              </select></label>
            <label className="block text-sm font-bold">Priority
              <select className="field mt-1" value={priority} onChange={(e) => setPriority(e.target.value)}><option value="normal">Normal</option><option value="urgent">Urgent</option></select></label>
          </div>
          
          {routing === 'peer' && (
            <div>
              <label className="mb-1 block text-sm font-bold">Recipients</label>
              <UserPicker value={recipients} onChange={setRecipients} placeholder="Search staff by name or number..." />
              {recipients.length === 0 && (
                <p className="mt-1 text-xs text-ink-soft">Add at least one recipient to send this memo.</p>
              )}
            </div>
          )}
          {err && <p role="alert" className="text-sm text-red-700">{err}</p>}
          <button className="btn btn-primary w-full" disabled={busy || !f.subject.trim() || (mode === 'template' ? !f.to.trim() : !file) || (routing === 'peer' && recipients.length === 0)}>{busy ? 'Creating…' : 'Create draft and annotate'}</button>
        </div>

        {mode === 'template' && (
          <aside aria-label="Memo preview" className="space-y-3">
            <p className="text-sm font-bold">Preview{pages > 1 ? ` (${pages} pages)` : ''}</p>
            {Array.from({ length: pages }, (_, i) => <div key={i} className="overflow-hidden rounded-lg border border-surface-line shadow-sm"><TemplateSheet t={preview} page={i + 1} width={420} /></div>)}
          </aside>
        )}
      </div>
    </form>
  );
}
