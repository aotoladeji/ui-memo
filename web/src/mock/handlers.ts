import { DB, DRoute, DUser, files, getDb, nextId, persist } from './db';
import { viewAt } from './visibility';

export class HttpError extends Error {
  constructor(public status: number, message: string, public errors?: Record<string, string[]>) { super(message); }
}
export interface Ctx { db: DB; me: DUser | null; params: Record<string, string>; query: Record<string, any>; body: any }
type Out = { status: number; data: unknown };
const ok = (data: unknown): Out => ({ status: 200, data });
const created = (data: unknown): Out => ({ status: 201, data });
const noContent = (): Out => ({ status: 204, data: undefined });

const need = (c: Ctx): DUser => { if (!c.me) throw new HttpError(401, 'Unauthenticated.'); return c.me; };
const lite = (u: DUser) => ({ id: u.id, name: u.name, title: u.title, unit_id: u.unit_id });
const shape = (u: DUser) => ({ id: u.id, name: u.name, email: u.email, role: u.role, title: u.title, unit_id: u.unit_id, is_executive: u.is_executive, can_send_global_broadcast: u.can_send_global_broadcast });
const val = (c: Ctx, f: Record<string, string>) => { const errors: Record<string, string[]> = {}; for (const [k, m] of Object.entries(f)) if (c.body?.[k] === undefined || c.body[k] === '' || c.body[k] === null) errors[k] = [m]; if (Object.keys(errors).length) throw new HttpError(422, 'Validation failed.', errors); };

function viewerRoute(c: Ctx, memoId: number, routeId?: number): DRoute {
  const me = need(c);
  const rows = c.db.recipients.filter((r) => r.memo_id === memoId && r.user_id === me.id && (!routeId || r.route_id === routeId));
  if (!rows.length) throw new HttpError(404, 'Not found.');           // no existence leak
  return c.db.routes.find((r) => r.id === rows[rows.length - 1].route_id)!;
}
const routeIdOf = (c: Ctx) => Number(c.body?.route_id ?? c.query?.route_id ?? 0) || undefined;

function assertCanSend(c: Ctx, type: string, ids: number[]) {
  const me = need(c);
  if (type === 'global' && !(me.role === 'super_admin' || me.can_send_global_broadcast)) throw new HttpError(403, 'You do not have global broadcast permission.');
  if (type === 'internal_unit' && ids.some((id) => c.db.users.find((u) => u.id === id)?.unit_id !== me.unit_id)) throw new HttpError(403, 'Internal unit memos can only go to members of your unit.');
  if (type === 'peer' && ids.some((id) => !c.db.users.find((u) => u.id === id && u.is_active))) throw new HttpError(422, 'Unknown or inactive recipient.');
}

function memoShow(c: Ctx) {
  const me = need(c); const id = Number(c.params.id); const db = c.db;
  const memo = db.memos.find((m) => m.id === id); const route = viewerRoute(c, id, routeIdOf(c));
  const v = viewAt(db, id, route.path, me.id);
  const row = db.recipients.filter((r) => r.memo_id === id && r.user_id === me.id && r.route_id === route.id)[0];
  if (row && !row.read_at) { row.read_at = new Date().toISOString(); persist(db); }
  const doc = db.docs.find((d) => d.memo_id === id)!;
  const minutes = db.minutes.filter((m) => m.memo_id === id);
  const minuteReplies = db.minuteReplies.filter((r) => minutes.some((m) => m.id === r.minute_id));
  const authorIds = new Set([...v.notes.map((n) => n.author_id), ...v.replies.map((r) => r.author_id), ...v.annotations.map((a) => a.author_id), ...minutes.map((m) => m.author_id), ...minuteReplies.map((r) => r.author_id)]);
  const authors = Object.fromEntries(db.users.filter((u) => authorIds.has(u.id)).map((u) => [u.id, { id: u.id, name: u.name, title: u.title, is_executive: u.is_executive }]));
  return ok({
    memo: { id: memo!.id, ref_no: memo!.ref_no, subject: memo!.subject, priority: memo!.priority, status: memo!.status, routing_type: memo!.routing_type, created_by: memo!.created_by },
    route: { id: route.id, kind: route.kind, depth: route.depth },
    document: { 
      url: doc.kind === 'template' ? null : `/api/v1/memos/${id}/file`, 
      mime: doc.mime, 
      page_count: doc.page_count, 
      page_dims: doc.page_dims,
      kind: doc.kind,
      template: doc.kind === 'template' ? doc.template : undefined
    },
    annotations: v.annotations, notes: v.notes, replies: v.replies, minutes, minuteReplies, authors,
  });
}

