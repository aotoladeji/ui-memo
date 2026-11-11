import { useEffect, useRef, useState } from 'react';
import { useEditor } from '../store/editorStore';
import { LoadedDoc } from '../store/loadDocument';
import { PdfLayer } from './layers/PdfLayer';
import { DrawLayer } from './layers/DrawLayer';
import { NoteLayer } from './layers/NoteLayer';
import { MinuteLayer } from './layers/MinuteLayer';

/** Scroll container. Pages mount lazily (IntersectionObserver) so 100-page memos stay light. */
export function DocumentStage({ doc, refNo }: { doc: LoadedDoc; refNo?: string }) {
  const scale = useEditor((s) => s.scale);
  const timestamp = new Date().toLocaleString();
  return (
    <div className="flex-1 overflow-auto bg-surface-deep p-4">
      {Array.from({ length: doc.pageCount }, (_, i) => (
        <PageFrame key={i} doc={doc} page={i + 1} w={doc.dims[i].w} h={doc.dims[i].h} scale={scale} refNo={refNo} timestamp={timestamp} />
      ))}
    </div>
  );
}

function PageFrame({ doc, page, w, h, scale, refNo, timestamp }: { doc: LoadedDoc; page: number; w: number; h: number; scale: number; refNo?: string; timestamp: string }) {
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
    <div 
      ref={ref} 
      className="relative mx-auto my-4 bg-white shadow-md" 
      style={{ width: W, height: H }} 
      aria-label={`Page ${page}`}
      data-ref-no={refNo}
      data-timestamp={timestamp}
    >
      {near && (
        <>
          <PdfLayer doc={doc} page={page} width={W} />
          <DrawLayer page={page} pageW={w} pageH={h} scale={scale} />
          <NoteLayer page={page} width={W} height={H} />
          <MinuteLayer page={page} width={W} height={H} />
        </>
      )}
      {/* Footer - visible on screen and in print */}
      {refNo && (
        <div className="absolute bottom-2 left-0 right-0 px-4 text-center text-[10px] text-gray-500 border-t border-gray-200 pt-1 bg-white/80">
          <span className="font-mono">{refNo}</span> • <span>{timestamp}</span> • <span>Page {page} of {doc.pageCount}</span>
        </div>
      )}
    </div>
  );
}
