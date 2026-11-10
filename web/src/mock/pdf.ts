/** Tiny dependency-free PDF writer (Helvetica text only), used for demo documents. */
const esc = (s: string) => s.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');

export function makePdf(pages: { title: string; lines: string[] }[]): ArrayBuffer {
  const objs: string[] = [];
  const font = 3 + pages.length * 2;
  objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
  objs[2] = `<< /Type /Pages /Kids [${pages.map((_, i) => `${3 + i * 2} 0 R`).join(' ')}] /Count ${pages.length} >>`;
  pages.forEach((p, i) => {
    const pn = 3 + i * 2, cn = pn + 1;
    let s = `BT /F1 20 Tf 60 780 Td (${esc(p.title)}) Tj ET\n`;
    let y = 740;
    for (const l of p.lines) { s += `BT /F1 12 Tf 60 ${y} Td (${esc(l)}) Tj ET\n`; y -= 22; }
    objs[pn] = `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents ${cn} 0 R /Resources << /Font << /F1 ${font} 0 R >> >> >>`;
    objs[cn] = `<< /Length ${s.length} >>\nstream\n${s}endstream`;
  });
  objs[font] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

  let out = '%PDF-1.4\n';
  const offs: number[] = [];
  for (let i = 1; i < objs.length; i++) { offs[i] = out.length; out += `${i} 0 obj\n${objs[i]}\nendobj\n`; }
  const xref = out.length;
  out += `xref\n0 ${objs.length}\n0000000000 65535 f \n` + offs.slice(1).map((o) => `${String(o).padStart(10, '0')} 00000 n \n`).join('');
  out += `trailer\n<< /Size ${objs.length} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
  const bytes = new TextEncoder().encode(out);
  return bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) as ArrayBuffer;
}

export const samplePdf = (subject: string, body: string[]) =>
  makePdf([
    { title: subject, lines: body },
    { title: 'Annex A: Summary of figures', lines: ['Item 1   Research equipment      NGN 42,000,000', 'Item 2   Postgraduate support     NGN 18,500,000', 'Item 3   Field work and travel    NGN 9,750,000', '', 'Prepared by the Registry for management review.'] },
  ]);
