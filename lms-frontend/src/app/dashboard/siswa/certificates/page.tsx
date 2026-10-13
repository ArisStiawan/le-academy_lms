'use client';

import { useState } from 'react';
import { Badge, Button, Card, CardHeader, EmptyState, ErrorNote, PageHeader, Spinner } from '@/components/ui';
import { apiError, listOf, tanggal, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Enrollment {
  id: number;
  progress_pct: number;
  package: { id: number; title: string } | null;
  certificate: { id: number; status: string; certificate_code: string | null } | null;
}

export default function SiswaCertificatesPage() {
  const enrollments = useApi<unknown>('/my/enrollments');
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);

  const rows = listOf<Enrollment>(enrollments.data);

  async function requestCert(enrollmentId: number) {
    setError(null);
    setNotice(null);
    setBusy(enrollmentId);
    try {
      await api.post('/certificates/request', { enrollment_id: enrollmentId });
      setNotice('Permohonan sertifikat berhasil diajukan. Menunggu persetujuan admin.');
      await enrollments.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  return (
    <>
      <PageHeader title="Sertifikat Saya" description="Ajukan sertifikat untuk paket yang sudah selesai 100%." />

      {(error || notice) && (
        <div className="mb-4 space-y-2">
          {error && <ErrorNote>{error}</ErrorNote>}
          {notice && (
            <div className="rounded-[14px] border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-600 dark:text-emerald-400">
              {notice}
            </div>
          )}
        </div>
      )}

      <Card>
        <CardHeader title="Paket & Status Sertifikat" />
        {enrollments.loading ? (
          <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="Belum ada paket" description="Beli paket lalu selesaikan 100% untuk mengajukan sertifikat." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted">
                  <th className="py-2">Paket</th>
                  <th className="py-2">Progres</th>
                  <th className="py-2">Sertifikat</th>
                  <th className="py-2 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((e) => (
                  <tr key={e.id} className="border-b border-border last:border-0">
                    <td className="py-2">{e.package?.title ?? '-'}</td>
                    <td className="py-2">
                      <Badge tone={e.progress_pct >= 100 ? 'success' : 'info'}>{e.progress_pct}%</Badge>
                    </td>
                    <td className="py-2">
                      {e.certificate ? (
                        <span className="flex items-center gap-2">
                          <Badge tone={e.certificate.status === 'approved' ? 'success' : e.certificate.status === 'rejected' ? 'danger' : 'warning'}>
                            {e.certificate.status}
                          </Badge>
                          {e.certificate.certificate_code && (
                            <span className="font-mono text-xs">{e.certificate.certificate_code}</span>
                          )}
                        </span>
                      ) : (
                        <span className="text-muted">—</span>
                      )}
                    </td>
                    <td className="py-2 text-right">
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={e.progress_pct < 100 || !!e.certificate}
                        loading={busy === e.id}
                        onClick={() => requestCert(e.id)}
                      >
                        {e.certificate ? 'Sudah Diajukan' : 'Ajukan'}
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      <p className="mt-4 text-xs text-muted">
        Sertifikat terakhir diperbarui: {tanggal(new Date().toISOString())}
      </p>
    </>
  );
}