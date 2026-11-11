import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCheck, Forward, Send } from 'lucide-react';
import { api, errorText } from '@/shared/api';
import { useAuth } from '@/features/auth/authStore';
import type { Ann, MemoView } from '@/shared/types';
import { useEditor } from './store/editorStore';
import { loadDocument, LoadedDoc } from './store/loadDocument';
import { useAutosave } from './hooks';
import { Toolbar } from './components/Toolbar';
import { DocumentStage } from './components/DocumentStage';
import { TemplateStage } from './components/TemplateStage';
import { NotesAndMinutesPanel } from './components/NotesAndMinutesPanel';
import { ForwardModal } from './components/ForwardModal/ForwardModal';
import { SendDialog } from './components/SendDialog';
import { MemoTemplate, A4 } from '@/shared/memoTemplate';

export default function MemoPage() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const me = useAuth((s) => s.me)!;
  const { data, error, refetch } = useQuery({ queryKey: ['memo', id], queryFn: async () => (await api.get<MemoView>(`/memos/${id}`)).data });
  const [doc, setDoc] = useState<LoadedDoc | null>(null);
  const [fwd, setFwd] = useState(false); const [sendOpen, setSendOpen] = useState(false); const [msg, setMsg] = useState('');

  // Reconstruct full MemoTemplate from stored template data
  const templatePreview: MemoTemplate | null = useMemo(() => {
    if (!data || data.document.kind !== 'template' || !data.document.template) return null;
    const t = data.document.template;
    
    // Find the memo creator to get their department info
    const creator = data.authors[data.memo.created_by];
    
    return {
      v: 1,
      department_id: t.department_id ?? me.department_id ?? 1,
      department_name: t.department_name ?? me.department_name ?? 'Department',
      office_line: t.office_line || '',
      from_name: t.from_name || '',
      from_staff_no: t.from_staff_no || '',
      to: t.to || '',
      thru: t.thru || '',
      date: t.date || '',
      subject: data.memo.subject,
      body: t.body || '',
      signature: {
        name: t.signature_name || '',
        title: t.signature_title || '',
        unit: t.signature_unit || ''
      }
    };
  }, [data, me]);

  useEffect(() => {
    if (!data) return;
    const isDraftAuthor = data.memo.status === 'draft' && data.memo.created_by === me.id;
    const anns: Ann[] = data.annotations.map((a) => ({ id: `s-${a.id}`, serverId: a.id, page: a.page_number, tool: a.tool, authorId: a.author_id, geometry: a.geometry, style: a.style, classification: a.classification }));
    useEditor.getState().init({ 
      memoId: data.memo.id, 
      routeId: data.route.id, 
      readOnly: data.memo.status === 'closed' || (data.memo.status === 'draft' && !isDraftAuthor),
      annotations: anns, 
      notes: data.notes, 
      replies: data.replies, 
      minutes: data.minutes || [],
      minuteReplies: data.minuteReplies || [],
      authors: data.authors 
    });
    
    // Handle template memos differently - they have A4 dimensions
    if (data.document.kind === 'template') {
      // Create virtual page dimensions for template (A4 size in points)
      const pageCount = data.document.page_count || 1;
      const dims = Array(pageCount).fill({ w: A4.w, h: A4.h });
      setDoc({ 
        type: 'template', 
        pageCount, 
        dims,
        pages: []
      } as LoadedDoc);
    } else if (data.document.url) {
      loadDocument(data.document.url, data.document.mime, data.document.page_dims).then(setDoc);
    }
  }, [data, me.id]);

  useAutosave();

  if (error) return <p role="alert" className="p-8 text-red-700">This memo is not available to you. {errorText(error)}</p>;
  if (!data) return (
    <div className="flex h-full items-center justify-center">
      <div className="text-center">
        <div className="mb-3 inline-block h-8 w-8 animate-spin rounded-full border-4 border-brand-200 border-t-brand-600"></div>
        <p className="text-ink-soft">Loading memo...</p>
      </div>
    </div>
  );
  if (!doc) return (
    <div className="flex h-full items-center justify-center">
      <div className="text-center">
        <div className="mb-3 inline-block h-8 w-8 animate-spin rounded-full border-4 border-brand-200 border-t-brand-600"></div>
        <p className="text-ink-soft">Loading document...</p>
      </div>
    </div>
  );

  const isDraft = data.memo.status === 'draft';
  const isAuthor = data.memo.created_by === me.id;
  const isTemplate = data.document.kind === 'template';

  const complete = async () => { try { await api.post(`/memos/${data.memo.id}/complete`); setMsg('Marked as done.'); } catch (e) { setMsg(errorText(e)); } };

  return (
    <div className="flex h-full flex-col">
      {/* Print metadata - hidden in normal view */}
      <div className="hidden print:block mb-4">
        <h1 className="text-2xl font-bold">{data.memo.subject}</h1>
        <p className="text-sm text-gray-600">
          Reference: {data.memo.ref_no} • 
          Date: {new Date().toLocaleString()} •
          Status: {data.memo.status} •
          Priority: {data.memo.priority}
        </p>
      </div>

      <header className="flex flex-wrap items-center gap-3 border-b border-surface-line bg-white px-4 py-3">
        <div className="min-w-0 flex-1">
          <h1 className="truncate text-lg font-bold">{data.memo.subject}</h1>
          <p className="text-xs text-ink-soft">{data.memo.ref_no}{data.memo.priority === 'urgent' ? ' · Urgent' : ''}{isDraft ? ' · Draft' : ''}</p>
        </div>
        {msg && <p role="status" className="text-sm">{msg}</p>}
        <button className="btn btn-ghost" onClick={() => window.print()} title="Print memo">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          Print
        </button>
        {isDraft && isAuthor
          ? <button className="btn btn-primary" onClick={() => setSendOpen(true)}><Send size={16} /> Send</button>
          : <>
              {!isAuthor && <button className="btn btn-ghost" onClick={complete}><CheckCheck size={16} /> Mark done</button>}
              <button className="btn btn-primary" onClick={() => setFwd(true)}><Forward size={16} /> Forward</button>
            </>}
      </header>
      <Toolbar />
      <div className="flex min-h-0 flex-1" data-memo-ref={data.memo.ref_no} data-memo-timestamp={new Date().toLocaleString()}>
        {isTemplate && templatePreview ? (
          <TemplateStage template={templatePreview} refNo={data.memo.ref_no} />
        ) : (
          <DocumentStage doc={doc} refNo={data.memo.ref_no} />
        )}
        <NotesAndMinutesPanel />
      </div>

      <ForwardModal open={fwd} onClose={() => setFwd(false)} onForwarded={() => { setMsg('Forwarded.'); qc.invalidateQueries({ queryKey: ['memos'] }); }} />
      <SendDialog open={sendOpen} onClose={() => setSendOpen(false)} memoId={data.memo.id} routing={data.memo.routing_type}
        onSent={() => { qc.invalidateQueries({ queryKey: ['memos'] }); refetch(); nav('/sent'); }} />
    </div>
  );
}
