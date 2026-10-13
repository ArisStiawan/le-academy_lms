'use client';

import { useCallback, useEffect, useState } from 'react';
import api from '@/lib/api';

/** Normalisasi error axios → pesan yang layak ditampilkan. */
export function apiError(err: unknown): string {
  const e = err as { message?: string; errors?: Record<string, string[]> };
  const fieldError = e.errors ? Object.values(e.errors)[0]?.[0] : undefined;
  return fieldError ?? e.message ?? 'Terjadi kesalahan.';
}

/**
 * Hook fetch sederhana: GET path → { data, loading, error, reload }.
 * `path` null → tidak fetch (menunggu dependensi).
 */
export function useApi<T>(path: string | null, deps: unknown[] = []) {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState<boolean>(!!path);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!path) return;
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<T>(path);
      setData(res.data);
    } catch (err) {
      setError(apiError(err));
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path]);

  useEffect(() => {
    reload();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path, ...deps]);

  return { data, loading, error, reload, setData };
}

/** Ambil array dari paginator Laravel ({ data: { data: [...] } }) atau array biasa. */
export function listOf<T>(payload: unknown): T[] {
  const p = payload as { data?: unknown } | T[] | null;
  if (!p) return [];
  if (Array.isArray(p)) return p as T[];
  const inner = (p as { data?: unknown }).data;
  if (Array.isArray(inner)) return inner as T[];
  if (inner && typeof inner === 'object' && Array.isArray((inner as { data?: unknown }).data)) {
    return (inner as { data: T[] }).data;
  }
  return [];
}

/** Ambil objek data dari envelope { data: {...} }. */
export function objOf<T>(payload: unknown): T | null {
  const p = payload as { data?: T } | null;
  return p?.data ?? null;
}

export function rupiah(n: number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(n ?? 0);
}

export function tanggal(iso: string | null | undefined) {
  if (!iso) return '-';
  return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

export function tanggalSaja(iso: string | null | undefined) {
  if (!iso) return '-';
  return new Date(iso).toLocaleDateString('id-ID', { dateStyle: 'long' });
}