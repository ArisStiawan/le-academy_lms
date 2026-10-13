'use client';

import { Badge, Card, CardHeader, EmptyState, PageHeader, Spinner } from '@/components/ui';
import { objOf, useApi } from '@/lib/use-api';

interface LB {
  leaderboard: { rank: number; user: { id: number; name: string }; total_xp: number; level: string | null }[];
  my_progress: {
    rank: number; total_xp: number; level: string | null;
    min_xp: number | null; next_level: string | null; xp_to_next_level: number | null;
  } | null;
}

export default function LeaderboardPage() {
  const { data, loading } = useApi<{ data: LB }>('/gamification/leaderboard?limit=50');
  const lb = objOf<LB>(data);

  return (
    <>
      <PageHeader title="Leaderboard" description="Peringkat pengguna berdasarkan total XP." />

      {lb?.my_progress && (
        <Card className="mb-6">
          <div className="grid gap-4 sm:grid-cols-4">
            <div>
              <p className="text-sm text-muted">Peringkat Anda</p>
              <p className="text-xl font-semibold">#{lb.my_progress.rank}</p>
            </div>
            <div>
              <p className="text-sm text-muted">Total XP</p>
              <p className="text-xl font-semibold">{lb.my_progress.total_xp}</p>
            </div>
            <div>
              <p className="text-sm text-muted">Level</p>
              <p className="text-xl font-semibold">{lb.my_progress.level ?? 'Pemula'}</p>
            </div>
            <div>
              <p className="text-sm text-muted">Menuju</p>
              <p className="text-xl font-semibold">
                {lb.my_progress.next_level ?? 'Level Maksimal'}
              </p>
            </div>
          </div>
        </Card>
      )}

      <Card>
        <CardHeader title="50 Teratas" />
        {loading ? (
          <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
        ) : !lb || lb.leaderboard.length === 0 ? (
          <EmptyState title="Belum ada data XP" description="Selesaikan materi untuk mendapatkan XP." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted">
                  <th className="py-2 w-16">Rank</th>
                  <th className="py-2">Nama</th>
                  <th className="py-2">Level</th>
                  <th className="py-2 text-right">XP</th>
                </tr>
              </thead>
              <tbody>
                {lb.leaderboard.map((row) => (
                  <tr key={row.rank} className="border-b border-border last:border-0">
                    <td className="py-2 font-semibold text-muted">#{row.rank}</td>
                    <td className="py-2">{row.user.name}</td>
                    <td className="py-2"><Badge tone="brand">{row.level ?? 'Pemula'}</Badge></td>
                    <td className="py-2 text-right font-semibold">{row.total_xp}</td>
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