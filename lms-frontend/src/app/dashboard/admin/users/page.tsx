'use client';

import { useState } from 'react';
import { Badge, Card, CardHeader, EmptyState, Input, PageHeader, Spinner } from '@/components/ui';
import { listOf, tanggal, useApi } from '@/lib/use-api';

interface User {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  institution?: { id: number; name: string } | null;
  roles?: { name: string }[];
  created_at: string;
}

const ROLE_TONE: Record<string, 'danger' | 'info' | 'brand' | 'success' | 'warning' | 'neutral'> = {
  admin: 'danger', mentor: 'info', siswa: 'brand', karyawan: 'success', lembaga: 'warning',
};

export default function AdminUsersPage() {
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const query = `/users?${search ? `search=${encodeURIComponent(search)}&` : ''}${role ? `role=${role}` : ''}`;
  const users = useApi<unknown>(query, [search, role]);

  const rows = listOf<User>(users.data);

  return (
    <>
      <PageHeader title="Pengguna" description="Daftar seluruh pengguna platform." />

      <Card className="mb-6">
        <div className="flex flex-wrap gap-3">
          <div className="min-w-56 flex-1">
            <Input placeholder="Cari nama atau email…" value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
          <select
            className="h-10 rounded-[14px] border border-border bg-card px-3 text-sm outline-none focus:border-brand"
            value={role}
            onChange={(e) => setRole(e.target.value)}
          >
            <option value="">Semua Role</option>
            <option value="admin">Admin</option>
            <option value="mentor">Mentor</option>
            <option value="siswa">Siswa</option>
            <option value="karyawan">Karyawan</option>
            <option value="lembaga">Lembaga</option>
          </select>
        </div>
      </Card>

      <Card>
        <CardHeader title="Daftar Pengguna" subtitle={`${rows.length} pengguna ditampilkan`} />
        {users.loading ? (
          <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="Tidak ada pengguna" />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted">
                  <th className="py-2">Nama</th>
                  <th className="py-2">Role</th>
                  <th className="py-2">Lembaga</th>
                  <th className="py-2">Status</th>
                  <th className="py-2">Terdaftar</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((u) => (
                  <tr key={u.id} className="border-b border-border last:border-0">
                    <td className="py-2">
                      <div className="font-medium">{u.name}</div>
                      <div className="text-xs text-muted">{u.email}</div>
                    </td>
                    <td className="py-2">
                      <div className="flex flex-wrap gap-1">
                        {(u.roles ?? []).map((r) => (
                          <Badge key={r.name} tone={ROLE_TONE[r.name] ?? 'neutral'}>{r.name}</Badge>
                        ))}
                      </div>
                    </td>
                    <td className="py-2">{u.institution?.name ?? '—'}</td>
                    <td className="py-2">
                      <Badge tone={u.is_active ? 'success' : 'danger'}>{u.is_active ? 'aktif' : 'nonaktif'}</Badge>
                    </td>
                    <td className="py-2 text-muted">{tanggal(u.created_at)}</td>
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