import { create } from 'zustand';
import { useShallow } from 'zustand/react/shallow';
import type { Ann, AuthorInfo, Note, Reply } from '@/shared/types';

export type Tool = 'select' | 'pen' | 'highlight' | 'text' | 'stamp' | 'signature' | 'note';

export interface NoteDraft { page: number; x: number; y: number }

interface EditorState {
  memoId: number; routeId: number; readOnly: boolean;
  scale: number; tool: Tool; color: string;
  annotations: Record<string, Ann>; dirty: Set<string>;
  notes: Record<number, Note>; replies: Record<number, Reply[]>; authors: Record<string, AuthorInfo>;
  activeNoteId: number | null; draft: NoteDraft | null;

  init: (p: { memoId: number; routeId: number; readOnly: boolean; annotations: Ann[]; notes: Note[]; replies: Reply[]; authors: Record<string, AuthorInfo> }) => void;
  setTool: (t: Tool) => void; setScale: (s: number) => void; setColor: (c: string) => void;
  upsertAnn: (a: Ann) => void; removeAnn: (id: string) => void; markSaved: (map: { client_id: string; id: number }[]) => void;
  upsertNote: (n: Note) => void; moveNote: (id: number, page: number, x: number, y: number) => void;
  addReply: (r: Reply) => void; setActive: (id: number | null) => void; setDraft: (d: NoteDraft | null) => void;
}

export const useEditor = create<EditorState>((set) => ({
  memoId: 0, routeId: 0, readOnly: false, scale: 1, tool: 'select', color: '#7C3AED',
  annotations: {}, dirty: new Set(), notes: {}, replies: {}, authors: {}, activeNoteId: null, draft: null,

  init: (p) => set({
    memoId: p.memoId, routeId: p.routeId, readOnly: p.readOnly, dirty: new Set(),
    annotations: Object.fromEntries(p.annotations.map((a) => [a.id, a])),
    notes: Object.fromEntries(p.notes.map((n) => [n.id, n])),
    replies: p.replies.reduce<Record<number, Reply[]>>((m, r) => ((m[r.note_id] ??= []).push(r), m), {}),
    authors: p.authors, activeNoteId: null, draft: null,
  }),
  setTool: (tool) => set({ tool, draft: null }),
  setScale: (scale) => set({ scale: Math.min(3, Math.max(0.4, scale)) }),
  setColor: (color) => set({ color }),

  upsertAnn: (a) => set((s) => ({ annotations: { ...s.annotations, [a.id]: a }, dirty: new Set(s.dirty).add(a.id) })),
  removeAnn: (id) => set((s) => { const { [id]: _gone, ...rest } = s.annotations; const d = new Set(s.dirty); d.delete(id); return { annotations: rest, dirty: d }; }),
  markSaved: (map) => set((s) => {
    const annotations = { ...s.annotations }; const dirty = new Set(s.dirty);
    for (const m of map) { if (annotations[m.client_id]) annotations[m.client_id] = { ...annotations[m.client_id], serverId: m.id }; dirty.delete(m.client_id); }
    return { annotations, dirty };
  }),

  upsertNote: (n) => set((s) => ({ notes: { ...s.notes, [n.id]: n } })),
  moveNote: (id, page, x, y) => set((s) => ({ notes: { ...s.notes, [id]: { ...s.notes[id], page_number: page, x, y } } })),
  addReply: (r) => set((s) => ({ replies: { ...s.replies, [r.note_id]: [...(s.replies[r.note_id] ?? []), r] } })),
  setActive: (activeNoteId) => set({ activeNoteId }),
  setDraft: (draft) => set({ draft }),
}));

/** Zustand v5: selectors that build new objects/arrays must be shallow-compared. */
export function useEditorShallow<T>(selector: (s: EditorState) => T): T {
  return useEditor(useShallow(selector));
}
