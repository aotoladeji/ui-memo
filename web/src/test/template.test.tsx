import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from 'react-router-dom';
import { api } from '@/shared/api';
import { formatDate, MemoTemplate, paginate, UNIVERSITY } from '@/shared/memoTemplate';
import { TemplateSheet } from '@/features/memo-template/TemplateSheet';
import { createTestRouter } from '@/app/router';
import { useAuth } from '@/features/auth/authStore';

const as = async (email: string) => { localStorage.setItem('ui_memo_token', (await api.post('/auth/login', { email, password: 'ChangeMe#2026' })).data.token); };
const tpl = (over: object = {}) => ({ to: 'The Deputy Director, MIS', date: '2026-06-25', body: 'I am writing to request access.', ...over });
const create = (over: object = {}, top: object = {}) => api.post('/memos', { kind: 'template', subject: 'Request for access', routing_type: 'peer', page_count: 1, template: tpl(over), ...top });
const png = () => new Blob([new Uint8Array([137, 80, 78, 71])], { type: 'image/png' });

describe('pagination', () => {
  it('always returns at least one page, even for an empty body', () => expect(paginate('')).toHaveLength(1));
  it('keeps a short memo on one page', () => expect(paginate('Hello.\n\nThank you.')).toHaveLength(1));
  it('splits long text across pages without losing any words', () => {
    const body = Array.from({ length: 40 }, (_, i) => `Paragraph ${i} ` + 'word '.repeat(120)).join('\n\n');
    const pages = paginate(body);
    expect(pages.length).toBeGreaterThan(2);
    const words = (t: string) => t.split(/\s+/).filter(Boolean).length;
    expect(pages.flat().reduce((n, p) => n + words(p), 0)).toBe(words(body));
  });
  it('formats dates like the existing memos (MM/DD/YYYY)', () => expect(formatDate('2026-06-25')).toBe('06/25/2026'));
});

describe('template memo API (mock mirrors Laravel)', () => {
  it('takes FROM, department and signature from the account and ignores spoofed values', async () => {
    await as('st001@ui.edu.ng');
    const id = (await create({ from_name: 'Someone Else', department_name: 'Fake Dept' })).data.id;
    const doc = (await api.get(`/memos/${id}`)).data.document;
    expect(doc.kind).toBe('template');
    expect(doc.template).toMatchObject({ from_name: 'Ada Okafor', from_staff_no: 'ST001', department_name: 'Computer Science', thru: '', subject: 'Request for access', signature: { name: 'Ada Okafor', title: 'Systems Officer', unit: 'ICT Unit' } });
  });

  it('requires To and a valid date; THRU is optional', async () => {
    await as('st001@ui.edu.ng');
    await expect(create({ to: '' })).rejects.toMatchObject({ response: { status: 422 } });
    await expect(create({ date: 'yesterday' })).rejects.toMatchObject({ response: { status: 422 } });
    const id = (await create({ thru: 'The Director' })).data.id;
    expect((await api.get(`/memos/${id}`)).data.document.template.thru).toBe('The Director');
  });

  it('rejects the template for accounts with no unit', async () => {
    await as('sa001@ui.edu.ng');
    await expect(create()).rejects.toMatchObject({ response: { status: 422 } });
  });

  it('lets the author edit a draft, but not after sending and not other people', async () => {
    await as('st001@ui.edu.ng');
    const id = (await create()).data.id;
    await api.patch(`/memos/${id}/template`, { subject: 'New subject', page_count: 1, template: tpl({ thru: 'The Director', body: 'Updated.' }) });
    const d = (await api.get(`/memos/${id}`)).data.document.template;
    expect(d).toMatchObject({ subject: 'New subject', thru: 'The Director', body: 'Updated.' });
    await api.post(`/memos/${id}/send`, { recipient_ids: [4] });
    await expect(api.patch(`/memos/${id}/template`, { subject: 'x', template: tpl() })).rejects.toMatchObject({ response: { status: 403 } });
    await as('hd001@ui.edu.ng');
    await expect(api.patch(`/memos/${id}/template`, { subject: 'x', template: tpl() })).rejects.toMatchObject({ response: { status: 403 } });
  });

  it('has no downloadable file for template memos', async () => {
    await as('st001@ui.edu.ng');
    const id = (await create()).data.id;
    await expect(api.get(`/memos/${id}/file`, { responseType: 'arraybuffer' })).rejects.toMatchObject({ response: { status: 404 } });
  });

  it('seeded template memo reaches only its recipient (the Unit Head)', async () => {
    await as('hd001@ui.edu.ng'); expect((await api.get('/memos/3')).data.document.kind).toBe('template');
    await as('st002@ui.edu.ng'); await expect(api.get('/memos/3')).rejects.toMatchObject({ response: { status: 404 } });
  });
});

