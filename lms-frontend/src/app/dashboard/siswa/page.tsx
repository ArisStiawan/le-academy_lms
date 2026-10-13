'use client';

import Link from 'next/link';
import { Card, CardHeader, PageHeader, StatCard, Badge, Spinner, EmptyState } from '@/components/ui';
import { useAuth } from '@/lib/auth-context';
import { listOf, objOf, useApi, tanggal } from '@/lib/use-api';

interface Enrollment {
  id: number;
  progress_pct: number;
  enrolled_at: string;
  package: { id: number; title: string } | null;
  certificate: { status: string } | null;
}

interface LeaderboardData {
  leaderboard: { rank: number; user: { name: string }; total_xp: number; level: string | null }[];
  my_progress: { rank: number; total_xp: number; level: string | null; next_level: string | null; xp_to_next_level: number | null } | null;
}

export default function SiswaDashboard() {
  const { user } = useAuth();
  const enrollments = useApi<unknown>('/my/enrollments');
  const leaderboard = useApi<{ data: LeaderboardData }>('/gamification/leaderboard?limit=5');
  const certificates = useApi<unknown>('/certificates/mine');

  const myEnrollments = listOf<Enrollment>(enrollments.data);
  const lb = objOf<LeaderboardData>(leaderboard.data);
  const myCerts = listOf<{ id: number; status: string; certificate_code: string | null }>(certificates.data);

  const totalProgress =
    myEnrollments.length > 0
      ? Math.round(myEnrollments.reduce((s, e) => s + e.progress_pct, 0) / myEnrollments.length)
      : 0;

  return (
    <>
      <PageHeader
        title={`Halo, ${user?.name ?? 'Siswa'} 👋`}
        description="Ringkasan progres belajar, XP, dan sertifikat Anda."
      />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Paket Diikuti" value={myEnrollments.length} />
        <StatCard label="Rata-rata Progres" value={`${totalProgress}%`} tone="info" />
        <StatCard label="Total XP" value={lb?.my_progress?.total_xp ?? 0} tone="success" hint={lb?.my_progress?.level ?? undefined} />
        <StatCard label="Peringkat" value={lb?.my_progress?.rank ? `#${lb.my_progress.rank}` : '-'} tone="warning" />
      </div>

      {lb?.my_progress?.next_level && (
        <Card className="mt-4">
          <div className="flex items-center justify-between text-sm">
            <span>
              Menuju level <strong>{lb.my_progress.next_level}</strong>
            </span>
            <span className="text-muted">
              {lb.my_progress.xp_to_next_level ?? 0} XP lagi
            </span>
          </div>
          <div className="mt-2 h-2 overflow-hidden rounded-full bg-brand-soft">
            <div
              className="h-full rounded-full bg-brand transition-all"
              style={{ width: `${Math.min(100, 100 - ((lb.my_progress.xp_to_next_level ?? 0) / Math.max(1, lb.my_progress.total_xp + (lb.my_progress.xp_to_next_level ?? 0))) * 100)}%` }}
            />
          </div>
        </Card>
      )}

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader
            title="Paket Belajar Saya"
            action={<Link href="/dashboard/siswa/packages" className="text-sm text-brand hover:underline">Kelola →</Link>}
          />
          {enrollments.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : myEnrollments.length === 0 ? (
            <EmptyState title="Belum ada paket" description="Pilih paket belajar untuk memulai." />
          ) : (
            <ul className="space-y-3">
              {myEnrollments.slice(0, 4).map((e) => (
                <li key={e.id} className="rounded-[14px] border border-border p-3">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-medium">{e.package?.title ?? 'Paket'}</span>
                    <Badge tone={e.progress_pct >= 100 ? 'success' : 'info'}>{e.progress_pct}%</Badge>
                  </div>
                  <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-soft">
                    <div className="h-full rounded-full bg-brand" style={{ width: `${e.progress_pct}%` }} />
                  </div>
                  <p className="mt-1 text-xs text-muted">Terdaftar {tanggal(e.enrolled_at)}</p>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card>
          <CardHeader
            title="Leaderboard"
            action={<Link href="/dashboard/siswa/leaderboard" className="text-sm text-brand hover:underline">Lihat semua →</Link>}
          />
          {leaderboard.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : (
            <ul className="space-y-2">
              {(lb?.leaderboard ?? []).map((row) => (
                <li key={row.rank} className="flex items-center justify-between rounded-[14px] border border-border px-3 py-2 text-sm">
                  <span className="flex items-center gap-3">
                    <span className="w-6 font-semibold text-muted">#{row.rank}</span>
                    <span>{row.user.name}</span>
                  </span>
                  <span className="flex items-center gap-2">
                    <Badge tone="brand">{row.level ?? 'Pemula'}</Badge>
                    <span className="font-semibold">{row.total_xp} XP</span>
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      <div className="mt-6">
        <Card>
          <CardHeader
            title="Sertifikat Saya"
            action={<Link href="/dashboard/siswa/certificates" className="text-sm text-brand hover:underline">Kelola →</Link>}
          />
          {certificates.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : myCerts.length === 0 ? (
            <EmptyState title="Belum ada sertifikat" description="Selesaikan paket 100% untuk mengajukan sertifikat." />
          ) : (
            <div className="flex flex-wrap gap-2">
              {myCerts.map((c) => (
                <Badge key={c.id} tone={c.status === 'approved' ? 'success' : c.status === 'rejected' ? 'danger' : 'warning'}>
                  {c.certificate_code ?? c.status}
                </Badge>
              ))}
            </div>
          )}
        </Card>
      </div>
    </>
  );
}