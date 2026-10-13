'use client';

import { useState } from 'react';
import {
  Badge, Button, Card, CardHeader, EmptyState, ErrorNote, Input, PageHeader, Spinner,
} from '@/components/ui';
import { apiError, listOf, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Student {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
}

interface Pkg { id: number; title: string }

export default function LembagaStudentsPage() {
  const students = useApi<unknown>('/users?role=siswa');
  const packages = useApi<unknown>('/manage/packages');
  const [emails, setEmails] = useState('');
  const [packageId, setPackageId] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const rows = listOf<Student>(students.data);
  const pkgs = listOf<Pkg>(packages.data);

  async function importStudents(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setNotice(null);
    setBusy(true);
    try {
      const list = emails.split(/[\n,;]+/).map((s) => s.trim()).filter(Boolean);
      const { data } = await api.post<{ summary?: unknown; new_accounts?: unknown[] }>(
        '/institution/students/import',
        { package_id: Number(packageId), emails: list },
      );
      const created = (data as { data?: { summary?: Record<string, number> } }).data?.summary;
      setNotice(
        `Import selesai: ${created?.created_and_enrolled ?? 0} akun baru, ` +
        `${created?.enrolled_existing ?? 0} terdaftar, ${created?.skipped_already_enrolled ?? 0} dilewati.`,
      );
      setEmails('');
      await students.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <PageHeader title="Siswa" description="Import dan kelola siswa lembaga Anda." />

      {error && <div className="mb-4"><ErrorNote>{error}</ErrorNote></div>}
      {notice && (
        <div className="mb-4 rounded-[14px] border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-600 dark:text-emerald-400">
          {notice}
        </div>
      )}

      <Card className="mb-6">
        <CardHeader title="Import Siswa" subtitle="Tempel beberapa email (satu per baris) untuk mendaftarkan ke paket." />
        <form onSubmit={importStudents} className="space-y-4">
          <label className="block">
            <span className="mb-1 block text-sm font-medium">Pilih Paket</span>
            <select
              className="h-10 w-full rounded-[14px] border border-border bg-card px-3 text-sm outline-none focus:border-brand"
              required
              value={packageId}
              onChange={(e) => setPackageId(e.target.value)}
            >
              <option value="">— Pilih paket —</option>
              {pkgs.map((p) => (
                <option key={p.id} value={p.id}>{p.title}</option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium">Email Siswa</span>
            <textarea
              className="min-h-28 w-full rounded-[14px] border border-border bg-card p-3 text-sm outline-none focus:border-brand"
              placeholder={'siswa1@email.com\nsiswa2@email.com'}
              required
              value={emails}
              onChange={(e) => setEmails(e.target.value)}
            />
          </label>
          <Button type="submit" loading={busy} disabled={!packageId}>Import</Button>
        </form>
      </Card>

      <Card>
        <CardHeader title="Daftar Siswa" subtitle={`${rows.length} siswa`} />
        {students.loading ? (
          <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="Belum ada siswa" />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-muted">
                  <th className="py-2">Nama</th>
                  <th className="py-2">Email</th>
                  <th className="py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((u) => (
                  <tr key={u.id} className="border-b border-border last:border-0">
                    <td className="py-2 font-medium">{u.name}</td>
                    <td className="py-2 text-muted">{u.email}</td>
                    <td className="py-2">
                      <Badge tone={u.is_active ? 'success' : 'danger'}>{u.is_active ? 'aktif' : 'nonaktif'}</Badge>
                    </td>
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