describe('department logo', () => {
  const dept = (extra: Record<string, string | Blob> = {}) => { const fd = new FormData(); fd.append('faculty_id', '1'); fd.append('name', 'Physics'); fd.append('code', 'PHY'); for (const [k, v] of Object.entries(extra)) fd.append(k, v); return fd; };

  it('SuperAdmin creates a department with a logo; any signed-in user can read it', async () => {
    await as('sa001@ui.edu.ng');
    const id = (await api.post('/admin/departments', dept({ logo: png() }))).data.id;
    const org = (await api.get('/admin/org')).data;
    expect(org[0].departments.find((d: { id: number }) => d.id === id).has_logo).toBe(true);
    await as('st001@ui.edu.ng');
    const blob = (await api.get(`/departments/${id}/logo`, { responseType: 'blob' })).data as Blob;
    expect(blob.type).toBe('image/png'); expect(blob.size).toBe(4);
  });

  it('returns 404 when no logo was uploaded, then accepts an upload later', async () => {
    await as('sa001@ui.edu.ng');
    await expect(api.get('/departments/1/logo', { responseType: 'blob' })).rejects.toMatchObject({ response: { status: 404 } });
    const fd = new FormData(); fd.append('logo', png()); await api.post('/admin/departments/1/logo', fd);
    expect((await api.get('/departments/1/logo', { responseType: 'blob' })).status).toBe(200);
  });

  it('rejects non-images and files over 1 MB; only SuperAdmin creates departments', async () => {
    await as('sa001@ui.edu.ng');
    await expect(api.post('/admin/departments', dept({ logo: new Blob(['x'], { type: 'application/pdf' }) }))).rejects.toMatchObject({ response: { status: 422 } });
    await expect(api.post('/admin/departments', dept({ logo: new Blob([new Uint8Array(1024 * 1024 + 1)], { type: 'image/png' }) }))).rejects.toMatchObject({ response: { status: 422 } });
    await as('st001@ui.edu.ng');
    await expect(api.post('/admin/departments', dept())).rejects.toMatchObject({ response: { status: 403 } });
  });
});

const base: MemoTemplate = { v: 1, department_id: 1, department_name: 'Management Information System [MIS] Unit', office_line: 'Office of the Assistant Director', from_name: 'A.O. Oladeji', from_staff_no: 'PF-28559',
  to: 'The Deputy Director, MIS', thru: '', date: '2026-06-25', subject: 'Request for After-Hours Access', body: 'First paragraph.\n\nSecond paragraph.', signature: { name: 'A.O. Oladeji', title: 'Network Administrator', unit: 'MIS/ITeMS' } };
const wrap = (ui: React.ReactNode) => render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>{ui}</QueryClientProvider>);

