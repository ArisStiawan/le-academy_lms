'use client';

import Link from 'next/link';
import { Badge, Card, CardHeader, EmptyState, PageHeader, Spinner, StatCard } from '@/components/ui';
import { useAuth } from '@/lib/auth-context';
import { listOf, objOf, tanggal, useApi } from '@/lib/use-api';

interface MyProgress {
  rank: number;
  total_xp: number;
  level: string | null;
}

export default function MentorDashboard() {
  const { user } = useAuth();
  const curricula = useApi<unknown>('/curricula');
  const schedules = useApi<unknown>('/schedules');
  const students = useApi<unknown>('/users?role=siswa');
  const board = useApi<unknown>('/gamification/leaderboard?limit=1');

  const curric = listOf<{ id: number; title: string; status: string }>(curricula.data);
  const sch = listOf<{ id: number; title: string; type: string; start_at: string }>(schedules.data);
  const studentRows = listOf<unknown>(students.data);
  const progress = objOf<{ my_progress: MyProgress | null }>(board.data)?.my_progress ?? null;

  return (
    <>
      <PageHeader
        title={`Dasbor Mentor — ${user?.name ?? ''}`}
        description="Kelola kurikulum, jadwal bimbingan, dan forum diskusi."
      />

      <div className="grid gap-4 sm:grid-cols-4">
        <StatCard label="Kurikulum" value={curric.length} />
        <StatCard label="Jadwal Aktif" value={sch.length} tone="info" />
        <StatCard label="Siswa Terdaftar" value={studentRows.length} tone="success" />
        <StatCard
          label="Level Anda"
          value={progress?.level ?? '—'}
          tone="warning"
          hint={progress ? `${progress.total_xp} XP · Peringkat #${progress.rank}` : 'Belum ada XP'}
        />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader
            title="Kurikulum Saya"
            action={<Link href="/dashboard/mentor/curricula" className="text-sm text-brand hover:underline">Kelola →</Link>}
          />
          {curricula.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : curric.length === 0 ? (
            <EmptyState title="Belum ada kurikulum" description="Buat kurikulum pertama Anda." />
          ) : (
            <ul className="space-y-2">
              {curric.slice(0, 5).map((c) => (
                <li key={c.id} className="flex items-center justify-between rounded-[14px] border border-border px-3 py-2 text-sm">
                  <span>{c.title}</span>
                  <Badge tone={c.status === 'published' ? 'success' : 'neutral'}>{c.status}</Badge>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card>
          <CardHeader
            title="Jadwal Bimbingan"
            action={<Link href="/dashboard/mentor/schedules" className="text-sm text-brand hover:underline">Kelola →</Link>}
          />
          {schedules.loading ? (
            <Spinner className="h-6 w-6 text-brand" />
          ) : sch.length === 0 ? (
            <EmptyState title="Belum ada jadwal" description="Buat sesi bimbingan atau webinar." />
          ) : (
            <ul className="space-y-2">
              {sch.slice(0, 5).map((s) => (
                <li key={s.id} className="rounded-[14px] border border-border px-3 py-2 text-sm">
                  <div className="flex items-center justify-between">
                    <span>{s.title}</span>
                    <Badge tone={s.type === 'webinar' ? 'info' : 'brand'}>{s.type}</Badge>
                  </div>
                  <p className="mt-0.5 text-xs text-muted">{tanggal(s.start_at)}</p>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </>
  );
}