function forward(c: Ctx, dry: boolean) {
  const me = need(c); const db = c.db; const memoId = Number(c.params.id); const b = c.body;
  const parent = viewerRoute(c, memoId, Number(b.from_route_id) || undefined);
  const ids: number[] = [...new Set<number>((b.recipient_ids ?? []).map(Number))];
  if (!ids.length) throw new HttpError(422, 'Choose at least one recipient.');
  assertCanSend(c, 'peer', ids);
  const seen = viewAt(db, memoId, parent.path, me.id);
  for (const r of b.rules ?? []) {
    const pool = r.target_type === 'sticky_note' ? seen.notes : seen.annotations;
    if (!pool.some((x) => x.id === Number(r.target_id))) throw new HttpError(422, 'Invalid visibility target.');
  }
  const id = nextId(db, 'routes');
  const route: DRoute = { id, memo_id: memoId, parent_route_id: parent.id, depth: parent.depth + 1, path: `${parent.path}${id}/`, kind: 'forward', from_user_id: me.id,
    annotation_mode: b.include_annotations ? 'all' : 'none', note_mode: b.note_mode ?? 'all', hidden_classifications: b.hidden_classifications ?? [], message: b.message ?? null };
  db.routes.push(route);
  const rules = (b.rules ?? []).map((r: any) => ({ route_id: id, target_type: r.target_type, target_id: Number(r.target_id), effect: r.effect }));
  db.rules.push(...rules);
  if (dry) {
    const v = viewAt(db, memoId, route.path, ids[0]);
    db.routes.pop(); db.rules.splice(db.rules.length - rules.length, rules.length);
    return ok({ notes: v.notes, annotations: v.annotations, replies: v.replies });
  }
  for (const uid of ids) db.recipients.push({ id: nextId(db, 'recipients'), memo_id: memoId, route_id: id, user_id: uid, recipient_type: 'to', read_at: null, action_state: 'none' });
  persist(db);
  return created({ forward_id: id });
}

type H = (c: Ctx) => Out | Promise<Out>;
export const routes: { method: string; re: RegExp; keys: string[]; h: H }[] = [];
function on(method: string, path: string, h: H) {
  const keys: string[] = [];
  const re = new RegExp('^' + path.replace(/:(\w+)/g, (_, k) => { keys.push(k); return '([^/]+)'; }) + '/?$');
  routes.push({ method, re, keys, h });
}

on('post', '/auth/login', (c) => {
  val(c, { email: 'Enter your email.', password: 'Enter your password.' });
  const u = c.db.users.find((x) => x.email === c.body.email && x.is_active);
  if (!u || u.password !== c.body.password) throw new HttpError(422, 'Email or password is incorrect.');
  return ok({ token: `mock-${u.id}`, user: shape(u) });
});
on('get', '/auth/me', (c) => ok(shape(need(c))));
on('post', '/auth/logout', () => noContent());
on('get', '/users/search', (c) => {
  const me = need(c); const q = String(c.query.q ?? '').toLowerCase();
  return ok(c.db.users.filter((u) => u.is_active && u.id !== me.id && u.role !== 'super_admin' && (u.name.toLowerCase().includes(q) || u.staff_no.toLowerCase().startsWith(q))).slice(0, 20).map(lite));
});

