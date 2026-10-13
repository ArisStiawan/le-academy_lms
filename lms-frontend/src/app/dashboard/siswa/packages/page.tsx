'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Button, Card, CardHeader, EmptyState, ErrorNote, Input, PageHeader, Spinner, Badge } from '@/components/ui';
import { apiError, listOf, rupiah, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Pkg {
  id: number;
  title: string;
  description: string | null;
  price: number;
  modules?: { id: number; title: string }[];
}

interface Enrollment {
  id: number;
  package_id: number;
  progress_pct: number;
}

export default function SiswaPackagesPage() {
  const packages = useApi<unknown>('/packages');
  const enrollments = useApi<unknown>('/my/enrollments');
  const orders = useApi<unknown>('/orders');

  const [voucher, setVoucher] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const pkgs = listOf<Pkg>(packages.data);
  const myEnrollments = listOf<Enrollment>(enrollments.data);
  const myOrders = listOf<{ id: number; status: string; final_price: number; package: { title: string } | null }>(orders.data);

  const enrolledIds = new Set(myEnrollments.map((e) => e.package_id));

  async function checkout(pkg: Pkg) {
    setError(null);
    setNotice(null);
    setBusy(pkg.id);
    try {
      const body: Record<string, unknown> = { package_id: pkg.id };
      if (voucher.trim()) body.voucher_code = voucher.trim();

      const { data } = await api.post<{ data: { order_id: number } }>('/checkout', body);
      const orderId = data.data.order_id;

      // Simulasi pembayaran (backend masih `pay`), lalu segarkan data
      await api.post(`/orders/${orderId}/pay`);
      setNotice(`Berhasil membeli "${pkg.title}". Anda sekarang terdaftar.`);
      setVoucher('');
      await Promise.all([packages.reload(), enrollments.reload(), orders.reload()]);
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  return (
    <>
      <PageHeader title="Paket Belajar" description="Beli paket untuk mulai belajar." />

      {(error || notice) && (
        <div className="mb-4">
          {error && <ErrorNote>{error}</ErrorNote>}
          {notice && (
            <div className="rounded-[14px] border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-600 dark:text-emerald-400">
              {notice}
            </div>
          )}
        </div>
      )}

      <Card className="mb-6">
        <CardHeader title="Kode Voucher" subtitle="Opsional — masukkan sebelum membeli paket." />
        <Input
          placeholder="Contoh: LE2026"
          value={voucher}
          onChange={(e) => setVoucher(e.target.value)}
        />
      </Card>

      {packages.loading ? (
        <div className="flex justify-center py-10"><Spinner className="h-8 w-8 text-brand" /></div>
      ) : pkgs.length === 0 ? (
        <EmptyState title="Belum ada paket" description="Paket belajar akan tampil di sini." />
      ) : (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {pkgs.map((p) => {
            const owned = enrolledIds.has(p.id);
            return (
              <Card key={p.id} className="flex flex-col">
                <div className="flex items-start justify-between gap-2">
                  <h3 className="text-lg font-semibold">{p.title}</h3>
                  {owned && <Badge tone="success">Dimiliki</Badge>}
                </div>
                <p className="mt-1 line-clamp-3 flex-1 text-sm text-muted">
                  {p.description ?? 'Tanpa deskripsi.'}
                </p>
                {p.modules && p.modules.length > 0 && (
                  <p className="mt-2 text-xs text-muted">{p.modules.length} modul</p>
                )}
                <p className="mt-4 text-xl font-bold text-brand">{rupiah(p.price)}</p>
                <div className="mt-4 flex gap-2">
                  <Link href={`/dashboard/siswa/packages/${p.id}`} className="flex-1">
                    <Button className="w-full" variant="outline">Detail</Button>
                  </Link>
                  <Button
                    className="flex-1"
                    disabled={owned}
                    loading={busy === p.id}
                    onClick={() => checkout(p)}
                  >
                    {owned ? 'Dimiliki' : 'Beli'}
                  </Button>
                </div>
              </Card>
            );
          })}
        </div>
      )}

      <div className="mt-8">
        <Card>
          <CardHeader title="Riwayat Pesanan" subtitle="Transaksi pembelian paket Anda." />
          {orders.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : myOrders.length === 0 ? (
            <EmptyState title="Belum ada pesanan" />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-muted">
                    <th className="py-2">Paket</th>
                    <th className="py-2">Total</th>
                    <th className="py-2">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {myOrders.map((o) => (
                    <tr key={o.id} className="border-b border-border last:border-0">
                      <td className="py-2">{o.package?.title ?? '-'}</td>
                      <td className="py-2">{rupiah(o.final_price)}</td>
                      <td className="py-2">
                        <Badge tone={o.status === 'paid' ? 'success' : o.status === 'pending' ? 'warning' : 'neutral'}>
                          {o.status}
                        </Badge>
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