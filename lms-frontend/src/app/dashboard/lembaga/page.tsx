'use client';

import Link from 'next/link';
import { Card, CardHeader, EmptyState, PageHeader, Spinner, StatCard } from '@/components/ui';
import { listOf, rupiah, useApi } from '@/lib/use-api';

interface Summary {
  total_revenue: number;
  paid_orders: number;
  pending_orders: number;
  total_packages: number;
  per_package: { package: { id: number; title: string } | null; revenue: number; orders: number }[];
}

export default function LembagaDashboard() {
  const students = useApi<unknown>('/users?role=siswa');
  const summary = useApi<{ data: Summary }>('/orders/summary');
  const certs = useApi<unknown>('/certificates?status=pending');

  const sis = listOf<unknown>(students.data);
  const s = summary.data?.data ?? null;
  const pending = listOf<unknown>(certs.data);

  return (
    <>
      <PageHeader title="Dasbor Lembaga Bimbingan" description="Ringkasan siswa, paket, dan pemasukan lembaga Anda." />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Total Siswa" value={sis.length} />
        <StatCard label="Pemasukan" value={s ? rupiah(s.total_revenue) : '—'} tone="success" />
        <StatCard label="Paket" value={s?.total_packages ?? 0} tone="info" />
        <StatCard label="Sertifikat Pending" value={pending.length} tone="warning" />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader title="Siswa Terbaru" action={<Link href="/dashboard/lembaga/students" className="text-sm text-brand hover:underline">Semua →</Link>} />
          {students.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : sis.length === 0 ? (
            <EmptyState title="Belum ada siswa" description="Import siswa dari menu Siswa." />
          ) : (
            <ul className="space-y-2 text-sm">
              {(sis as { id: number; name: string; email: string }[]).slice(0, 5).map((u) => (
                <li key={u.id} className="flex items-center justify-between rounded-[14px] border border-border px-3 py-2">
                  <span>{u.name}</span>
                  <span className="text-xs text-muted">{u.email}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card>
          <CardHeader title="Pemasukan per Paket" action={<Link href="/dashboard/lembaga/revenue" className="text-sm text-brand hover:underline">Detail →</Link>} />
          {summary.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : !s || s.per_package.length === 0 ? (
            <EmptyState title="Belum ada pemasukan" />
          ) : (
            <ul className="space-y-2 text-sm">
              {s.per_package.map((row, i) => (
                <li key={i} className="flex items-center justify-between rounded-[14px] border border-border px-3 py-2">
                  <span>{row.package?.title ?? '-'}</span>
                  <span className="font-semibold">{rupiah(row.revenue)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </>
  );
}