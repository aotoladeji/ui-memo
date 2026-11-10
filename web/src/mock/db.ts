import { samplePdf } from './pdf';

export type Role = 'super_admin' | 'admin' | 'unit_head' | 'staff';
export type Cls = 'standard' | 'executive' | 'confidential';

export interface DUser { id: number; staff_no: string; name: string; email: string; password: string; role: Role; title: string | null; unit_id: number | null; is_executive: boolean; can_send_global_broadcast: boolean; is_active: boolean }
export interface DMemo { id: number; ref_no: string; subject: string; created_by: number; origin_unit_id: number | null; routing_type: 'internal_unit' | 'peer' | 'global'; priority: string; status: 'draft' | 'sent' | 'closed'; sent_at: string | null }
export interface DDoc { memo_id: number; kind?: 'template' | 'file'; mime: string; page_count: number; page_dims: { w: number; h: number }[] | null; file_key: string | null; template?: any }
export interface DRoute { id: number; memo_id: number; parent_route_id: number | null; depth: number; path: string; kind: 'origin' | 'forward'; from_user_id: number; annotation_mode: 'all' | 'none'; note_mode: 'all' | 'none'; hidden_classifications: string[]; message: string | null }
export interface DRecipient { id: number; memo_id: number; route_id: number; user_id: number; recipient_type: 'author' | 'to'; read_at: string | null; action_state: 'none' | 'pending' | 'done' }
export interface DAnn { id: number; memo_id: number; origin_route_id: number; author_id: number; page_number: number; tool: string; geometry: unknown; style: unknown; classification: Cls; visibility_scope: 'chain' | 'private'; z_index: number }
export interface DNote { id: number; memo_id: number; origin_route_id: number; author_id: number; page_number: number; x: number; y: number; body: string; color: string; classification: Cls; visibility_scope: 'chain' | 'private'; requires_action: boolean; assigned_to: number | null; status: 'open' | 'resolved' }
export interface DReply { id: number; note_id: number; origin_route_id: number; author_id: number; body: string; created_at: string }
export interface DRule { route_id: number; target_type: 'sticky_note' | 'annotation'; target_id: number; effect: 'include' | 'exclude' }
export interface DB {
  seq: Record<string, number>;
  faculties: { id: number; name: string; code: string }[]; departments: { id: number; faculty_id: number; name: string; code: string }[]; units: { id: number; department_id: number; name: string; code: string }[];
  users: DUser[]; memos: DMemo[]; docs: DDoc[]; routes: DRoute[]; recipients: DRecipient[]; annotations: DAnn[]; notes: DNote[]; replies: DReply[]; rules: DRule[];
}

const KEY = 'ui_memo_mock_db_v1';
export const files = new Map<string, ArrayBuffer>();   // uploaded originals live in memory for the session

export function nextId(db: DB, table: string): number { db.seq[table] = (db.seq[table] ?? 0) + 1; return db.seq[table]; }

