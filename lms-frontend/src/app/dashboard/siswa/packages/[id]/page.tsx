'use client';

import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useState } from 'react';
import { Badge, Button, Card, CardHeader, EmptyState, ErrorNote, PageHeader, Spinner } from '@/components/ui';
import { apiError, listOf, objOf, rupiah, useApi } from '@/lib/use-api';
import api from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { ROLE_DASHBOARD, primaryRole } from '@/lib/roles';

interface Content {
  id: number;
  type: string;
  title: string;
  description: string | null;
  external_url: string | null;
}

interface Mod {
  id: number;
  title: string;
  description: string | null;
  contents?: Content[];
}

interface Pkg {
  id: number;
  title: string;
  description: string | null;
  price: number;
  modules?: Mod[];
  reviews?: { id: number; rating: number; comment: string | null; user?: { name: string } }[];
  average_rating?: number | null;
}

export default function PackageDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const { roles } = useAuth();
  const base = ROLE_DASHBOARD[primaryRole(roles)];

  const detail = useApi<unknown>(`/packages/${params.id}`);
  const reviews = useApi<unknown>(`/reviews?package_id=${params.id}`);
  const enrollments = useApi<unknown>('/my/enrollments');

  const pkg = objOf<Pkg>(detail.data);
  const revRows = listOf<{ id: number; rating: number; comment: string | null; user?: { name: string } }>(reviews.data);
  const myEnrollments = listOf<{ id: number; package_id: number; progress_pct: number }>(enrollments.data);

  const enrollment = myEnrollments.find((e) => e.package_id === Number(params.id));

  const [voucher, setVoucher] = useState('');
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function enroll() {
    setError(null);
    setNotice(null);
    setBusy(true);
    try {
      const body: Record<string, unknown> = { package_id: Number(params.id) };
      if (voucher.trim()) body.voucher_code = voucher.trim();
      const { data } = await api.post<{ data: { order_id: number } }>('/checkout', body);
      await api.post(`/orders/${data.data.order_id}/pay`);
      setNotice('Pembelian berhasil. Anda sekarang terdaftar di paket ini.');
      setVoucher('');
      await Promise.all([enrollments.reload(), detail.reload()]);
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(false);
    }
  }

  async function submitReview(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setNotice(null);
    setBusy(true);
    try {
      await api.post('/reviews', {
        package_id: Number(params.id),
        rating,
        comment: comment || undefined,
      });
      setNotice('Terima kasih! Ulasan Anda tersimpan.');
      setComment('');
      await reviews.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(false);
    }
  }

  if (detail.loading) {
    return <div className="flex justify-center py-20"><Spinner className="h-8 w-8 text-brand" /></div>;
  }
  if (!pkg) {
    return <EmptyState title="Paket tidak ditemukan" description="Paket mungkin sudah nonaktif." />;
  }

  return (
    <>
      <button onClick={() => router.back()} className="mb-4 text-sm text-brand hover:underline">← Kembali</button>

      <PageHeader
        title={pkg.title}
        description={pkg.description ?? 'Tanpa deskripsi.'}
        action={
          <span className="text-2xl font-bold text-brand">{rupiah(pkg.price)}</span>
        }
      />

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

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-6">
          <Card>
            <CardHeader title="Modul Pembelajaran" subtitle={`${pkg.modules?.length ?? 0} modul`} />
            {!pkg.modules || pkg.modules.length === 0 ? (
              <EmptyState title="Belum ada modul" />
            ) : (
              <ol className="space-y-2">
                {pkg.modules.map((m, i) => (
                  <li key={m.id} className="rounded-[14px] border border-border px-3 py-2 text-sm">
                    <div className="flex items-center gap-2">
                      <span className="flex h-6 w-6 items-center justify-center rounded-full bg-brand-soft text-xs font-semibold">
                        {i + 1}
                      </span>
                      <span className="font-medium">{m.title}</span>
                    </div>
                    {m.description && <p className="mt-1 pl-8 text-xs text-muted">{m.description}</p>}
                  </li>
                ))}
              </ol>
            )}
          </Card>

          <Card>
            <CardHeader title="Ulasan Siswa" subtitle={`${revRows.length} ulasan`} />
            {reviews.loading ? (
              <Spinner className="h-6 w-6 text-brand" />
            ) : revRows.length === 0 ? (
              <EmptyState title="Belum ada ulasan" />
            ) : (
              <ul className="space-y-3">
                {revRows.map((r) => (
                  <li key={r.id} className="rounded-[14px] border border-border p-3 text-sm">
                    <div className="flex items-center justify-between">
                      <span className="font-medium">{r.user?.name ?? 'Siswa'}</span>
                      <Badge tone="warning">{'★'.repeat(r.rating)}</Badge>
                    </div>
                    {r.comment && <p className="mt-1 text-muted">{r.comment}</p>}
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader title={enrollment ? 'Anda Sudah Terdaftar' : 'Beli Paket'} />
            {enrollment ? (
              <>
                <p className="text-sm text-muted">Progres belajar Anda</p>
                <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-brand-soft">
                  <div className="h-full rounded-full bg-brand" style={{ width: `${enrollment.progress_pct}%` }} />
                </div>
                <p className="mt-1 text-sm font-medium">{enrollment.progress_pct}%</p>
                <Link href={`${base}/certificates`}>
                  <Button className="mt-4 w-full" variant="outline">Ke Sertifikat</Button>
                </Link>
              </>
            ) : (
              <>
                <label className="mb-3 block">
                  <span className="mb-1 block text-sm font-medium">Kode Voucher (opsional)</span>
                  <input
                    className="h-10 w-full rounded-[14px] border border-border bg-card px-3 text-sm outline-none focus:border-brand"
                    placeholder="Contoh: LE2026"
                    value={voucher}
                    onChange={(e) => setVoucher(e.target.value)}
                  />
                </label>
                <Button className="w-full" loading={busy} onClick={enroll}>
                  Beli {rupiah(pkg.price)}
                </Button>
                <p className="mt-2 text-xs text-muted">
                  Pembayaran masih dalam mode simulasi.
                </p>
              </>
            )}
          </Card>

          {enrollment && (
            <Card>
              <CardHeader title="Tulis Ulasan" />
              <form onSubmit={submitReview} className="space-y-3">
                <label className="block">
                  <span className="mb-1 block text-sm font-medium">Rating</span>
                  <select
                    className="h-10 w-full rounded-[14px] border border-border bg-card px-3 text-sm outline-none focus:border-brand"
                    value={rating}
                    onChange={(e) => setRating(Number(e.target.value))}
                  >
                    {[5, 4, 3, 2, 1].map((n) => (
                      <option key={n} value={n}>{'★'.repeat(n)} ({n})</option>
                    ))}
                  </select>
                </label>
                <textarea
                  className="min-h-20 w-full rounded-[14px] border border-border bg-card p-3 text-sm outline-none focus:border-brand"
                  placeholder="Ceritakan pengalaman Anda…"
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                />
                <Button type="submit" loading={busy}>Kirim Ulasan</Button>
              </form>
            </Card>
          )}
        </div>
      </div>
    </>
  );
}