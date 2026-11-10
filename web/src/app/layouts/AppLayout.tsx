import { Suspense, useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Building2, FilePlus2, FileText, Inbox, LogOut, Menu, Send, ShieldCheck, Users } from 'lucide-react';
import { useAuth } from '@/features/auth/authStore';
import { USE_MOCK } from '@/shared/api';

const link = ({ isActive }: { isActive: boolean }) =>
  `flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold ${isActive ? 'bg-brand-600 text-white' : 'hover:bg-brand-50'}`;

export default function AppLayout() {
  const { me, logout } = useAuth(); const nav = useNavigate(); const loc = useLocation();
  const [open, setOpen] = useState(false);
  useEffect(() => setOpen(false), [loc.pathname]);
  const isAdmin = me?.role === 'super_admin' || me?.role === 'admin';

  return (
    <div className="flex h-full flex-col md:flex-row">
      <header className="flex items-center justify-between border-b border-surface-line bg-white px-4 py-2 md:hidden">
        <Link to="/inbox" className="flex items-center gap-2">
          <img src="/ui_logo.png" alt="UI" className="h-8 w-auto" />
          <span className="text-lg font-bold text-brand-700">University Memo</span>
        </Link>
        <button className="btn btn-ghost px-2" aria-label="Menu" aria-expanded={open} onClick={() => setOpen((o) => !o)}><Menu size={18} /></button>
      </header>
      <nav aria-label="Main" className={`${open ? 'flex' : 'hidden'} w-full shrink-0 flex-col border-r border-surface-line bg-white p-3 md:flex md:w-56`}>
        <Link to="/inbox" className="mb-4 hidden flex-col items-center px-3 py-2 md:flex">
          <img src="/ui_logo.png" alt="University of Ibadan" className="mb-2 h-16 w-auto" />
          <span className="text-lg font-bold text-brand-700">University Memo</span>
        </Link>
        <NavLink to="/inbox" className={link}><Inbox size={16} /> Inbox</NavLink>
        <NavLink to="/sent" className={link}><Send size={16} /> Sent</NavLink>
        <NavLink to="/drafts" className={link}><FileText size={16} /> Drafts</NavLink>
        <NavLink to="/memos/new" className={link}><FilePlus2 size={16} /> New memo</NavLink>
        {isAdmin && <p className="mb-1 mt-4 px-3 text-xs font-bold text-ink-soft">Administration</p>}
        {isAdmin && <NavLink to="/admin/org" className={link}><Building2 size={16} /> Organisation</NavLink>}
        {isAdmin && <NavLink to="/admin/people" className={link}><Users size={16} /> People</NavLink>}
        {me?.role === 'super_admin' && <NavLink to="/admin/permissions" className={link}><ShieldCheck size={16} /> Permissions</NavLink>}
        <div className="mt-auto border-t border-surface-line pt-3 text-sm">
          <p className="font-bold">{me?.name}</p><p className="mb-2 text-ink-soft">{me?.title ?? me?.role}</p>
          {USE_MOCK && <p className="mb-2 rounded bg-brand-50 px-2 py-1 text-xs">Demo mode (no backend)</p>}
          <button className="btn btn-ghost w-full" onClick={async () => { await logout(); nav('/login', { replace: true }); }}><LogOut size={14} /> Sign out</button>
        </div>
      </nav>
      <main className="min-h-0 min-w-0 flex-1 overflow-auto">
        <Suspense fallback={<p className="p-8 text-ink-soft" role="status">Loading…</p>}><Outlet /></Suspense>
      </main>
    </div>
  );
}