function seed(): DB {
  const u = (id: number, staff_no: string, name: string, role: Role, unit_id: number | null, title: string, extra: Partial<DUser> = {}): DUser =>
    ({ id, staff_no, name, email: `${staff_no.toLowerCase()}@ui.edu.ng`, password: 'ChangeMe#2026', role, title, unit_id, is_executive: false, can_send_global_broadcast: false, is_active: true, ...extra });
  const db: DB = {
    seq: {},
    faculties: [{ id: 1, name: 'Faculty of Science', code: 'SCI' }],
    departments: [{ id: 1, faculty_id: 1, name: 'Computer Science', code: 'CSC' }],
    units: [{ id: 1, department_id: 1, name: 'Registry', code: 'REG' }, { id: 2, department_id: 1, name: 'ICT Unit', code: 'ICT' }],
    users: [
      u(1, 'SA001', 'System Super Admin', 'super_admin', null, 'Super Administrator'),
      u(2, 'VC001', 'Vice-Chancellor', 'staff', 1, 'Vice-Chancellor', { is_executive: true, can_send_global_broadcast: true }),
      u(3, 'DVC01', 'Deputy Vice-Chancellor (Admin)', 'staff', 1, 'DVC (Administration)', { is_executive: true }),
      u(4, 'HD001', 'ICT Unit Head', 'unit_head', 2, 'Head, ICT Unit'),
      u(5, 'ST001', 'Ada Okafor', 'staff', 2, 'Systems Officer'),
      u(6, 'ST002', 'Tunde Bello', 'staff', 2, 'Network Engineer'),
    ],
    memos: [], docs: [], routes: [], recipients: [], annotations: [], notes: [], replies: [], rules: [],
  };
  const now = new Date().toISOString();
  const mk = (subject: string, by: number, to: number[], body: string[]) => {
    const id = nextId(db, 'memos');
    db.memos.push({ id, ref_no: `UI/REG/2026/${String(id).padStart(4, '0')}`, subject, created_by: by, origin_unit_id: 1, routing_type: 'peer', priority: id === 1 ? 'urgent' : 'normal', status: 'sent', sent_at: now });
    db.docs.push({ memo_id: id, kind: 'file', mime: 'application/pdf', page_count: 2, page_dims: null, file_key: `seed-${id}`, template: undefined });
    files.set(`seed-${id}`, samplePdf(subject, body));
    const rid = nextId(db, 'routes');
    db.routes.push({ id: rid, memo_id: id, parent_route_id: null, depth: 0, path: `/${rid}/`, kind: 'origin', from_user_id: by, annotation_mode: 'all', note_mode: 'all', hidden_classifications: [], message: null });
    for (const [i, uid] of [by, ...to].entries())
      db.recipients.push({ id: nextId(db, 'recipients'), memo_id: id, route_id: rid, user_id: uid, recipient_type: i === 0 ? 'author' : 'to', read_at: null, action_state: 'none' });
    return rid;
  };
  const r1 = mk('Approval of 2027 Research Grant Allocations', 2, [3], [
    'To: Deputy Vice-Chancellor (Administration)', 'From: Vice-Chancellor', '', 'Management has reviewed the proposed allocations for the 2027 research grant cycle.',
    'Please coordinate the release of funds with the Bursary and report back by month end.', '', 'Allocations are summarised in Annex A.']);
  const note = (o: Partial<DNote>): DNote => ({ id: nextId(db, 'notes'), memo_id: 1, origin_route_id: r1, author_id: 2, page_number: 1, x: 0.6, y: 0.3, body: '', color: '#FDE68A', classification: 'executive', visibility_scope: 'chain', requires_action: false, assigned_to: null, status: 'open', ...o });
  db.notes.push(note({ body: 'DVC: keep this between us until the Bursary confirms the figures.', requires_action: true, assigned_to: 3 }));
  db.notes.push(note({ body: 'Line 3 looks high. Please confirm.', x: 0.3, y: 0.55, classification: 'standard' }));
  mk('Staff Welfare Policy Review', 4, [5, 6], ['To: ICT Unit staff', 'From: Head, ICT Unit', '', 'Please read the attached draft policy and add your comments as notes on the document.']);
  return db;
}

function load(): DB {
  try { const raw = localStorage.getItem(KEY); if (raw) { const d = JSON.parse(raw) as DB; ensureFiles(d); return d; } } catch { /* fall through to seed */ }
  const d = seed(); persist(d); return d;
}
/** Seed PDFs are regenerated after a reload; uploaded files (memory only) fall back to a placeholder. */
function ensureFiles(d: DB) {
  for (const doc of d.docs) {
    if (files.has(doc.file_key)) continue;
    const m = d.memos.find((x) => x.id === doc.memo_id)!;
    files.set(doc.file_key, samplePdf(m.subject, doc.file_key.startsWith('seed') ? ['(Demo document)'] : ['Demo mode keeps uploaded files in memory only.', 'Re-upload the file to view the original after a page refresh.']));
    if (!doc.file_key.startsWith('seed')) { doc.mime = 'application/pdf'; doc.page_count = 2; doc.page_dims = null; }
  }
}

let current: DB | null = null;
export const getDb = (): DB => (current ??= load());
export function persist(d: DB = getDb()) { try { localStorage.setItem(KEY, JSON.stringify(d)); } catch { /* quota: ignore */ } }
export function resetDb() { current = null; files.clear(); try { localStorage.removeItem(KEY); } catch { /* noop */ } return getDb(); }
