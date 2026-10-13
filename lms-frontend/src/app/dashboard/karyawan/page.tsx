'use client';

import Link from 'next/link';
import { Badge, Card, CardHeader, EmptyState, PageHeader, Spinner, StatCard } from '@/components/ui';
import { useAuth } from '@/lib/auth-context';
import { listOf, useApi } from '@/lib/use-api';

interface Enrollment {
  id: number;
  progress_pct: number;
  package: { id: number; title: string } | null;
  certificate: { status: string } | null;
}

export default function KaryawanDashboard() {
  const { user } = useAuth();
  const enrollments = useApi<unknown>('/my/enrollments');

  const rows = listOf<Enrollment>(enrollments.data);
  const selesai = rows.filter((e) => e.progress_pct >= 100).length;
  const bersertifikat = rows.filter((e) => e.certificate?.status === 'approved').length;

  return (
    <>
      <PageHeader
        title={`Dasbor Karyawan — ${user?.name ?? ''}`}
        description="Pelatihan internal dan sertifikasi kompetensi Anda."
      />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Pelatihan Diikuti" value={rows.length} />
        <StatCard label="Selesai 100%" value={selesai} tone="success" />
        <StatCard label="Sertifikat" value={bersertifikat} tone="info" />
      </div>

      <Card className="mt-6">
        <CardHeader
          title="Pelatihan Saya"
          action={<Link href="/dashboard/karyawan/training" className="text-sm text-brand hover:underline">Semua →</Link>}
        />
        {enrollments.loading ? (
          <Spinner className="h-6 w-6 text-brand" />
        ) : rows.length === 0 ? (
          <EmptyState title="Belum ada pelatihan" description="Pelatihan yang diikuti akan tampil di sini." />
        ) : (
          <ul className="space-y-2 text-sm">
            {rows.slice(0, 5).map((e) => (
              <li key={e.id} className="flex items-center justify-between rounded-[14px] border border-border px-3 py-2">
                <span>{e.package?.title ?? '-'}</span>
                <Badge tone={e.progress_pct >= 100 ? 'success' : 'info'}>{e.progress_pct}%</Badge>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </>
  );
}