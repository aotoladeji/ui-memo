import { FormEvent, useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from './authStore';
import { errorText, USE_MOCK } from '@/shared/api';
import { useDocumentTitle } from '@/shared/useDocumentTitle';

const DEMO = [
  ['vc001@ui.edu.ng', 'Vice-Chancellor', 'Executive, can broadcast'], ['dvc01@ui.edu.ng', 'Deputy Vice-Chancellor', 'Executive'],
  ['hd001@ui.edu.ng', 'ICT Unit Head', 'Unit Head'], ['st001@ui.edu.ng', 'Ada Okafor', 'Staff'],
  ['st002@ui.edu.ng', 'Tunde Bello', 'Staff'], ['sa001@ui.edu.ng', 'Super Admin', 'Permissions and admin'],
];

export default function LoginPage() {
  useDocumentTitle('Sign in');
  const nav = useNavigate(); const loc = useLocation();
  const from = (loc.state as { from?: string } | null)?.from ?? '/inbox';
  const { me, login } = useAuth();
  const [email, setEmail] = useState(''); const [password, setPassword] = useState('');
  const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const [demoOpen, setDemoOpen] = useState(false);
  if (me) return <Navigate to={from} replace />;

  const go = async (e: string, p: string) => {
    setBusy(true); setErr('');
    try { await login(e, p); nav(from, { replace: true }); } catch (x) { setErr(errorText(x)); } finally { setBusy(false); }
  };
  const submit = (e: FormEvent) => { e.preventDefault(); void go(email, password); };

  return (
    <main className="grid min-h-full place-items-center p-4">
      <div className="w-full max-w-sm">
        <form onSubmit={submit} className="card space-y-4 p-6">
          <div className="flex flex-col items-center">
            <img src="/ui_logo.png" alt="University of Ibadan" className="mb-4 h-24 w-auto" />
            <h1 className="text-2xl font-bold text-center">University of Ibadan Memos</h1>
            <p className="text-sm text-ink-soft text-center">Sign in with your staff email.</p>
          </div>
          <label className="block text-sm font-bold">Email
            <input className="field mt-1" type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </label>
          <label className="block text-sm font-bold">Password
            <input className="field mt-1" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </label>
          {err && <p role="alert" className="text-sm text-red-700">{err}</p>}
          <button className="btn btn-primary w-full" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
        </form>
        {USE_MOCK && (
          <section className="card mt-4 overflow-hidden" aria-label="Demo accounts">
            <button 
              className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-brand-50"
              onClick={() => setDemoOpen(!demoOpen)}
              aria-expanded={demoOpen}
            >
              <div>
                <h2 className="text-sm font-bold">Demo mode: no backend needed</h2>
                <p className="text-xs text-ink-soft">Pick an account to sign in</p>
              </div>
              <span className="text-ink-soft">{demoOpen ? '−' : '+'}</span>
            </button>
            {demoOpen && (
              <div className="border-t border-surface-line p-4">
                <ul className="space-y-1">
                  {DEMO.map(([e, n, r]) => (
                    <li key={e}><button disabled={busy} className="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-brand-50" onClick={() => go(e, 'ChangeMe#2026')}>
                      <span className="font-bold">{n}</span><span className="text-xs text-ink-soft">{r}</span></button></li>
                  ))}
                </ul>
              </div>
            )}
          </section>
        )}
      </div>
    </main>
  );
}
