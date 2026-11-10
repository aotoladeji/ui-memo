import { useEffect, useRef } from 'react';
import * as fabric from 'fabric';
import { useEditor } from '../../store/editorStore';
import { useAuth } from '@/features/auth/authStore';
import { api } from '@/shared/api';
import type { Ann } from '@/shared/types';

const PROPS = ['annId', 'authorId', 'tl'];
let seq = 0;
const newId = () => `c-${Date.now().toString(36)}-${seq++}`;

/**
 * Layer 1: one Fabric canvas per page, kept in page units (scale 1). Zoom uses canvas.setZoom, so stored
 * geometry never depends on the screen. Only the author's own objects are editable.
 */
export function DrawLayer({ page, pageW, pageH, scale }: { page: number; pageW: number; pageH: number; scale: number }) {
  const el = useRef<HTMLCanvasElement>(null);
  const fc = useRef<fabric.Canvas | null>(null);
  const tool = useEditor((s) => s.tool);
  const color = useEditor((s) => s.color);
  const readOnly = useEditor((s) => s.readOnly);
  const me = useAuth((s) => s.me)!;

  // Create once; hydrate this page's saved annotations.
  useEffect(() => {
    const c = new fabric.Canvas(el.current!, { width: pageW * scale, height: pageH * scale, selection: false, preserveObjectStacking: true });
    c.setZoom(scale); fc.current = c;

    const save = (o: fabric.FabricObject, tl: string) => {
      const json = o.toObject(PROPS) as Record<string, unknown>;
      let id = (o as any).annId as string | undefined;
      if (!id) { id = newId(); o.set({ annId: id, authorId: me.id } as any); json.annId = id; json.authorId = me.id; }
      const prev = useEditor.getState().annotations[id];
      const a: Ann = { id, serverId: prev?.serverId, page, tool: tl, authorId: me.id, geometry: { fabric: json, pageW, pageH },
                       style: { color: (o as any).stroke ?? (o as any).fill }, classification: me.is_executive ? 'executive' : 'standard' };
      useEditor.getState().upsertAnn(a);
    };

    (async () => {
      const mine = Object.values(useEditor.getState().annotations).filter((a) => a.page === page);
      for (const a of mine) {
        const g = a.geometry as { fabric: Record<string, unknown> };
        const [obj] = await fabric.util.enlivenObjects<fabric.FabricObject>([g.fabric]);
        const own = a.authorId === me.id && !useEditor.getState().readOnly;
        obj.set({ annId: a.id, authorId: a.authorId, selectable: own, evented: own, hasControls: own } as any);
        c.add(obj);
      }
      c.requestRenderAll();
    })();

    c.on('path:created', (e) => {
      const p = e.path; const hl = useEditor.getState().tool === 'highlight';
      p.set({ selectable: true, ...(hl ? { globalCompositeOperation: 'multiply' } : {}) } as any);
      save(p, hl ? 'highlight' : 'freehand');
    });
    c.on('object:modified', (e) => e.target && (e.target as any).authorId === me.id && save(e.target, useEditor.getState().annotations[(e.target as any).annId]?.tool ?? (e.target as any).tl ?? 'shape'));
    c.on('text:changed', (e) => save(e.target as fabric.FabricObject, 'text'));

    const onKey = (ev: KeyboardEvent) => {
      if (ev.key !== 'Delete' && ev.key !== 'Backspace') return;
      const o = c.getActiveObject(); if (!o || (o as fabric.IText).isEditing) return;
      if ((o as any).authorId !== me.id) return;
      const id = (o as any).annId as string; const sid = useEditor.getState().annotations[id]?.serverId;
      c.remove(o); useEditor.getState().removeAnn(id);
      if (sid) api.delete(`/annotations/${sid}`).catch(() => {});
    };
    window.addEventListener('keydown', onKey);
    return () => { window.removeEventListener('keydown', onKey); c.dispose(); fc.current = null; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, pageW, pageH]);

  // Zoom
  useEffect(() => { const c = fc.current; if (!c) return; c.setDimensions({ width: pageW * scale, height: pageH * scale }); c.setZoom(scale); }, [scale, pageW, pageH]);

  // Tool switching
  useEffect(() => {
    const c = fc.current; if (!c) return;
    const drawing = !readOnly && (tool === 'pen' || tool === 'highlight');
    c.isDrawingMode = drawing; c.selection = false;
    if (drawing) {
      const b = new fabric.PencilBrush(c);
      b.color = tool === 'highlight' ? 'rgba(250, 204, 21, 0.45)' : color;
      b.width = tool === 'highlight' ? 18 : 2.5;
      (b as any).canvas = c; c.freeDrawingBrush = b;
    }
    c.off('mouse:down'); 
    if (!readOnly && (tool === 'text' || tool === 'stamp' || tool === 'signature')) {
      c.on('mouse:down', (e) => {
        if (e.target) return;
        const p = c.getScenePoint(e.e); let obj: fabric.FabricObject;
        if (tool === 'text') obj = new fabric.IText('Type here', { left: p.x, top: p.y, fontSize: 16, fill: color, fontFamily: 'sans-serif' });
        else if (tool === 'signature') obj = new fabric.IText(me.name, { left: p.x, top: p.y, fontSize: 28, fontStyle: 'italic', fontFamily: 'cursive', fill: '#1E1B3A' });
        else {
          const r = new fabric.Rect({ width: 150, height: 44, fill: 'rgba(0,0,0,0)', stroke: '#7C3AED', strokeWidth: 3, rx: 6, ry: 6 });
          const t = new fabric.Text('APPROVED', { fontSize: 22, fontWeight: 'bold', fill: '#7C3AED', left: 14, top: 9 });
          obj = new fabric.Group([r, t], { left: p.x, top: p.y, angle: -6 });
        }
        obj.set({ annId: newId(), authorId: me.id, tl: tool } as any);
        c.add(obj); c.setActiveObject(obj); c.fire('object:modified', { target: obj } as any);
      });
    }
    // Pointer events reach this canvas only for drawing tools; see wrapper class below.
  }, [tool, color, readOnly, me]);

  const active = !readOnly && tool !== 'note';
  return <div className={`absolute inset-0 z-10 ${active ? '' : 'pointer-events-none'}`}><canvas ref={el} /></div>;
}
