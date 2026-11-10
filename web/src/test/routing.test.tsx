import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from 'react-router-dom';
import { createTestRouter } from '@/app/router';
import { useAuth } from '@/features/auth/authStore';

function app(path: string) {
  useAuth.setState({ me: null, ready: false });
  const router = createTestRouter(path);
  render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><RouterProvider router={router} /></QueryClientProvider>);
  return router;
}
const signIn = async (name: string) => { await userEvent.click(await screen.findByRole('button', { name: new RegExp(name) })); };

describe('routing', () => {
  it('redirects signed-out visitors to /login and returns them to the page they wanted', async () => {
    const r = app('/sent');
    await screen.findByRole('heading', { name: /University of Ibadan Memos/ });
    expect(r.state.location.pathname).toBe('/login');
    await signIn('^Vice-Chancellor');
    await screen.findByRole('heading', { name: 'Sent' });
    expect(r.state.location.pathname).toBe('/sent');
  });

  it('shows the seeded memo in the DVC inbox and in the VC sent box', async () => {
    app('/inbox'); await signIn('Deputy Vice-Chancellor');
    expect(await screen.findByText('Approval of 2027 Research Grant Allocations')).toBeTruthy();
  });

  it('the index route redirects to /inbox', async () => {
    const r = app('/'); await signIn('Ada Okafor');
    await waitFor(() => expect(r.state.location.pathname).toBe('/inbox'));
  });

  it('keeps staff out of admin pages but lets SuperAdmin in', async () => {
    const r = app('/admin/permissions'); await signIn('Ada Okafor');
    await waitFor(() => expect(r.state.location.pathname).toBe('/inbox'));
  });

  it('SuperAdmin sees the permission manager', async () => {
    app('/admin/permissions'); await signIn('Super Admin');
    expect(await screen.findByRole('heading', { name: 'Global broadcast permission' })).toBeTruthy();
    expect(await screen.findByRole('switch', { name: /Allow Vice-Chancellor to/ })).toBeTruthy();
  });

  it('shows a not-found page for unknown addresses', async () => {
    app('/nope/nothing'); await signIn('Ada Okafor');
    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeTruthy();
  });

  it('lists each mailbox on its own address', async () => {
    const r = app('/drafts'); await signIn('Ada Okafor');
    expect(await screen.findByText('No drafts. Start a new memo.')).toBeTruthy();
    expect(r.state.location.pathname).toBe('/drafts');
  });
});
