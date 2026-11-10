// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { samplePdf } from '@/mock/pdf';

describe('demo PDF generator', () => {
  it('produces a valid 2-page A4 PDF that pdf.js can open', async () => {
    const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const doc = await pdfjs.getDocument({ data: new Uint8Array(samplePdf('Test (memo)', ['line one', 'line (two)'])), verbosity: 0 }).promise;
    expect(doc.numPages).toBe(2);
    const vp = (await doc.getPage(1)).getViewport({ scale: 1 });
    expect([Math.round(vp.width), Math.round(vp.height)]).toEqual([595, 842]);
    const text = (await (await doc.getPage(1)).getTextContent()).items.map((i: any) => i.str).join(' ');
    expect(text).toContain('Test (memo)');
  });
});
