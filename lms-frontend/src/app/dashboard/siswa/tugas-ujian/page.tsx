'use client';

import Link from 'next/link';
import { Badge, Button, Card, EmptyState, ErrorNote, PageHeader, Spinner, StatCard } from '@/components/ui';
import { Ic } from '@/components/icons';
import { objOf, tanggal, useApi } from '@/lib/use-api';

interface Assessment {
  id: number;
  type: 'tugas' | 'ujian' | string;
  title: string;
  module: string | null;
  curriculum: string | null;
  max_score: number;
  passing_score: number | null;
  xp_reward: number;
  due_at: string | null;
  status: 'belum' | 'terkumpul' | 'dinilai' | 'terlambat' | string;
  is_submitted: boolean;
  is_graded: boolean;
  is_overdue: boolean;
  score: number | null;
  feedback: string | null;
  submitted_at: string | null;
}

interface AssessmentsData {
  summary: {
    total: number;
    tugas: number;
    ujian: number;
    belum: number;
    terkumpul: number;
    dinilai: number;
    terlambat: number;
  };
  assessments: Assessment[];
}

const STATUS_TONE: Record<string, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  belum: 'neutral',
  terkumpul: 'info',
  dinilai: 'success',
  terlambat: 'danger',
};

const STATUS_LABEL: Record<string, string> = {
  belum: 'Belum dikumpul',
  terkumpul: 'Terkumpul',
  dinilai: 'Sudah dinilai',
  terlambat: 'Terlambat',
};

export default function SiswaTugasUjianPage() {
  const { data, loading, error } = useApi<unknown>('/my/assessments');

  const payload = objOf<AssessmentsData>(data);
  const rows = payload?.assessments ?? [];
  const summary = payload?.summary;

  return (
    <>
      <PageHeader
        title="Tugas & Ujian"
        description="Kerjakan dan kumpulkan tugas serta ujian dari paket belajarmu."
      />

      {error && (
        <div className="mb-4">
          <ErrorNote>{error}</ErrorNote>
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Total" value={summary?.total ?? 0} icon={<Ic.clipboard className="h-6 w-6" />} />
        <StatCard label="Tugas" value={summary?.tugas ?? 0} tone="info" icon={<Ic.book className="h-6 w-6" />} />
        <StatCard label="Belum Dikumpul" value={summary?.belum ?? 0} tone="warning" icon={<Ic.sparkle className="h-6 w-6" />} />
        <StatCard label="Sudah Dinilai" value={summary?.dinilai ?? 0} tone="success" icon={<Ic.award className="h-6 w-6" />} />
      </div>

      <div className="mt-6 space-y-3">
        {loading ? (
          <div className="flex justify-center py-10">
            <Spinner className="h-8 w-8 text-brand" />
          </div>
        ) : rows.length === 0 ? (
          <EmptyState
            title="Belum ada tugas atau ujian"
            description="Tugas dan ujian dari paketmu akan muncul di sini."
          />
        ) : (
          rows.map((a) => (
            <Card key={a.id}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="font-display text-lg font-extrabold">{a.title}</h3>
                    <Badge tone={a.type === 'ujian' ? 'danger' : 'brand'}>
                      {a.type === 'ujian' ? 'Ujian' : 'Tugas'}
                    </Badge>
                    <Badge tone={STATUS_TONE[a.status] ?? 'neutral'}>
                      {STATUS_LABEL[a.status] ?? a.status}
                    </Badge>
                  </div>
                  <p className="mt-1 text-sm font-semibold text-muted">
                    {a.module}
                    {a.curriculum ? ` · ${a.curriculum}` : ''}
                  </p>
                  {a.due_at && (
                    <p className={`mt-1 text-sm font-bold ${a.is_overdue && !a.is_submitted ? 'text-coral-dark' : 'text-muted'}`}>
                      Deadline: {tanggal(a.due_at)}
                    </p>
                  )}
                </div>

                <div className="flex flex-col items-end gap-2 text-right">
                  {a.xp_reward > 0 && <Badge tone="warning">+{a.xp_reward} XP</Badge>}
                  {a.is_graded ? (
                    <span className="font-display text-2xl font-extrabold text-mint-dark">
                      {a.score}
                      <span className="text-sm text-muted"> / {a.max_score}</span>
                    </span>
                  ) : (
                    <span className="text-sm font-semibold text-muted">Nilai maks {a.max_score}</span>
                  )}
                  {!a.is_submitted ? (
                    <Link href={`/dashboard/siswa/tugas-ujian/${a.id}`}>
                      <Button size="sm">Kerjakan</Button>
                    </Link>
                  ) : (
                    <Link href={`/dashboard/siswa/tugas-ujian/${a.id}`}>
                      <Button size="sm" variant="outline">
                        {a.is_graded ? 'Lihat Hasil' : 'Ubah Jawaban'}
                      </Button>
                    </Link>
                  )}
                </div>
              </div>

              {a.is_graded && a.feedback && (
                <p className="mt-3 rounded-[14px] bg-brand-soft px-4 py-3 text-sm font-semibold text-brand-dark">
                  💬 {a.feedback}
                </p>
              )}
            </Card>
          ))
        )}
      </div>
    </>
  );
}
