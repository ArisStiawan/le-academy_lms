'use client';

import { Badge, Card, CardHeader, EmptyState, PageHeader, Spinner, StatCard } from '@/components/ui';
import { listOf, rupiah, tanggal, useApi } from '@/lib/use-api';

interface Summary {
  total_revenue: number;
  paid_orders: number;
  pending_orders: number;
  total_packages: number;
  per_package: { package: { id: number; title: string } | null; revenue: number; orders: number }[];
}

interface Order {
  id: number;
  status: string;
  final_price: number;
  paid_at: string | null;
  user?: { name: string; email: string };
  package?: { title: string };
}

export default function LembagaRevenuePage() {
  const summary = useApi<{ data: Summary }>('/orders/summary');
  const orders = useApi<unknown>('/orders/manage');

  const s = summary.data?.data ?? null;
  const rows = listOf<Order>(orders.data);

  return (
    <>
      <PageHeader title="Pemasukan" description="Pantau pemasukan dan transaksi paket lembaga Anda." />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Total Pemasukan" value={s ? rupiah(s.total_revenue) : '—'} tone="success" />
        <StatCard label="Transaksi Lunas" value={s?.paid_orders ?? 0} tone="info" />
        <StatCard label="Menunggu Bayar" value={s?.pending_orders ?? 0} tone="warning" />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader title="Pemasukan per Paket" />
          {summary.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : !s || s.per_package.length === 0 ? (
            <EmptyState title="Belum ada pemasukan" />
          ) : (
            <ul className="space-y-2 text-sm">
              {s.per_package.map((row, i) => (
                <li key={i} className="flex items-center justify-between rounded-[14px] border border-border px-3 py-2">
                  <span>{row.package?.title ?? '-'}</span>
                  <span className="flex items-center gap-3">
                    <Badge tone="info">{row.orders}</Badge>
                    <span className="font-semibold">{rupiah(row.revenue)}</span>
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card>
          <CardHeader title="Transaksi Terbaru" subtitle={`${rows.length} transaksi`} />
          {orders.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : rows.length === 0 ? (
            <EmptyState title="Belum ada transaksi" />
          ) : (
            <div className="max-h-96 overflow-y-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-muted">
                    <th className="py-2">Pembeli</th>
                    <th className="py-2">Paket</th>
                    <th className="py-2">Total</th>
                    <th className="py-2">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((o) => (
                    <tr key={o.id} className="border-b border-border last:border-0">
                      <td className="py-2">{o.user?.name ?? '-'}</td>
                      <td className="py-2">{o.package?.title ?? '-'}</td>
                      <td className="py-2 font-medium">{rupiah(o.final_price)}</td>
                      <td className="py-2">
                        <Badge tone={o.status === 'paid' ? 'success' : o.status === 'pending' ? 'warning' : 'neutral'}>
                          {o.status}
                        </Badge>
                        {o.paid_at && <div className="mt-0.5 text-xs text-muted">{tanggal(o.paid_at)}</div>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </>
  );
}