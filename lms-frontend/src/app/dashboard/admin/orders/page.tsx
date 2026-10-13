'use client';

import { Badge, Card, CardHeader, EmptyState, PageHeader, Spinner } from '@/components/ui';
import { listOf, rupiah, tanggal, useApi } from '@/lib/use-api';

interface Order {
  id: number;
  status: string;
  final_price: number;
  original_price: number;
  discount_amount: number;
  paid_at: string | null;
  created_at: string;
  user?: { name: string; email: string };
  package?: { title: string };
}

export default function AdminOrdersPage() {
  const orders = useApi<unknown>('/orders/manage');
  const rows = listOf<Order>(orders.data);

  return (
    <>
      <PageHeader title="Penjualan" description="Seluruh transaksi pembelian paket." />

      <Card>
        <CardHeader title="Daftar Transaksi" subtitle={`${rows.length} transaksi`} />
        {orders.loading ? (
          <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="Belum ada transaksi" />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted">
                  <th className="py-2">Pembeli</th>
                  <th className="py-2">Paket</th>
                  <th className="py-2">Total</th>
                  <th className="py-2">Status</th>
                  <th className="py-2">Dibayar</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((o) => (
                  <tr key={o.id} className="border-b border-border last:border-0">
                    <td className="py-2">
                      <div>{o.user?.name ?? '-'}</div>
                      <div className="text-xs text-muted">{o.user?.email}</div>
                    </td>
                    <td className="py-2">{o.package?.title ?? '-'}</td>
                    <td className="py-2">
                      <div className="font-medium">{rupiah(o.final_price)}</div>
                      {o.discount_amount > 0 && (
                        <div className="text-xs text-muted line-through">{rupiah(o.original_price)}</div>
                      )}
                    </td>
                    <td className="py-2">
                      <Badge tone={o.status === 'paid' ? 'success' : o.status === 'pending' ? 'warning' : 'neutral'}>
                        {o.status}
                      </Badge>
                    </td>
                    <td className="py-2 text-muted">{o.paid_at ? tanggal(o.paid_at) : '—'}</td>
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