on('get', '/memos', (c) => {
  const me = need(c); const box = c.query.box ?? 'inbox'; const db = c.db;
  const held = new Set(db.recipients.filter((r) => r.user_id === me.id).map((r) => r.memo_id));
  const rows = db.memos.filter((m) => held.has(m.id) && (box === 'drafts' ? m.status === 'draft' && m.created_by === me.id : box === 'sent' ? m.status !== 'draft' && m.created_by === me.id : m.status !== 'draft' && m.created_by !== me.id))
    .sort((a, b) => b.id - a.id).map((m) => {
      const a = db.users.find((u) => u.id === m.created_by)!;
      const unread = db.recipients.some((r) => r.memo_id === m.id && r.user_id === me.id && !r.read_at && m.created_by !== me.id);
      return { id: m.id, ref_no: m.ref_no, subject: m.subject, priority: m.priority, status: m.status, sent_at: m.sent_at, unread, author: { id: a.id, name: a.name, title: a.title } };
    });
  return ok({ data: rows });
});
on('post', '/memos', (c) => {
  const me = need(c); const db = c.db;
  
  // Check if this is a template memo (JSON) or file upload (FormData)
  const isTemplate = c.body && typeof c.body === 'object' && c.body.kind === 'template';
  
  if (isTemplate) {
    // Template memo
    const { subject, routing_type, priority, page_count, page_dims, template } = c.body;
    if (!subject || !template) throw new HttpError(422, 'Validation failed.');
    
    const id = nextId(db, 'memos');
    db.memos.push({ 
      id, 
      ref_no: `UI/${me.unit_id === 2 ? 'ICT' : 'REG'}/2026/${String(id).padStart(4, '0')}`, 
      subject, 
      created_by: me.id, 
      origin_unit_id: me.unit_id, 
      routing_type: routing_type || 'peer', 
      priority: priority || 'normal', 
      status: 'draft', 
      sent_at: null 
    });
    
    // Store template data
    db.docs.push({ 
      memo_id: id, 
      kind: 'template',
      mime: 'application/json', 
      page_count: page_count || 1, 
      page_dims: page_dims || null, 
      template,
      file_key: null 
    });
    
    const rid = nextId(db, 'routes');
    db.routes.push({ 
      id: rid, 
      memo_id: id, 
      parent_route_id: null, 
      depth: 0, 
      path: `/${rid}/`, 
      kind: 'origin', 
      from_user_id: me.id, 
      annotation_mode: 'all', 
      note_mode: 'all', 
      hidden_classifications: [], 
      message: null 
    });
    
    db.recipients.push({ 
      id: nextId(db, 'recipients'), 
      memo_id: id, 
      route_id: rid, 
      user_id: me.id, 
      recipient_type: 'author', 
      read_at: null, 
      action_state: 'none' 
    });
    
    persist(db);
    return created({ id, ref_no: db.memos.find((m) => m.id === id)!.ref_no });
  } else {
    // File upload memo
    const fd = c.body as FormData;
    const subject = String(fd.get('subject') ?? ''); 
    const routing = String(fd.get('routing_type') ?? 'peer'); 
    const file = fd.get('file');
    
    if (!subject || !(file instanceof Blob)) throw new HttpError(422, 'Validation failed.', { file: ['Choose a document.'] });
    if (routing === 'global') assertCanSend(c, 'global', []);
    
    const id = nextId(db, 'memos'); 
    const dims: { w: number; h: number }[] = [];
    for (let i = 0; fd.get(`page_dims[${i}][w]`); i++) {
      dims.push({ w: Number(fd.get(`page_dims[${i}][w]`)), h: Number(fd.get(`page_dims[${i}][h]`)) });
    }
    
    const key = `up-${id}`;
    return (async () => {
      files.set(key, await file.arrayBuffer());
      db.memos.push({ 
        id, 
        ref_no: `UI/${me.unit_id === 2 ? 'ICT' : 'REG'}/2026/${String(id).padStart(4, '0')}`, 
        subject, 
        created_by: me.id, 
        origin_unit_id: me.unit_id, 
        routing_type: routing as any, 
        priority: String(fd.get('priority') ?? 'normal'), 
        status: 'draft', 
        sent_at: null 
      });
      
      db.docs.push({ 
        memo_id: id, 
        kind: 'file',
        mime: file.type || 'application/pdf', 
        page_count: Number(fd.get('page_count') ?? 1), 
        page_dims: dims.length ? dims : null, 
        file_key: key,
        template: undefined
      });
      
      const rid = nextId(db, 'routes');
      db.routes.push({ 
        id: rid, 
        memo_id: id, 
        parent_route_id: null, 
        depth: 0, 
        path: `/${rid}/`, 
        kind: 'origin', 
        from_user_id: me.id, 
        annotation_mode: 'all', 
        note_mode: 'all', 
        hidden_classifications: [], 
        message: null 
      });
      
      db.recipients.push({ 
        id: nextId(db, 'recipients'), 
        memo_id: id, 
        route_id: rid, 
        user_id: me.id, 
        recipient_type: 'author', 
        read_at: null, 
        action_state: 'none' 
      });
      
      persist(db);
      return created({ id, ref_no: db.memos.find((m) => m.id === id)!.ref_no });
    })();
  }
});
on('get', '/memos/:id', memoShow);
on('get', '/memos/:id/file', (c) => {
  viewerRoute(c, Number(c.params.id));
  const doc = c.db.docs.find((d) => d.memo_id === Number(c.params.id))!;
  return ok(files.get(doc.file_key)!);
});
on('post', '/memos/:id/send', (c) => {
  const me = need(c); const db = c.db; const id = Number(c.params.id);
  const memo = db.memos.find((m) => m.id === id);
  if (!memo || memo.created_by !== me.id || memo.status !== 'draft') throw new HttpError(403, 'Only the author can send a draft.');
  const picked: number[] = (c.body?.recipient_ids ?? []).map(Number);
  assertCanSend(c, memo.routing_type, picked);
  let targets = picked;
  if (memo.routing_type === 'global') targets = db.users.filter((u) => u.is_active && u.id !== me.id).map((u) => u.id);
  if (memo.routing_type === 'internal_unit' && !picked.length) targets = db.users.filter((u) => u.is_active && u.id !== me.id && u.unit_id === me.unit_id).map((u) => u.id);
  if (memo.routing_type === 'peer' && !picked.length) throw new HttpError(422, 'Choose at least one recipient.');
  const route = db.routes.find((r) => r.memo_id === id && r.kind === 'origin')!;
  for (const uid of targets) if (!db.recipients.some((r) => r.route_id === route.id && r.user_id === uid))
    db.recipients.push({ id: nextId(db, 'recipients'), memo_id: id, route_id: route.id, user_id: uid, recipient_type: 'to', read_at: null, action_state: 'none' });
  memo.status = 'sent'; memo.sent_at = new Date().toISOString(); persist(db);
  return ok({ status: 'sent' });
});
on('post', '/memos/:id/complete', (c) => {
  const me = need(c); const id = Number(c.params.id); const route = viewerRoute(c, id);
  const v = viewAt(c.db, id, route.path, me.id);
  const open = v.notes.filter((n) => n.requires_action && n.status === 'open' && n.assigned_to === me.id).length;
  if (open) throw new HttpError(409, `Resolve ${open} pending action note(s) first.`);
  c.db.recipients.filter((r) => r.memo_id === id && r.user_id === me.id).forEach((r) => (r.action_state = 'done')); persist(c.db);
  return ok({ action_state: 'done' });
});

