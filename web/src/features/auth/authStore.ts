import { create } from 'zustand';
import { api, UNAUTHORIZED_EVENT } from '@/shared/api';
import type { Me } from '@/shared/types';

interface AuthState {
  me: Me | null; ready: boolean;
  login: (email: string, password: string) => Promise<void>;
  load: () => Promise<void>;
  logout: () => Promise<void>;
}

export const useAuth = create<AuthState>((set) => ({
  me: null, ready: false,
  login: async (email, password) => {
    const { data } = await api.post('/auth/login', { email, password });
    localStorage.setItem('ui_memo_token', data.token);
    set({ me: data.user, ready: true });
  },
  load: async () => {
    if (!localStorage.getItem('ui_memo_token')) return set({ ready: true });
    try { set({ me: (await api.get<Me>('/auth/me')).data, ready: true }); }
    catch { set({ me: null, ready: true }); }
  },
  logout: async () => {
    try { await api.post('/auth/logout'); } catch { /* token may already be invalid */ }
    localStorage.removeItem('ui_memo_token');
    set({ me: null });
  },
}));

if (typeof window !== 'undefined') window.addEventListener(UNAUTHORIZED_EVENT, () => useAuth.setState({ me: null }));
