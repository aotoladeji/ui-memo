import { lazy } from 'react';
import { createBrowserRouter, createMemoryRouter, Navigate, RouteObject } from 'react-router-dom';
import Root from './Root';
import AppLayout from './layouts/AppLayout';
import { RequireAuth, RequireRole } from './guards';
import { NotFound, RouteError } from './pages';
import LoginPage from '@/features/auth/LoginPage';

// Code-split: the heavy viewer (pdf.js + Fabric) loads only when a memo is opened.
const MemoListPage = lazy(() => import('@/features/inbox/MemoListPage'));
const ComposePage = lazy(() => import('@/features/memo-compose/ComposePage'));
const MemoPage = lazy(() => import('@/features/memo-viewer/MemoPage'));
const OrgPage = lazy(() => import('@/features/admin/OrgPage'));
const PeoplePage = lazy(() => import('@/features/admin/PeoplePage'));
const PermissionManager = lazy(() => import('@/features/permissions/PermissionManager'));

/**
 *  /login
 *  /inbox  /sent  /drafts
 *  /memos/new          compose
 *  /memos/:id          viewer + editor
 *  /admin/org|people   SuperAdmin, Admin
 *  /admin/permissions  SuperAdmin
 */
export const routes: RouteObject[] = [
  {
    element: <Root />, errorElement: <RouteError />,
    children: [
      { path: '/login', element: <LoginPage /> },
      {
        element: <RequireAuth />,
        children: [{
          element: <AppLayout />,
          children: [
            { index: true, element: <Navigate to="/inbox" replace /> },
            { path: 'inbox', element: <MemoListPage box="inbox" /> },
            { path: 'sent', element: <MemoListPage box="sent" /> },
            { path: 'drafts', element: <MemoListPage box="drafts" /> },
            { path: 'memos/new', element: <ComposePage /> },
            { path: 'memos/:id', element: <MemoPage /> },
            {
              path: 'admin', element: <RequireRole roles={['super_admin', 'admin']} />,
              children: [
                { index: true, element: <Navigate to="org" replace /> },
                { path: 'org', element: <OrgPage /> },
                { path: 'people', element: <PeoplePage /> },
                { path: 'permissions', element: <RequireRole roles={['super_admin']} />, children: [{ index: true, element: <PermissionManager /> }] },
              ],
            },
            { path: '*', element: <NotFound /> },
          ],
        }],
      },
      { path: '*', element: <NotFound /> },
    ],
  },
];

export const createAppRouter = () => createBrowserRouter(routes);
export const createTestRouter = (path: string) => createMemoryRouter(routes, { initialEntries: [path] });
