'use client';

import { useState } from 'react';
import {
  Badge, Button, Card, CardHeader, EmptyState, ErrorNote, PageHeader, Spinner,
} from '@/components/ui';
import { apiError, listOf, tanggal, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Cert {
  id: number;
  status: string;
  certificate_code: string | null;
  issued_at: string | null;
  notes: string | null;
  user?: { name: string; email: string };
  enrollment?: { package?: { title: string } };
}

type Filter = '' | 'pending' | 'approved' | 'rejected';

/** Daftar semua sertifikat + setujui/tolak (admin & mentor). */
export function CertificateReview({ showHeader = true }: { showHeader?: boolean }) {
  const [filter, setFilter] = useState<Filter>('pending');
  const certs = useApi<unknown>(`/certificates${filter ? `?status=${filter}` : ''}`, [filter]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);

  const rows = listOf<Cert>(certs.data);

  async function approve(c: Cert) {
    setError(null);
    setBusy(c.id);
    try {
      await api.patch(`/certificates/${c.id}/approve`);
      await certs.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  async function reject(c: Cert) {
    const notes = prompt('Alasan penolakan (opsional):') ?? '';
    setError(null);
    setBusy(c.id);
    try {
      await api.patch(`/certificates/${c.id}/reject`, { notes });
      await certs.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  return (
    <>
      {showHeader && (
        <PageHeader title="Sertifikat" description="Setujui atau tolak permohonan sertifikat." />
      )}

      <div className="mb-4 flex flex-wrap gap-2">
        {(['pending', 'approved', 'rejected', ''] as Filter[]).map((f) => (
          <button
            key={f || 'all'}
            onClick={() => setFilter(f)}
            className={`rounded-full px-3 py-1 text-sm ${
              filter === f ? 'bg-brand text-brand-fg' : 'border border-border hover:bg-brand-soft'
            }`}
          >
            {f === '' ? 'Semua' : f}
          </button>
        ))}
      </div>

      {error && <div className="mb-4"><ErrorNote>{error}</ErrorNote></div>}

      <Card>
        <CardHeader title="Daftar Permohonan" />
        {certs.loading ? (
          <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="Tidak ada sertifikat" description="Belum ada permohonan dengan filter ini." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted">
                  <th className="py-2">Pengguna</th>
                  <th className="py-2">Paket</th>
                  <th className="py-2">Status</th>
                  <th className="py-2">Kode</th>
                  <th className="py-2 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((c) => (
                  <tr key={c.id} className="border-b border-border last:border-0">
                    <td className="py-2">
                      <div>{c.user?.name ?? '-'}</div>
                      <div className="text-xs text-muted">{c.user?.email}</div>
                    </td>
                    <td className="py-2">{c.enrollment?.package?.title ?? '-'}</td>
                    <td className="py-2">
                      <Badge tone={c.status === 'approved' ? 'success' : c.status === 'rejected' ? 'danger' : 'warning'}>
                        {c.status}
                      </Badge>
                      {c.issued_at && <div className="mt-1 text-xs text-muted">{tanggal(c.issued_at)}</div>}
                    </td>
                    <td className="py-2 font-mono text-xs">{c.certificate_code ?? '—'}</td>
                    <td className="py-2 text-right">
                      {c.status === 'pending' ? (
                        <div className="flex justify-end gap-2">
                          <Button size="sm" loading={busy === c.id} onClick={() => approve(c)}>Setujui</Button>
                          <Button size="sm" variant="danger" loading={busy === c.id} onClick={() => reject(c)}>Tolak</Button>
                        </div>
                      ) : (
                        <span className="text-xs text-muted">Selesai</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </>
  );
}