describe('TemplateSheet', () => {
  it('shows the fixed university heading, logo, FROM / TO / DATE and signature block', async () => {
    await as('st001@ui.edu.ng');
    wrap(<TemplateSheet t={base} page={1} width={595} />);
    expect(screen.getByText(UNIVERSITY.name)).toBeTruthy();
    expect(screen.getByText(UNIVERSITY.heading)).toBeTruthy();
    expect(screen.getByAltText('University of Ibadan crest').getAttribute('src')).toBe(UNIVERSITY.logo);
    expect(screen.getByText('Management Information System [MIS] Unit')).toBeTruthy();
    expect(screen.getByText('Office of the Assistant Director')).toBeTruthy();
    expect(screen.getByText(/A\.O\. Oladeji \(PF-28559\)/)).toBeTruthy();
    expect(screen.getByText('The Deputy Director, MIS', { exact: false })).toBeTruthy();
    expect(screen.getByText('06/25/2026', { exact: false })).toBeTruthy();
    expect(screen.getByText('First paragraph.')).toBeTruthy();
    expect(screen.getByText('Network Administrator')).toBeTruthy();
    expect(screen.queryByText('THRU', { exact: false })).toBeNull();
  });

  it('shows THRU only when filled, and a placeholder when the department has no logo', async () => {
    await as('st001@ui.edu.ng');
    wrap(<TemplateSheet t={{ ...base, thru: 'The Director, MIS', office_line: '' }} page={1} width={595} />);
    expect(screen.getByText('The Director, MIS', { exact: false })).toBeTruthy();
    expect(screen.getByText('THRU')).toBeTruthy();
    expect(screen.queryByText('Office of the Assistant Director')).toBeNull();
    await waitFor(() => expect(screen.getByLabelText('Department logo placeholder')).toBeTruthy());
  });

  it('continuation pages repeat only a slim header and carry the page number', async () => {
    await as('st001@ui.edu.ng');
    const long = { ...base, body: Array.from({ length: 30 }, () => 'word '.repeat(150)).join('\n\n') };
    wrap(<TemplateSheet t={long} page={2} width={595} />);
    expect(screen.queryByText(UNIVERSITY.heading)).toBeNull();
    expect(screen.getByText(/\(continued\)/)).toBeTruthy();
    expect(screen.getByText(/Page 2 of/)).toBeTruthy();
  });
});

describe('compose page', () => {
  const open = (path = '/memos/new') => { useAuth.setState({ me: null, ready: false }); const r = createTestRouter(path); wrap(<RouterProvider router={r} />); return r; };
  const signIn = async (name: string) => userEvent.click(await screen.findByRole('button', { name: new RegExp(name) }));

  it('offers the template first, previews it live, and keeps the button off until subject and To are filled', async () => {
    open(); await signIn('Ada Okafor');
    expect((await screen.findByRole('tab', { name: /Use the university memo template/i })).getAttribute('aria-selected')).toBe('true');
    expect(screen.getByText(UNIVERSITY.name)).toBeTruthy();                                   // fixed header is already there
    const create = screen.getByRole('button', { name: /Create draft/ }) as HTMLButtonElement;
    expect(create.disabled).toBe(true);
    await userEvent.type(screen.getByLabelText(/^Subject/), 'Leave request');
    await userEvent.type(screen.getByLabelText(/^To/), 'The Registrar');
    expect(create.disabled).toBe(false);
    expect(within(screen.getByLabelText('Memo preview')).getByText('Leave request')).toBeTruthy();   // subject appears in the preview
    expect(within(screen.getByLabelText('Memo preview')).getByText(/The Registrar/)).toBeTruthy();
    expect(within(screen.getByLabelText('Memo preview')).getByText(/Ada Okafor \(ST001\)/)).toBeTruthy();
  });

  it('can switch to uploading a document instead', async () => {
    open(); await signIn('Ada Okafor');
    await userEvent.click(await screen.findByRole('tab', { name: /Upload a document/ }));
    expect(screen.getByLabelText(/^Document/)).toBeTruthy();
    expect(screen.queryByLabelText('Memo preview')).toBeNull();
  });

   it('template mode works for all users in demo mode', async () => {
    open(); await signIn('Super Admin');
    const tab = (await screen.findByRole('tab', { name: /Use the university memo template/i })) as HTMLButtonElement;
    expect(tab.disabled).toBe(false);
    expect(tab.getAttribute('aria-selected')).toBe('true');
    expect(screen.getByText(UNIVERSITY.name)).toBeTruthy();
    expect(within(screen.getByLabelText('Memo preview')).getByText('Computer Science')).toBeTruthy();
    await userEvent.type(screen.getByLabelText(/^Subject/), 'Test');
    await userEvent.type(screen.getByLabelText(/^To/), 'Someone');
    expect((screen.getByRole('button', { name: /Create draft/ }) as HTMLButtonElement).disabled).toBe(false);
  });
});
