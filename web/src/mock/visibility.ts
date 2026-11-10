import type { DB, DNote, DAnn, DReply, DRoute } from './db';

type Item = DNote | DAnn;

/** Does hop `h` hide `item`? Precedence: explicit exclude > explicit include > mode none > classification mask. */
function hopHides(db: DB, h: DRoute, kind: 'sticky_note' | 'annotation', item: Item): boolean {
  const rule = db.rules.filter((r) => r.route_id === h.id && r.target_type === kind && r.target_id === item.id);
  if (rule.some((r) => r.effect === 'exclude')) return true;
  if (rule.some((r) => r.effect === 'include')) return false;
  if ((kind === 'sticky_note' ? h.note_mode : h.annotation_mode) === 'none') return true;
  return h.hidden_classifications.includes(item.classification);
}

/** Mirror of the Laravel ChainScoped scope. */
export function isVisible(db: DB, kind: 'sticky_note' | 'annotation', item: Item, vpath: string, uid: number): boolean {
  if (item.visibility_scope === 'private' && item.author_id !== uid) return false;
  if (item.author_id === uid) return true;
  const o = db.routes.find((r) => r.id === item.origin_route_id)!;
  if (o.path.startsWith(vpath)) return true;                       // same hop or downstream
  if (vpath.startsWith(o.path))                                    // upstream origin: every later hop must allow it
    return !db.routes.some((h) => h.memo_id === item.memo_id && vpath.startsWith(h.path) && h.depth > o.depth && hopHides(db, h, kind, item));
  return false;                                                    // sibling branch
}

export function viewAt(db: DB, memoId: number, vpath: string, uid: number) {
  const notes = db.notes.filter((n) => n.memo_id === memoId && isVisible(db, 'sticky_note', n, vpath, uid));
  const annotations = db.annotations.filter((a) => a.memo_id === memoId && isVisible(db, 'annotation', a, vpath, uid));
  const ids = new Set(notes.map((n) => n.id));
  const replies: DReply[] = db.replies.filter((rp) => {
    if (!ids.has(rp.note_id)) return false;
    const o = db.routes.find((r) => r.id === rp.origin_route_id)!.path;
    return rp.author_id === uid || o.startsWith(vpath) || vpath.startsWith(o);
  });
  return { notes, annotations, replies };
}
