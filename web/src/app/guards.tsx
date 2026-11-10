import { ReactNode } from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '@/features/auth/authStore';
import type { Role } from '@/shared/types';

/** Sends signed-out visitors to /login and remembers where they were going. */
export function RequireAuth({ children }: { children?: ReactNode }) {
  const me = useAuth((s) => s.me); const loc = useLocation();
  if (!me) return <Navigate to="/login" replace state={{ from: loc.pathname + loc.search }} />;
  return <>{children ?? <Outlet />}</>;
}

/** Signed-in users without the role are sent home, not shown an error. */
export function RequireRole({ roles, children }: { roles: Role[]; children?: ReactNode }) {
  const me = useAuth((s) => s.me);
  if (!me || !roles.includes(me.role)) return <Navigate to="/inbox" replace />;
  return <>{children ?? <Outlet />}</>;
}