on('put', '/memos/:id/annotations', (c) => {
  const me = need(c); const id = Number(c.params.id); const route = viewerRoute(c, id, routeIdOf(c)); const saved: { client_id: string; id: number }[] = [];
  for (const it of c.body.items ?? []) {
    const payload = { page_number: it.page_number, tool: it.tool, geometry: it.geometry, style: it.style ?? null, z_index: it.z_index ?? 0, classification: it.classification ?? (me.is_executive ? 'executive' : 'standard') };
    let a = it.id ? c.db.annotations.find((x) => x.id === it.id && x.memo_id === id && x.author_id === me.id) : undefined;
    if (it.id && !a) throw new HttpError(404, 'Not found.');
    if (a) Object.assign(a, payload);
    else { a = { id: nextId(c.db, 'annotations'), memo_id: id, origin_route_id: route.id, author_id: me.id, visibility_scope: 'chain', ...payload }; c.db.annotations.push(a); }
    saved.push({ client_id: it.client_id, id: a.id });
  }
  persist(c.db); return ok({ saved });
});
on('delete', '/annotations/:id', (c) => {
  const me = need(c); const i = c.db.annotations.findIndex((a) => a.id === Number(c.params.id));
  if (i < 0 || c.db.annotations[i].author_id !== me.id) throw new HttpError(403, 'Forbidden.');
  c.db.annotations.splice(i, 1); persist(c.db); return noContent();
});

