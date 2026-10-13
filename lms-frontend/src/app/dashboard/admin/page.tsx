'use client';

import Link from 'next/link';
import { Badge, Card, CardHeader, EmptyState, PageHeader, Spinner, StatCard } from '@/components/ui';
import { listOf, rupiah, useApi } from '@/lib/use-api';

interface Summary {
  total_revenue: number;
  paid_orders: number;
  pending_orders: number;
  total_packages: number;
  per_package: { package: { id: number; title: string } | null; revenue: number; orders: number }[];
}

export default function AdminDashboard() {
  const users = useApi<unknown>('/users');
  const summary = useApi<{ data: Summary }>('/orders/summary');
  const certs = useApi<unknown>('/certificates?status=pending');
  const curricula = useApi<unknown>('/curricula');

  const userRows = listOf<{ id: number; institution_id: number | null }>(users.data);
  const s = summary.data?.data ?? null;
  const pendingCerts = listOf<unknown>(certs.data);
  const curr = listOf<unknown>(curricula.data);

  return (
    <>
      <PageHeader title="Dasbor Administrator" description="Ringkasan seluruh platform LE Academy." />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Total Pendapatan" value={s ? rupiah(s.total_revenue) : '—'} tone="success" />
        <StatCard label="Transaksi Lunas" value={s?.paid_orders ?? 0} tone="info" />
        <StatCard label="Menunggu Bayar" value={s?.pending_orders ?? 0} tone="warning" />
        <StatCard label="Sertifikat Pending" value={pendingCerts.length} tone="danger" />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader
            title="Pendapatan per Paket"
            action={<Link href="/dashboard/admin/orders" className="text-sm text-brand hover:underline">Transaksi →</Link>}
          />
          {summary.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : !s || s.per_package.length === 0 ? (
            <EmptyState title="Belum ada pendapatan" />
          ) : (
            <ul className="space-y-2">
              {s.per_package.map((row, i) => (
                <li key={i} className="flex items-center justify-between rounded-[14px] border border-border px-3 py-2 text-sm">
                  <span>{row.package?.title ?? 'Paket dihapus'}</span>
                  <span className="flex items-center gap-3">
                    <Badge tone="info">{row.orders} order</Badge>
                    <span className="font-semibold">{rupiah(row.revenue)}</span>
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card>
          <CardHeader
            title="Statistik Platform"
          />
          <div className="grid grid-cols-2 gap-4">
            <div>
              <p className="text-sm text-muted">Total Pengguna</p>
              <p className="text-xl font-semibold">{userRows.length}</p>
            </div>
            <div>
              <p className="text-sm text-muted">Total Paket</p>
              <p className="text-xl font-semibold">{s?.total_packages ?? 0}</p>
            </div>
            <div>
              <p className="text-sm text-muted">Kurikulum</p>
              <p className="text-xl font-semibold">{curr.length}</p>
            </div>
            <div>
              <p className="text-sm text-muted">Sertifikat Pending</p>
              <p className="text-xl font-semibold">{pendingCerts.length}</p>
            </div>
          </div>
        </Card>
      </div>
    </>
  );
}