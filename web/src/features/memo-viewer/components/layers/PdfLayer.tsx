import { useEffect, useRef } from 'react';
import type { LoadedDoc } from '../../store/loadDocument';

/** Layer 0: the immutable original, rendered by pdf.js. Re-renders only when width changes. */
export function PdfLayer({ doc, page, width }: { doc: LoadedDoc; page: number; width: number }) {
  const ref = useRef<HTMLCanvasElement>(null);
  useEffect(() => {
    let alive = true;
    const t = setTimeout(() => { if (alive && ref.current) doc.render(page, ref.current, width).catch(() => {}); }, 60); // debounce zoom
    return () => { alive = false; clearTimeout(t); };
  }, [doc, page, width]);
  return <canvas ref={ref} className="absolute inset-0 z-0" />;
}