on('post', '/memos/:id/notes', (c) => {
  const me = need(c); const id = Number(c.params.id); const route = viewerRoute(c, id, routeIdOf(c)); const b = c.body;
  val(c, { body: 'Write a note.', page_number: 'Page is required.' });
  if (b.assigned_to && !c.db.recipients.some((r) => r.memo_id === id && r.user_id === Number(b.assigned_to))) throw new HttpError(422, 'Assignee does not have this memo.');
  const n = { id: nextId(c.db, 'notes'), memo_id: id, origin_route_id: route.id, author_id: me.id, page_number: Number(b.page_number), x: Number(b.x), y: Number(b.y), body: String(b.body), color: b.color ?? '#FDE68A',
    classification: b.classification ?? (me.is_executive ? 'executive' : 'standard'), visibility_scope: b.visibility_scope ?? 'chain', requires_action: !!b.requires_action, assigned_to: b.assigned_to ? Number(b.assigned_to) : null, status: 'open' as const };
  c.db.notes.push(n); persist(c.db); return created(n);
});
on('patch', '/notes/:id', (c) => {
  const me = need(c); const n = c.db.notes.find((x) => x.id === Number(c.params.id));
  if (!n) throw new HttpError(404, 'Not found.');
  const route = viewerRoute(c, n.memo_id, routeIdOf(c));
  if (!viewAt(c.db, n.memo_id, route.path, me.id).notes.some((x) => x.id === n.id)) throw new HttpError(404, 'Not found.');
  const b = c.body; const isAuthor = n.author_id === me.id;
  const layout = ['page_number', 'x', 'y', 'body', 'requires_action'].filter((k) => b[k] !== undefined);
  if (layout.length && !isAuthor) throw new HttpError(403, 'Only the author can edit this note.');
  for (const k of layout) (n as any)[k] = b[k];
  if (b.status) { if (!isAuthor && n.assigned_to !== me.id) throw new HttpError(403, 'Only the author or assignee can resolve.'); n.status = b.status; }
  persist(c.db); return ok(n);
});
on('post', '/notes/:id/replies', (c) => {
  const me = need(c); const n = c.db.notes.find((x) => x.id === Number(c.params.id));
  if (!n) throw new HttpError(404, 'Not found.');
  const route = viewerRoute(c, n.memo_id, routeIdOf(c));
  if (!viewAt(c.db, n.memo_id, route.path, me.id).notes.some((x) => x.id === n.id)) throw new HttpError(404, 'Not found.');
  val(c, { body: 'Write a reply.' });
  const rp = { id: nextId(c.db, 'replies'), note_id: n.id, origin_route_id: route.id, author_id: me.id, body: String(c.body.body), created_at: new Date().toISOString() };
  c.db.replies.push(rp); persist(c.db); return created(rp);
});
on('post', '/minutes', (c) => {
  const me = need(c); const id = Number(c.body.memo_id); const route = viewerRoute(c, id, routeIdOf(c));
  const b = c.body;
  const m = { 
    id: nextId(c.db, 'minutes'), 
    memo_id: id, 
    origin_route_id: route.id, 
    author_id: me.id, 
    page_number: Number(b.page_number), 
    x: Number(b.x), 
    y: Number(b.y), 
    body: String(b.body), 
    color: b.color ?? '#E0E7FF',
    classification: b.classification ?? 'standard',
    created_at: new Date().toISOString()
  };
  c.db.minutes.push(m); 
  persist(c.db); 
  return created(m);
});
on('post', '/minutes/:id/replies', (c) => {
  const me = need(c); const m = c.db.minutes.find((x) => x.id === Number(c.params.id));
  if (!m) throw new HttpError(404, 'Not found.');
  val(c, { body: 'Write a reply.' });
  const rp = { 
    id: nextId(c.db, 'minuteReplies'), 
    minute_id: m.id, 
    origin_route_id: m.origin_route_id, 
    author_id: me.id, 
    body: String(c.body.body), 
    created_at: new Date().toISOString() 
  };
  c.db.minuteReplies.push(rp); 
  persist(c.db); 
  return created(rp);
});
on('post', '/memos/:id/forward', (c) => forward(c, false));
on('post', '/memos/:id/forward-preview', (c) => forward(c, true));

