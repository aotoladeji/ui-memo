import { useEffect, useRef, useState } from 'react';
import { useEditor } from '../store/editorStore';
import { LoadedDoc } from '../store/loadDocument';
import { PdfLayer } from './layers/PdfLayer';
import { DrawLayer } from './layers/DrawLayer';
import { NoteLayer } from './layers/NoteLayer';

/** Scroll container. Pages mount lazily (IntersectionObserver) so 100-page memos stay light. */
export function DocumentStage({ doc }: { doc: LoadedDoc }) {
  const scale = useEditor((s) => s.scale);
  return (
    <div className="flex-1 overflow-auto bg-surface-deep p-4">
      {Array.from({ length: doc.pageCount }, (_, i) => (
        <PageFrame key={i} doc={doc} page={i + 1} w={doc.dims[i].w} h={doc.dims[i].h} scale={scale} />
      ))}
    </div>
  );
}

function PageFrame({ doc, page, w, h, scale }: { doc: LoadedDoc; page: number; w: number; h: number; scale: number }) {
  const ref = useRef<HTMLDivElement>(null);
  const [near, setNear] = useState(page <= 2);
  useEffect(() => {
    const io = new IntersectionObserver(([e]) => e.isIntersecting && setNear(true), { rootMargin: '800px' });
    ref.current && io.observe(ref.current);
    return () => io.disconnect();
  }, []);
  const W = w * scale, H = h * scale;
  // All three layers share ONE coordinate system: page units x scale.
  return (
    <div ref={ref} className="relative mx-auto my-4 bg-white shadow-md" style={{ width: W, height: H }} aria-label={`Page ${page}`}>
      {near && (
        <>
          <PdfLayer doc={doc} page={page} width={W} />
          <DrawLayer page={page} pageW={w} pageH={h} scale={scale} />
          <NoteLayer page={page} width={W} height={H} />
        </>
      )}
    </div>
  );
}
