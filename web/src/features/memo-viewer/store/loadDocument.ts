import * as pdfjs from 'pdfjs-dist';
import type { PageDim } from '@/shared/types';
import { api } from '@/shared/api';

pdfjs.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString();

export interface LoadedDoc {
  pageCount: number; dims: PageDim[];
  render: (page: number, canvas: HTMLCanvasElement, cssWidth: number) => Promise<void>;
}

/** Loads a memo's original (PDF or image) through the authenticated file endpoint. */
export async function loadDocument(url: string, mime: string, hint: PageDim[] | null): Promise<LoadedDoc> {
  const res = await api.get<ArrayBuffer>(url.replace(/^.*\/api\/v1/, ''), { responseType: 'arraybuffer' });

  if (mime === 'application/pdf') {
    const pdf = await pdfjs.getDocument({ data: new Uint8Array(res.data) }).promise;
    const dims: PageDim[] = [];
    for (let i = 1; i <= pdf.numPages; i++) {
      const vp = (await pdf.getPage(i)).getViewport({ scale: 1 });
      dims.push({ w: vp.width, h: vp.height });
    }
    return {
      pageCount: pdf.numPages, dims,
      render: async (n, canvas, cssWidth) => {
        const page = await pdf.getPage(n);
        const base = page.getViewport({ scale: 1 });
        const dpr = window.devicePixelRatio || 1;
        const vp = page.getViewport({ scale: (cssWidth / base.width) * dpr });
        canvas.width = vp.width; canvas.height = vp.height;
        canvas.style.width = `${cssWidth}px`; canvas.style.height = `${(base.height / base.width) * cssWidth}px`;
        await page.render({ canvasContext: canvas.getContext('2d')!, viewport: vp }).promise;
      },
    };
  }

  const bitmap = await createImageBitmap(new Blob([res.data], { type: mime }));
  const w = hint?.[0]?.w ?? Math.min(bitmap.width, 794);
  const h = hint?.[0]?.h ?? (bitmap.height / bitmap.width) * w;
  return {
    pageCount: 1, dims: [{ w, h }],
    render: async (_n, canvas, cssWidth) => {
      const dpr = window.devicePixelRatio || 1;
      canvas.width = cssWidth * dpr; canvas.height = (h / w) * cssWidth * dpr;
      canvas.style.width = `${cssWidth}px`; canvas.style.height = `${(h / w) * cssWidth}px`;
      canvas.getContext('2d')!.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    },
  };
}

/** Used by the compose page to measure pages before upload. */
export async function measureFile(file: File): Promise<{ pageCount: number; dims: PageDim[] }> {
  if (file.type !== 'application/pdf') {
    const bmp = await createImageBitmap(file);
    const w = Math.min(bmp.width, 794);
    return { pageCount: 1, dims: [{ w, h: (bmp.height / bmp.width) * w }] };
  }
  const pdf = await pdfjs.getDocument({ data: new Uint8Array(await file.arrayBuffer()) }).promise;
  const dims: PageDim[] = [];
  for (let i = 1; i <= pdf.numPages; i++) { const v = (await pdf.getPage(i)).getViewport({ scale: 1 }); dims.push({ w: v.width, h: v.height }); }
  return { pageCount: pdf.numPages, dims };
}
