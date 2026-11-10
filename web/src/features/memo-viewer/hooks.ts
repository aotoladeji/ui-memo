import { useEffect } from 'react';
import { api } from '@/shared/api';
import { useEditor } from './store/editorStore';

/** Debounced autosave of dirty canvas annotations (1.5 s). Server returns client_id -> id. */
export function useAutosave() {
  useEffect(() => {
    let busy = false;
    const tick = async () => {
      const s = useEditor.getState();
      if (busy || s.readOnly || s.dirty.size === 0) return;
      busy = true;
      const items = [...s.dirty].map((id) => s.annotations[id]).filter(Boolean).map((a) => ({
        client_id: a.id, id: a.serverId, page_number: a.page, tool: a.tool, geometry: a.geometry, style: a.style, classification: a.classification,
      }));
      try {
        const { data } = await api.put(`/memos/${s.memoId}/annotations`, { items, route_id: s.routeId });
        useEditor.getState().markSaved(data.saved);
      } catch { /* keep dirty; retry next tick */ } finally { busy = false; }
    };
    const t = setInterval(tick, 1500);
    const flush = () => { void tick(); };
    window.addEventListener('beforeunload', flush);
    return () => { clearInterval(t); window.removeEventListener('beforeunload', flush); void tick(); };
  }, []);
}
