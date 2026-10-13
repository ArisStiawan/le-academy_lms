'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api, { type AuthUser } from '@/lib/api';

interface AuthContextValue {
  user: AuthUser | null;
  roles: string[];
  token: string | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

const STORAGE_KEYS = {
  token: 'lms_token',
  user: 'lms_user',
  roles: 'lms_roles',
};

function readStoredUser(): AuthUser | null {
  if (typeof window === 'undefined') return null;
  try {
    const raw = localStorage.getItem(STORAGE_KEYS.user);
    return raw ? (JSON.parse(raw) as AuthUser) : null;
  } catch {
    return null;
  }
}

function readStoredRoles(): string[] {
  if (typeof window === 'undefined') return [];
  try {
    const raw = localStorage.getItem(STORAGE_KEYS.roles);
    return raw ? (JSON.parse(raw) as string[]) : [];
  } catch {
    return [];
  }
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(() => readStoredUser());
  const [roles, setRoles] = useState<string[]>(() => readStoredRoles());
  const [token, setToken] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(STORAGE_KEYS.token);
  });
  const [loading, setLoading] = useState<boolean>(false);

  const persist = useCallback((t: string, u: AuthUser, r: string[]) => {
    localStorage.setItem(STORAGE_KEYS.token, t);
    localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(u));
    localStorage.setItem(STORAGE_KEYS.roles, JSON.stringify(r));
  }, []);

  const clear = useCallback(() => {
    localStorage.removeItem(STORAGE_KEYS.token);
    localStorage.removeItem(STORAGE_KEYS.user);
    localStorage.removeItem(STORAGE_KEYS.roles);
  }, []);

  const login = useCallback(
    async (email: string, password: string) => {
      setLoading(true);
      try {
        const { data } = await api.post<{ data: { token: string; user: AuthUser; roles: string[] } }>(
          '/auth/login',
          { email, password },
        );
        const { token: t, user: u, roles: r } = data.data;
        persist(t, u, r);
        setToken(t);
        setUser(u);
        setRoles(r);
      } finally {
        setLoading(false);
      }
    },
    [persist],
  );

  const refreshMe = useCallback(async () => {
    try {
      const { data } = await api.get<{ data: { user: AuthUser; roles: string[] } }>('/auth/me');
      setUser(data.data.user);
      setRoles(data.data.roles);
      persist(token ?? '', data.data.user, data.data.roles);
    } catch {
      // 401 → interceptor sudah arahkan ke login
    }
  }, [persist, token]);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } catch {
      // tetap logout lokal walau request gagal
    } finally {
      clear();
      setUser(null);
      setRoles([]);
      setToken(null);
    }
  }, [clear]);

  const value = useMemo(
    () => ({ user, roles, token, loading, login, logout, refreshMe }),
    [user, roles, token, loading, login, logout, refreshMe],
  );

  useEffect(() => {
    if (token) refreshMe();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth harus dipakai di dalam <AuthProvider>.');
  return ctx;
}