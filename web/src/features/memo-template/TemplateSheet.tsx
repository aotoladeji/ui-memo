import { A4, formatDate, MemoTemplate, paginate, UNIVERSITY } from '@/shared/memoTemplate';
import { useDepartmentLogo } from '@/shared/useDepartmentLogo';

const SERIF = "'Times New Roman', Times, serif";
const SANS = "Calibri, 'Segoe UI', Arial, sans-serif";

/**
 * One A4 page of the University of Ibadan memo template, drawn at 595x842 and scaled to `width`.
 * Fixed: university name, university logo, INTERNAL MEMORANDUM, FROM/TO/DATE layout, divider lines.
 * Preset: department name and logo, FROM. Optional: THRU, office line.
 */
export function TemplateSheet({ t, page, width }: { t: MemoTemplate; page: number; width: number }) {
  const pages = paginate(t.body);
  const first = page === 1, last = page === pages.length;
  const deptLogo = useDepartmentLogo(t.department_id, first);
  const k = width / A4.w;

  return (
    <div style={{ width, height: (A4.h / A4.w) * width, overflow: 'hidden', background: '#fff', position: 'relative' }} data-testid={`template-page-${page}`}>
      <div style={{ width: A4.w, height: A4.h, transform: `scale(${k})`, transformOrigin: 'top left', padding: '40px 56px 56px', boxSizing: 'border-box', color: '#000', fontFamily: SERIF, position: 'absolute', top: 0, left: 0 }}>
        {first ? (
          <>
            <header style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <img src={UNIVERSITY.logo} alt="University of Ibadan crest" width={74} height={82} style={{ objectFit: 'contain', flexShrink: 0 }} />
              <div style={{ flex: 1, textAlign: 'center', lineHeight: 1.25 }}>
                <div style={{ fontSize: 16, fontWeight: 700, textTransform: 'uppercase' }}>{t.department_name}</div>
                <div style={{ fontSize: 11, fontWeight: 700, fontStyle: 'italic' }}>{UNIVERSITY.name}</div>
                {t.office_line && <div style={{ fontSize: 11, fontWeight: 700, fontStyle: 'italic' }}>{t.office_line}</div>}
              </div>
              {deptLogo
                ? <img src={deptLogo} alt={`${t.department_name} logo`} width={74} height={74} style={{ objectFit: 'contain', flexShrink: 0 }} />
                : <div aria-label="Department logo placeholder" style={{ width: 74, height: 74, flexShrink: 0, border: '2px dashed #999', borderRadius: '50%', display: 'grid', placeItems: 'center', fontSize: 9, color: '#777', textAlign: 'center' }}>Department logo</div>}
            </header>

            <div style={{ fontFamily: SANS, fontSize: 12, fontWeight: 700, textAlign: 'center', margin: '22px 0 12px' }}>{UNIVERSITY.heading}</div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', borderBottom: '3px double #000', fontSize: 12, minHeight: 88 }}>
              <div style={{ borderRight: '1px solid #000', padding: '6px 10px 10px 4px' }}>
                <p style={{ margin: 0 }}><b>FROM</b>: {t.from_name}{t.from_staff_no ? ` (${t.from_staff_no})` : ''}</p>
                {t.thru.trim() && <p style={{ margin: '12px 0 0' }}><b>THRU</b>: {t.thru}</p>}
                <p style={{ margin: '12px 0 0' }}><b>DATE</b>: {formatDate(t.date)}</p>
              </div>
              <div style={{ padding: '6px 4px 10px 12px' }}><p style={{ margin: 0 }}><b>TO</b>: {t.to}</p></div>
            </div>

            {t.subject && <p style={{ fontSize: 13, fontWeight: 700, margin: '18px 0 12px', textAlign: 'center' }}>{t.subject}</p>}
          </>
        ) : (
          <p style={{ fontSize: 10, color: '#555', margin: '0 0 14px', paddingBottom: 6, borderBottom: '1px solid #999' }}>{t.subject} (continued)</p>
        )}

        <div style={{ fontSize: 12, lineHeight: '19px', textAlign: 'left' }}>
          {pages[page - 1]?.map((paragraph, i) => {
            // Check if this is a table marker
            const tableMatch = paragraph.match(/^\[TABLE:(.*)\]$/);
            if (tableMatch) {
              try {
                const tableData = JSON.parse(tableMatch[1]);
                return (
                  <table key={i} style={{ width: '100%', borderCollapse: 'collapse', margin: '8px 0', fontSize: 11, border: '1px solid #000' }}>
                    <tbody>
                      {tableData.rows.map((row: string[], rowIndex: number) => (
                        <tr key={rowIndex}>
                          {row.map((cell: string, colIndex: number) => {
                            const Tag = (rowIndex === 0 && tableData.hasHeader) ? 'th' : 'td';
                            return (
                              <Tag
                                key={colIndex}
                                style={{
                                  border: '1px solid #000',
                                  padding: '4px 6px',
                                  textAlign: 'left',
                                  backgroundColor: (rowIndex === 0 && tableData.hasHeader) ? '#f0f0f0' : 'transparent',
                                  fontWeight: (rowIndex === 0 && tableData.hasHeader) ? 700 : 400
                                }}
                              >
                                {cell || '\u00A0'}
                              </Tag>
                            );
                          })}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                );
              } catch (e) {
                return <p key={i} style={{ margin: '0 0 10px' }}>{paragraph}</p>;
              }
            }
            // Regular paragraph
            return <p key={i} style={{ margin: '0 0 10px' }}>{paragraph}</p>;
          })}
        </div>

        {last && (
          <div style={{ marginTop: 26, fontSize: 12, lineHeight: '19px' }}>
            <p style={{ margin: 0 }}>{t.signature.name}</p>
            {t.signature.title && <p style={{ margin: '8px 0 0' }}>{t.signature.title}</p>}
            {t.signature.unit && <p style={{ margin: '8px 0 0' }}>{t.signature.unit}</p>}
          </div>
        )}
        {pages.length > 1 && <div style={{ position: 'absolute', bottom: 22, left: 0, right: 0, textAlign: 'center', fontSize: 10, color: '#555' }}>Page {page} of {pages.length}</div>}
      </div>
    </div>
  );
}
