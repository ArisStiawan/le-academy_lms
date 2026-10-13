'use client';

import { Badge, Card, CardHeader, EmptyState, Spinner } from '@/components/ui';
import { listOf, rupiah, useApi } from '@/lib/use-api';

interface Pkg {
  id: number;
  title: string;
  description: string | null;
  price: number;
  is_active: boolean;
  enrollments_count?: number;
  modules_count?: number;
  institution?: { name: string } | null;
}

/** Daftar paket untuk pengelola (termasuk non-aktif & hitungan enrollment). */
export function PackageManager({ showInstitution = false }: { showInstitution?: boolean }) {
  const packages = useApi<unknown>('/manage/packages');
  const rows = listOf<Pkg>(packages.data);

  return (
    <Card>
      <CardHeader title="Daftar Paket" subtitle={`${rows.length} paket`} />
      {packages.loading ? (
        <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
      ) : rows.length === 0 ? (
        <EmptyState title="Belum ada paket" />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-muted">
                <th className="py-2">Paket</th>
                {showInstitution && <th className="py-2">Lembaga</th>}
                <th className="py-2">Harga</th>
                <th className="py-2">Modul</th>
                <th className="py-2">Peserta</th>
                <th className="py-2">Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((p) => (
                <tr key={p.id} className="border-b border-border last:border-0">
                  <td className="py-2">
                    <div className="font-medium">{p.title}</div>
                    {p.description && <div className="text-xs text-muted line-clamp-1">{p.description}</div>}
                  </td>
                  {showInstitution && <td className="py-2">{p.institution?.name ?? 'Global'}</td>}
                  <td className="py-2">{rupiah(p.price)}</td>
                  <td className="py-2">{p.modules_count ?? 0}</td>
                  <td className="py-2">{p.enrollments_count ?? 0}</td>
                  <td className="py-2">
                    <Badge tone={p.is_active ? 'success' : 'neutral'}>{p.is_active ? 'aktif' : 'nonaktif'}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}