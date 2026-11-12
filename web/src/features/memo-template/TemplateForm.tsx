import { MemoTemplate } from '@/shared/memoTemplate';
import { StaffAutocomplete } from './StaffAutocomplete';
import { TableBuilder } from './TableBuilder';
import { useRef } from 'react';

export type TemplateFields = Omit<Pick<MemoTemplate, 'to' | 'thru' | 'date' | 'office_line' | 'body'>, 'thru'> & { 
  subject: string;
  from_name: string;
  from_staff_no: string;
  signature_name: string;
  signature_title: string;
  signature_unit: string;
  thru: string[];
};

/** The editable parts of the template. Department, logos, lines and the university name are fixed. */
export function TemplateForm({ value, onChange }: { value: TemplateFields; onChange: (v: TemplateFields) => void }) {
  const set = <K extends keyof TemplateFields>(k: K, v: TemplateFields[K]) => onChange({ ...value, [k]: v });
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const insertTable = (tableText: string) => {
    const textarea = textareaRef.current;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const currentText = value.body;
    
    // Insert table marker at cursor position
    const newText = currentText.substring(0, start) + tableText + currentText.substring(end);
    set('body', newText);
    
    // Move cursor after inserted table
    setTimeout(() => {
      textarea.focus();
      const newPosition = start + tableText.length;
      textarea.setSelectionRange(newPosition, newPosition);
    }, 0);
  };

  return (
    <div className="space-y-4">
      <label className="block text-sm font-bold">Subject
        <input className="field mt-1" value={value.subject} onChange={(e) => set('subject', e.target.value)} maxLength={255} required placeholder="e.g. Request for After-Hours Office Access" /></label>
      
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="block text-sm font-bold">From (Name)
          <input className="field mt-1" value={value.from_name} onChange={(e) => set('from_name', e.target.value)} maxLength={255} required placeholder="Your name" /></label>
        <label className="block text-sm font-bold">From (Staff No.)
          <input className="field mt-1" value={value.from_staff_no} onChange={(e) => set('from_staff_no', e.target.value)} maxLength={50} placeholder="e.g. ST001" /></label>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <StaffAutocomplete
          label="To"
          value={value.to}
          onChange={(v) => set('to', v)}
          placeholder="e.g. The Deputy Director, MIS"
          required
        />
        <div>
          <label className="block text-sm font-bold mb-1">Thru (optional)</label>
          <div className="space-y-2">
            {value.thru.map((thruItem, idx) => (
              <div key={idx} className="flex gap-2">
                <StaffAutocomplete
                  label=""
                  value={thruItem}
                  onChange={(v) => {
                    const newThru = [...value.thru];
                    newThru[idx] = v;
                    set('thru', newThru);
                  }}
                  placeholder={idx === 0 ? "e.g. The Head of Department" : "Additional thru"}
                />
                <button
                  type="button"
                  className="btn btn-ghost px-2"
                  onClick={() => {
                    const newThru = value.thru.filter((_, i) => i !== idx);
                    set('thru', newThru);
                  }}
                  title="Remove this thru"
                >
                  ×
                </button>
              </div>
            ))}
            <button
              type="button"
              className="btn btn-ghost text-sm w-full"
              onClick={() => set('thru', [...value.thru, ''])}
            >
              + Add Thru
            </button>
          </div>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <label className="block text-sm font-bold">Date
          <input className="field mt-1" type="date" value={value.date} onChange={(e) => set('date', e.target.value)} required /></label>
        <label className="block text-sm font-bold">Office line <span className="font-normal text-ink-soft">(optional)</span>
          <input className="field mt-1" value={value.office_line} onChange={(e) => set('office_line', e.target.value)} maxLength={255} placeholder="e.g. Office of the Assistant Director" /></label>
      </div>

      <div>
        <div className="mb-1 flex items-center justify-between">
          <label className="text-sm font-bold">Memo text</label>
          <TableBuilder onInsert={insertTable} />
        </div>
        <textarea 
          ref={textareaRef}
          className="field h-56" 
          value={value.body} 
          onChange={(e) => set('body', e.target.value)} 
          maxLength={20000} 
          placeholder="Write the memo here. Leave a blank line between paragraphs." 
        />
      </div>
      
      <fieldset className="space-y-3 rounded-lg border border-surface-line bg-surface-soft p-4">
        <legend className="px-2 text-sm font-bold">Signature Block</legend>
        <label className="block text-sm font-bold">Name
          <input className="field mt-1" value={value.signature_name} onChange={(e) => set('signature_name', e.target.value)} maxLength={255} required placeholder="Your name" /></label>
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block text-sm font-bold">Title
            <input className="field mt-1" value={value.signature_title} onChange={(e) => set('signature_title', e.target.value)} maxLength={255} placeholder="e.g. Network Administrator" /></label>
          <label className="block text-sm font-bold">Unit
            <input className="field mt-1" value={value.signature_unit} onChange={(e) => set('signature_unit', e.target.value)} maxLength={255} placeholder="e.g. MIS/ITeMS" /></label>
        </div>
      </fieldset>
      
      <p className="text-xs text-ink-soft">Department, logos, the university heading and the dividing lines are filled in automatically.</p>
    </div>
  );
}
