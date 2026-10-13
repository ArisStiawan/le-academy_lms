'use client';

import { Badge, Card, CardHeader, EmptyState, PageHeader, Spinner } from '@/components/ui';
import { listOf, useApi } from '@/lib/use-api';

interface Enrollment {
  id: number;
  progress_pct: number;
  enrolled_at: string;
  package: { id: number; title: string; description: string | null } | null;
}

export default function KaryawanTrainingPage() {
  const enrollments = useApi<unknown>('/my/enrollments');
  const rows = listOf<Enrollment>(enrollments.data);

  return (
    <>
      <PageHeader title="Pelatihan" description="Daftar pelatihan internal yang Anda ikuti." />

      {enrollments.loading ? (
        <div className="flex justify-center py-10"><Spinner className="h-8 w-8 text-brand" /></div>
      ) : rows.length === 0 ? (
        <EmptyState title="Belum ada pelatihan" description="Hubungi admin untuk didaftarkan ke pelatihan." />
      ) : (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {rows.map((e) => (
            <Card key={e.id} className="flex flex-col">
              <h3 className="text-lg font-semibold">{e.package?.title ?? 'Pelatihan'}</h3>
              <p className="mt-1 line-clamp-3 flex-1 text-sm text-muted">
                {e.package?.description ?? 'Tanpa deskripsi.'}
              </p>
              <div className="mt-4">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted">Progres</span>
                  <Badge tone={e.progress_pct >= 100 ? 'success' : 'info'}>{e.progress_pct}%</Badge>
                </div>
                <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-brand-soft">
                  <div className="h-full rounded-full bg-brand" style={{ width: `${e.progress_pct}%` }} />
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}