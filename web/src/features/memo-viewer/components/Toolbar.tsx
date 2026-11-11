import { Highlighter, MousePointer2, PenLine, Signature, StickyNote, Stamp, Type, ZoomIn, ZoomOut, FileText, Printer } from 'lucide-react';
import { Tool, useEditor } from '../store/editorStore';

const TOOLS: { id: Tool; label: string; Icon: typeof PenLine }[] = [
  { id: 'select', label: 'Select', Icon: MousePointer2 },
  { id: 'pen', label: 'Draw', Icon: PenLine },
  { id: 'highlight', label: 'Highlight', Icon: Highlighter },
  { id: 'text', label: 'Text', Icon: Type },
  { id: 'stamp', label: 'Stamp', Icon: Stamp },
  { id: 'signature', label: 'Sign', Icon: Signature },
  { id: 'note', label: 'Note', Icon: StickyNote },
  { id: 'minute', label: 'Minute', Icon: FileText },
];
const COLORS = ['#7C3AED', '#1E1B3A', '#DC2626', '#059669', '#2563EB'];

export function Toolbar() {
  const tool = useEditor((s) => s.tool);
  const setTool = useEditor((s) => s.setTool);
  const color = useEditor((s) => s.color);
  const setColor = useEditor((s) => s.setColor);
  const scale = useEditor((s) => s.scale);
  const setScale = useEditor((s) => s.setScale);
  const readOnly = useEditor((s) => s.readOnly);
  
  const handlePrint = () => {
    window.print();
  };
  
  return (
    <div className="flex flex-wrap items-center gap-2 border-b border-surface-line bg-white px-4 py-2" role="toolbar" aria-label="Annotation tools">
      {TOOLS.map(({ id, label, Icon }) => (
        <button key={id} disabled={readOnly && id !== 'select'} onClick={() => setTool(id)} aria-pressed={tool === id}
          className={`btn px-3 ${tool === id ? 'bg-brand-600 text-white' : 'btn-ghost'}`}>
          <Icon size={16} /> <span className="hidden md:inline">{label}</span>
        </button>
      ))}
      <span className="mx-2 h-6 w-px bg-surface-line" />
      {COLORS.map((c) => (
        <button key={c} aria-label={`Ink colour ${c}`} aria-pressed={color === c} onClick={() => setColor(c)}
          className={`h-6 w-6 rounded-full ring-offset-2 ${color === c ? 'ring-2 ring-brand-600' : ''}`} style={{ background: c }} />
      ))}
      <span className="ml-auto flex items-center gap-1">
        <button className="btn btn-ghost px-2" aria-label="Print memo" onClick={handlePrint}>
          <Printer size={16} /> <span className="hidden md:inline">Print</span>
        </button>
        <span className="mx-2 h-6 w-px bg-surface-line" />
        <button className="btn btn-ghost px-2" aria-label="Zoom out" onClick={() => setScale(scale - 0.1)}><ZoomOut size={16} /></button>
        <span className="w-12 text-center text-sm tabular-nums">{Math.round(scale * 100)}%</span>
        <button className="btn btn-ghost px-2" aria-label="Zoom in" onClick={() => setScale(scale + 0.1)}><ZoomIn size={16} /></button>
      </span>
    </div>
  );
}
