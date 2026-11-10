import axios from 'axios';

/** true when VITE_USE_MOCK=true: the app runs fully in the browser with no backend. */
export const USE_MOCK = import.meta.env.VITE_USE_MOCK === 'true';

export const api = axios.create({ baseURL: import.meta.env.VITE_API_BASE ?? '/api/v1' });

if (USE_MOCK) {
  // Loaded lazily so it is not bundled into real-backend builds.
  api.defaults.adapter = (cfg) => import('@/mock/adapter').then((m) => m.mockAdapter(cfg));
}

api.interceptors.request.use((cfg) => {
  const t = localStorage.getItem('ui_memo_token');
  if (t) cfg.headers.Authorization = `Bearer ${t}`;
  cfg.headers.Accept = 'application/json';
  return cfg;
});

export const UNAUTHORIZED_EVENT = 'university-memo:unauthorized';
api.interceptors.response.use((r) => r, (err) => {
  if (err.response?.status === 401) {
    localStorage.removeItem('ui_memo_token');
    window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));   // the router guard redirects to /login
  }
  return Promise.reject(err);
});

/** Laravel validation / abort messages -> one readable string. */
export function errorText(e: unknown): string {
  const d = (e as any)?.response?.data;
  if (d?.errors) return Object.values(d.errors).flat().join(' ');
  if (d?.message) return d.message;
  return (e as any)?.response ? 'Something went wrong. Try again.' : 'Cannot reach the server. Check your connection.';
}
