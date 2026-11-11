import { useEffect, useRef, useState } from 'react';
import { useEditor } from '../store/editorStore';
import { MemoTemplate, A4, paginate } from '@/shared/memoTemplate';
import { TemplateSheet } from '@/features/memo-template/TemplateSheet';
import { DrawLayer } from './layers/DrawLayer';
import { NoteLayer } from './layers/NoteLayer';
import { MinuteLayer } from './layers/MinuteLayer';

/** Template memo viewer with annotation support */
export function TemplateStage({ template, refNo }: { template: MemoTemplate; refNo?: string }) {
  const scale = useEditor((s) => s.scale);
  const pages = paginate(template.body);
  const timestamp = new Date().toLocaleString();
  
  return (
    <div className="flex-1 overflow-auto bg-surface-deep p-4">
      {pages.map((_, i) => (
        <TemplatePageFrame key={i} template={template} page={i + 1} scale={scale} refNo={refNo} timestamp={timestamp} />
      ))}
    </div>
  );
}

function TemplatePageFrame({ template, page, scale, refNo, timestamp }: { template: MemoTemplate; page: number; scale: number; refNo?: string; timestamp: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const [near, setNear] = useState(page <= 2);
  
  useEffect(() => {
    const io = new IntersectionObserver(([e]) => e.isIntersecting && setNear(true), { rootMargin: '800px' });
    ref.current && io.observe(ref.current);
    return () => io.disconnect();
  }, []);
  
  // A4 dimensions in points (same as PDF coordinate system)
  const W = A4.w * scale;
  const H = A4.h * scale;
  
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
          {/* Template content layer */}
          <div className="absolute inset-0 pointer-events-none">
            <TemplateSheet t={template} page={page} width={W} />
          </div>
          
          {/* Annotation layers (same as PDF viewer) */}
          <DrawLayer page={page} pageW={A4.w} pageH={A4.h} scale={scale} />
          <NoteLayer page={page} width={W} height={H} />
          <MinuteLayer page={page} width={W} height={H} />
        </>
      )}
      {/* Footer for print */}
      <div className="hidden print:block absolute bottom-2 left-0 right-0 text-center text-xs text-gray-600 border-t border-gray-300 pt-1">
        {refNo} • {timestamp} • Page {page}
      </div>
    </div>
  );
}