const admin = (c: Ctx, only?: 'super_admin') => { const me = need(c); if (!(me.role === 'super_admin' || (!only && me.role === 'admin'))) throw new HttpError(403, 'Forbidden.'); return me; };
on('get', '/admin/org', (c) => { admin(c); return ok(c.db.faculties.map((f) => ({ ...f, departments: c.db.departments.filter((d) => d.faculty_id === f.id).map((d) => ({ ...d, units: c.db.units.filter((u) => u.department_id === d.id) })) }))); });
on('get', '/admin/users', (c) => {
  admin(c); const q = String(c.query.q ?? '').toLowerCase();
  return ok({ data: c.db.users.filter((u) => !q || u.name.toLowerCase().includes(q) || u.staff_no.toLowerCase().startsWith(q)).map((u) => ({ ...shape(u), staff_no: u.staff_no, is_active: u.is_active, unit: c.db.units.find((x) => x.id === u.unit_id) ?? undefined })) });
});
on('patch', '/admin/users/:id/permissions', (c) => {
  admin(c, 'super_admin'); const u = c.db.users.find((x) => x.id === Number(c.params.id));
  if (!u) throw new HttpError(404, 'Not found.');
  u.can_send_global_broadcast = !!c.body.can_send_global_broadcast; persist(c.db);
  return ok({ id: u.id, name: u.name, can_send_global_broadcast: u.can_send_global_broadcast });
});

export function dispatch(method: string, url: string, query: Record<string, any>, body: any, token: string | null): Promise<Out> | Out {
  const path = url.replace(/^.*\/api\/v1/, '');
  for (const r of routes) {
    if (r.method !== method) continue;
    const m = r.re.exec(path); if (!m) continue;
    const db = getDb(); const uid = token?.startsWith('mock-') ? Number(token.slice(5)) : 0;
    const me = db.users.find((u) => u.id === uid && u.is_active) ?? null;
    return r.h({ db, me, params: Object.fromEntries(r.keys.map((k, i) => [k, m[i + 1]])), query, body });
  }
  throw new HttpError(404, `Mock API has no route for ${method.toUpperCase()} ${path}`);
}
