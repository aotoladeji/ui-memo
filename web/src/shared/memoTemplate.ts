/** The University of Ibadan memo template: fixed wording, fixed logo, deterministic pagination. */

export const UNIVERSITY = { name: 'UNIVERSITY OF IBADAN, IBADAN, NIGERIA', logo: '/ui_logo.png', heading: 'INTERNAL MEMORANDUM' } as const;
export const A4 = { w: 595, h: 842 } as const;

export interface MemoTemplate {
  v: 1;
  department_id: number | null;
  department_name: string;      // preset from the sender's department
  office_line: string;          // optional, e.g. "Office of the Assistant Director"
  from_name: string;            // preset from the sender's account
  from_staff_no: string;
  to: string;
  thru: string[];               // array of thru recipients, each on separate line
  date: string;                 // ISO yyyy-mm-dd
  subject: string;
  body: string;
  signature: { name: string; title: string; unit: string };
}

export const todayIso = () => new Date().toISOString().slice(0, 10);

/** Displayed as MM/DD/YYYY, matching the existing memo format. */
export function formatDate(iso: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
  return m ? `${m[2]}/${m[3]}/${m[1]}` : iso;
}

// Layout capacity (in body lines) used to split text across A4 pages. Conservative on purpose.
const CHARS_PER_LINE = 82, FIRST_PAGE_LINES = 22, NEXT_PAGE_LINES = 36, SIGNATURE_LINES = 7;
const linesOf = (t: string) => Math.max(1, Math.ceil(t.length / CHARS_PER_LINE)) + 1;   // +1 = gap after paragraph

/** Splits the body into pages of paragraphs. Used for both rendering and the stored page_count. */
export function paginate(body: string): string[][] {
  const paras = body.replace(/\r/g, '').split(/\n+/).map((p) => p.trim()).filter(Boolean);
  const pages: string[][] = [[]];
  let used = 0, cap = FIRST_PAGE_LINES;
  const newPage = () => { pages.push([]); used = 0; cap = NEXT_PAGE_LINES; };
  const place = (t: string) => { pages[pages.length - 1].push(t); used += linesOf(t); };

  for (const p of paras) {
    let rest = p;
    while (rest) {
      const room = cap - used - 1;                                  // lines available for text
      if (room <= 0 && pages[pages.length - 1].length) { newPage(); continue; }
      const maxChars = Math.max(1, room) * CHARS_PER_LINE;
      if (rest.length <= maxChars) { place(rest); break; }
      let cut = rest.lastIndexOf(' ', maxChars);
      if (cut <= 0) cut = maxChars;
      place(rest.slice(0, cut)); rest = rest.slice(cut).trim(); newPage();
    }
  }
  if (used + SIGNATURE_LINES > cap) newPage();                      // keep the signature block on a page
  return pages;
}

export const pageDims = (n: number) => Array.from({ length: n }, () => ({ w: A4.w, h: A4.h }));
export const templatePageCount = (body: string) => paginate(body).length;
