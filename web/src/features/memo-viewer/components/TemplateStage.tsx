import { useEffect, useRef, useState } from 'react';
import { useEditor } from '../store/editorStore';
import { MemoTemplate, A4, paginate } from '@/shared/memoTemplate';
import { TemplateSheet } from '@/features/memo-template/TemplateSheet';
import { DrawLayer } from './layers/DrawLayer';
import { NoteLayer } from './layers/NoteLayer';

/** Template memo viewer with annotation support */
export function TemplateStage({ template }: { template: MemoTemplate }) {
  const scale = useEditor((s) => s.scale);
  const pages = paginate(template.body);
  
  return (
    <div className="flex-1 overflow-auto bg-surface-deep p-4">
      {pages.map((_, i) => (
        <TemplatePageFrame key={i} template={template} page={i + 1} scale={scale} />
      ))}
    </div>
  );
}

function TemplatePageFrame({ template, page, scale }: { template: MemoTemplate; page: number; scale: number }) {
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
        </>
      )}
    </div>
  );
}
