'use client';

import { Badge, Card, CardHeader, EmptyState, PageHeader, Spinner } from '@/components/ui';
import { listOf, tanggal, useApi } from '@/lib/use-api';

interface Enrollment {
  id: number;
  progress_pct: number;
  package: { title: string } | null;
  certificate: { status: string; certificate_code: string | null; issued_at: string | null } | null;
}

export default function KaryawanCertificatesPage() {
  const enrollments = useApi<unknown>('/my/enrollments');
  const rows = listOf<Enrollment>(enrollments.data).filter((e) => e.certificate);

  return (
    <>
      <PageHeader title="Sertifikasi" description="Sertifikat kompetensi yang Anda peroleh." />

      <Card>
        <CardHeader title="Daftar Sertifikat" subtitle={`${rows.length} sertifikat`} />
        {enrollments.loading ? (
          <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="Belum ada sertifikat" description="Selesaikan pelatihan 100% lalu ajukan sertifikat." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted">
                  <th className="py-2">Pelatihan</th>
                  <th className="py-2">Status</th>
                  <th className="py-2">Kode</th>
                  <th className="py-2">Terbit</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((e) => (
                  <tr key={e.id} className="border-b border-border last:border-0">
                    <td className="py-2">{e.package?.title ?? '-'}</td>
                    <td className="py-2">
                      <Badge tone={e.certificate?.status === 'approved' ? 'success' : e.certificate?.status === 'rejected' ? 'danger' : 'warning'}>
                        {e.certificate?.status}
                      </Badge>
                    </td>
                    <td className="py-2 font-mono text-xs">{e.certificate?.certificate_code ?? '—'}</td>
                    <td className="py-2 text-muted">{tanggal(e.certificate?.issued_at)}</td>
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