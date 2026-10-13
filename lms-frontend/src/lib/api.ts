import axios, { AxiosError, type AxiosInstance } from 'axios';

export const API_URL =
  process.env.NEXT_PUBLIC_API_URL ?? 'http://127.0.0.1:8000/api/v1';

export interface ApiEnvelope<T> {
  message: string;
  data: T;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  institution_id: number | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface LoginResponse {
  token: string;
  token_type: string;
  user: AuthUser;
  roles: string[];
}

/**
 * Satu instance axios untuk seluruh app.
 * - Token otomatis disisipkan dari localStorage.
 * - Interceptor response melempar error ter-normalisasi (pesan + status).
 */
export const api: AxiosInstance = axios.create({
  baseURL: API_URL,
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('lms_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  }
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    const status = error.response?.status ?? 0;
    const serverMessage = error.response?.data?.message;

    // 401 → token kedaluwarsa / tidak valid, arahkan ke login
    if (status === 401 && typeof window !== 'undefined') {
      localStorage.removeItem('lms_token');
      localStorage.removeItem('lms_user');
      localStorage.removeItem('lms_roles');
      if (!window.location.pathname.startsWith('/login')) {
        window.location.assign('/login');
      }
    }

    return Promise.reject({
      status,
      message: serverMessage ?? error.message ?? 'Terjadi kesalahan jaringan.',
      errors: error.response?.data?.errors ?? null,
    });
  }
);

export default api;