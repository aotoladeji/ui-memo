import { Switch } from '@/shared/ui';

export interface VisibilityFilterProps {
  includeAnnotations: boolean; onIncludeAnnotations: (v: boolean) => void; annotationCount: number;
  noteMode: 'all' | 'none'; onNoteMode: (m: 'all' | 'none') => void;
  hideExecutive: boolean; onHideExecutive: (v: boolean) => void; executiveCount: number;
}

/** Master switches of the “Annotation & Note Visibility Filter”. */
export function VisibilityFilter(p: VisibilityFilterProps) {
  return (
    <fieldset className="space-y-3 rounded-lg bg-surface p-4">
      <legend className="px-1 text-sm font-bold">What should the recipient see?</legend>
      <Row title="Drawings, highlights, stamps and signatures" hint={`${p.annotationCount} on this memo`} checked={p.includeAnnotations} onChange={p.onIncludeAnnotations} />
      <Row title="Sticky notes and their replies" hint="Turn off to send the document clean" checked={p.noteMode === 'all'} onChange={(v) => p.onNoteMode(v ? 'all' : 'none')} />
      <Row title="Hide executive notes (VC / DVC)" hint={`${p.executiveCount} executive note${p.executiveCount === 1 ? '' : 's'} on this memo`}
        checked={p.hideExecutive} onChange={p.onHideExecutive} disabled={p.noteMode === 'none'} />
    </fieldset>
  );
}

function Row({ title, hint, checked, onChange, disabled }: { title: string; hint: string; checked: boolean; onChange: (v: boolean) => void; disabled?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <div><p className="text-sm font-bold">{title}</p><p className="text-xs text-ink-soft">{hint}</p></div>
      <Switch checked={checked} onChange={onChange} label={title} disabled={disabled} />
    </div>
  );
}
