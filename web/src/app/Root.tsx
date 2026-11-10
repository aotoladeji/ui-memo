import { useEffect } from 'react';
import { Outlet } from 'react-router-dom';
import { useAuth } from '@/features/auth/authStore';

/** Restores the session once, then renders the matched route. */
export default function Root() {
  const ready = useAuth((s) => s.ready); const load = useAuth((s) => s.load);
  useEffect(() => { void load(); }, [load]);
  if (!ready) return <div className="grid h-full place-items-center text-ink-soft" role="status">Loading…</div>;
  return <Outlet />;
}
