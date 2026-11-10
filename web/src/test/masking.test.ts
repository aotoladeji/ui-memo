import { describe, expect, it } from 'vitest';
import { api } from '@/shared/api';

const as = async (email: string) => { localStorage.setItem('ui_memo_token', (await api.post('/auth/login', { email, password: 'ChangeMe#2026' })).data.token); };
const noteIds = async (memo = 1) => (await api.get(`/memos/${memo}`)).data.notes.map((n: { id: number }) => n.id) as number[];
const fwd = (to: number[], extra: object = {}) => api.post('/memos/1/forward', { recipient_ids: to, include_annotations: true, note_mode: 'all', hidden_classifications: [], rules: [], ...extra });
const EXEC = 1, STD = 2;   // seeded VC notes: 1 = executive, 2 = standard

describe('chain-of-custody masking (mock API mirrors Laravel ChainScoped)', () => {
  it('DVC sees both VC notes before forwarding', async () => { await as('dvc01@ui.edu.ng'); expect(await noteIds()).toEqual([EXEC, STD]); });

  it('hides executive notes from the recipient, keeps the original trail for VC and DVC', async () => {
    await as('dvc01@ui.edu.ng'); await fwd([5], { hidden_classifications: ['executive'] });
    await as('st001@ui.edu.ng'); expect(await noteIds()).toEqual([STD]);
    await as('vc001@ui.edu.ng'); expect(await noteIds()).toEqual([EXEC, STD]);
    await as('dvc01@ui.edu.ng'); expect(await noteIds()).toEqual([EXEC, STD]);
  });

  it('explicit include beats the classification mask; explicit exclude beats everything', async () => {
    await as('dvc01@ui.edu.ng');
    await fwd([5], { hidden_classifications: ['executive'], rules: [{ target_type: 'sticky_note', target_id: EXEC, effect: 'include' }, { target_type: 'sticky_note', target_id: STD, effect: 'exclude' }] });
    await as('st001@ui.edu.ng'); expect(await noteIds()).toEqual([EXEC]);
  });

  it('keeps hidden notes hidden on a second forward', async () => {
    await as('dvc01@ui.edu.ng'); await fwd([5], { hidden_classifications: ['executive'] });
    await as('st001@ui.edu.ng'); await fwd([6]);
    await as('st002@ui.edu.ng'); expect(await noteIds()).toEqual([STD]);
  });

  it('refuses rules that reference notes the sender cannot see', async () => {
    await as('dvc01@ui.edu.ng'); await fwd([5], { hidden_classifications: ['executive'] });
    await as('st001@ui.edu.ng');
    await expect(fwd([6], { rules: [{ target_type: 'sticky_note', target_id: EXEC, effect: 'include' }] })).rejects.toMatchObject({ response: { status: 422 } });
  });

  it('sends downstream replies upstream but not to sibling branches', async () => {
    await as('dvc01@ui.edu.ng'); await fwd([5]); await fwd([6]);
    await as('st001@ui.edu.ng');
    const note = (await api.post('/memos/1/notes', { page_number: 1, x: 0.2, y: 0.2, body: 'Done' })).data;
    await as('vc001@ui.edu.ng'); expect(await noteIds()).toContain(note.id);
    await as('st002@ui.edu.ng'); expect(await noteIds()).not.toContain(note.id);
  });

  it('returns 404 for non-recipients and 403 for global broadcast without the flag', async () => {
    await as('st002@ui.edu.ng');
    await expect(api.get('/memos/1')).rejects.toMatchObject({ response: { status: 404 } });
    const fd = new FormData(); fd.append('subject', 'All staff'); fd.append('routing_type', 'global'); fd.append('file', new Blob(['x'], { type: 'application/pdf' }));
    await expect(api.post('/memos', fd)).rejects.toMatchObject({ response: { status: 403 } });
  });

  it('only SuperAdmin toggles the global broadcast flag', async () => {
    await as('dvc01@ui.edu.ng');
    await expect(api.patch('/admin/users/5/permissions', { can_send_global_broadcast: true })).rejects.toMatchObject({ response: { status: 403 } });
    await as('sa001@ui.edu.ng');
    expect((await api.patch('/admin/users/5/permissions', { can_send_global_broadcast: true })).data.can_send_global_broadcast).toBe(true);
  });

  it('blocks Mark done while an assigned action note is open', async () => {
    await as('dvc01@ui.edu.ng');
    await expect(api.post('/memos/1/complete')).rejects.toMatchObject({ response: { status: 409 } });
    await api.patch('/notes/1', { status: 'resolved' });
    expect((await api.post('/memos/1/complete')).data.action_state).toBe('done');
  });
});
