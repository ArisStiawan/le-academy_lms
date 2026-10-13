'use client';

import { useState } from 'react';
import {
  Badge, Button, Card, CardHeader, EmptyState, ErrorNote, Input, PageHeader, Spinner,
} from '@/components/ui';
import { apiError, listOf, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Curriculum {
  id: number;
  title: string;
  description: string | null;
  status: string;
  modules_count?: number;
}

export default function MentorCurriculaPage() {
  const curricula = useApi<unknown>('/curricula');
  const [showForm, setShowForm] = useState(false);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  // Modul: form tambah modul per kurikulum
  const [moduleFor, setModuleFor] = useState<Curriculum | null>(null);
  const [moduleTitle, setModuleTitle] = useState('');

  const rows = listOf<Curriculum>(curricula.data);

  async function createCurriculum(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post('/curricula', { title, description });
      setTitle('');
      setDescription('');
      setShowForm(false);
      await curricula.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setSaving(false);
    }
  }

  async function addModule(e: React.FormEvent) {
    e.preventDefault();
    if (!moduleFor) return;
    setError(null);
    setSaving(true);
    try {
      await api.post(`/curricula/${moduleFor.id}/modules`, { title: moduleTitle });
      setModuleTitle('');
      setModuleFor(null);
      await curricula.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setSaving(false);
    }
  }

  async function togglePublish(c: Curriculum) {
    setError(null);
    try {
      await api.put(`/curricula/${c.id}`, { status: c.status === 'published' ? 'draft' : 'published' });
      await curricula.reload();
    } catch (err) {
      setError(apiError(err));
    }
  }

  async function remove(c: Curriculum) {
    if (!confirm(`Hapus kurikulum "${c.title}"?`)) return;
    setError(null);
    try {
      await api.delete(`/curricula/${c.id}`);
      await curricula.reload();
    } catch (err) {
      setError(apiError(err));
    }
  }

  return (
    <>
      <PageHeader
        title="Kurikulum Saya"
        description="Buat dan kelola kurikulum beserta modulnya."
        action={<Button onClick={() => setShowForm((v) => !v)}>{showForm ? 'Tutup' : '+ Kurikulum'}</Button>}
      />

      {error && <div className="mb-4"><ErrorNote>{error}</ErrorNote></div>}

      {showForm && (
        <Card className="mb-6">
          <CardHeader title="Kurikulum Baru" />
          <form onSubmit={createCurriculum} className="space-y-4">
            <Input label="Judul" required value={title} onChange={(e) => setTitle(e.target.value)} />
            <label className="block">
              <span className="mb-1 block text-sm font-medium">Deskripsi</span>
              <textarea
                className="min-h-24 w-full rounded-[14px] border border-border bg-card p-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/30"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </label>
            <Button type="submit" loading={saving}>Simpan</Button>
          </form>
        </Card>
      )}

      {moduleFor && (
        <Card className="mb-6">
          <CardHeader title={`Tambah Modul — ${moduleFor.title}`} />
          <form onSubmit={addModule} className="flex flex-wrap items-end gap-3">
            <div className="min-w-64 flex-1">
              <Input label="Judul Modul" required value={moduleTitle} onChange={(e) => setModuleTitle(e.target.value)} />
            </div>
            <Button type="submit" loading={saving}>Tambah</Button>
            <Button type="button" variant="ghost" onClick={() => setModuleFor(null)}>Batal</Button>
          </form>
        </Card>
      )}

      {curricula.loading ? (
        <div className="flex justify-center py-10"><Spinner className="h-8 w-8 text-brand" /></div>
      ) : rows.length === 0 ? (
        <EmptyState title="Belum ada kurikulum" description="Klik “+ Kurikulum” untuk memulai." />
      ) : (
        <div className="space-y-3">
          {rows.map((c) => (
            <Card key={c.id}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <h3 className="font-semibold">{c.title}</h3>
                    <Badge tone={c.status === 'published' ? 'success' : c.status === 'archived' ? 'danger' : 'neutral'}>
                      {c.status}
                    </Badge>
                  </div>
                  {c.description && <p className="mt-1 text-sm text-muted">{c.description}</p>}
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" onClick={() => setModuleFor(c)}>+ Modul</Button>
                  <Button size="sm" variant="outline" onClick={() => togglePublish(c)}>
                    {c.status === 'published' ? 'Jadikan Draft' : 'Publish'}
                  </Button>
                  <Button size="sm" variant="danger" onClick={() => remove(c)}>Hapus</Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}