'use client';

import { useState } from 'react';
import {
  Badge, Button, Card, CardHeader, EmptyState, ErrorNote, Input, PageHeader, Spinner,
} from '@/components/ui';
import { apiError, listOf, tanggal, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Schedule {
  id: number;
  title: string;
  type: string;
  description: string | null;
  start_at: string;
  end_at: string | null;
  zoom_url: string | null;
  participants_count?: number;
  mentor?: { name: string };
}

export default function MentorSchedulesPage() {
  const schedules = useApi<unknown>('/schedules');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    title: '', type: 'bimbingan', start_at: '', end_at: '', zoom_url: '', description: '',
  });
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const rows = listOf<Schedule>(schedules.data);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post('/schedules', {
        title: form.title,
        type: form.type,
        start_at: form.start_at,
        end_at: form.end_at || undefined,
        zoom_url: form.zoom_url || undefined,
        description: form.description || undefined,
      });
      setForm({ title: '', type: 'bimbingan', start_at: '', end_at: '', zoom_url: '', description: '' });
      setShowForm(false);
      await schedules.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setSaving(false);
    }
  }

  async function remove(s: Schedule) {
    if (!confirm(`Hapus jadwal "${s.title}"?`)) return;
    setError(null);
    try {
      await api.delete(`/schedules/${s.id}`);
      await schedules.reload();
    } catch (err) {
      setError(apiError(err));
    }
  }

  return (
    <>
      <PageHeader
        title="Jadwal Bimbingan & Webinar"
        description="Buat sesi daring dan bagikan tautan Zoom ke peserta."
        action={<Button onClick={() => setShowForm((v) => !v)}>{showForm ? 'Tutup' : '+ Jadwal'}</Button>}
      />

      {error && <div className="mb-4"><ErrorNote>{error}</ErrorNote></div>}

      {showForm && (
        <Card className="mb-6">
          <CardHeader title="Jadwal Baru" />
          <form onSubmit={create} className="grid gap-4 sm:grid-cols-2">
            <Input label="Judul" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
            <label className="block">
              <span className="mb-1 block text-sm font-medium">Tipe</span>
              <select
                className="h-10 w-full rounded-[14px] border border-border bg-card px-3 text-sm outline-none focus:border-brand"
                value={form.type}
                onChange={(e) => setForm({ ...form, type: e.target.value })}
              >
                <option value="bimbingan">Bimbingan</option>
                <option value="webinar">Webinar</option>
              </select>
            </label>
            <Input label="Mulai" type="datetime-local" required value={form.start_at} onChange={(e) => setForm({ ...form, start_at: e.target.value })} />
            <Input label="Selesai (opsional)" type="datetime-local" value={form.end_at} onChange={(e) => setForm({ ...form, end_at: e.target.value })} />
            <Input label="Tautan Zoom (opsional)" type="url" placeholder="https://zoom.us/j/..." value={form.zoom_url} onChange={(e) => setForm({ ...form, zoom_url: e.target.value })} />
            <Input label="Deskripsi (opsional)" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
            <div className="sm:col-span-2">
              <Button type="submit" loading={saving}>Simpan Jadwal</Button>
            </div>
          </form>
        </Card>
      )}

      {schedules.loading ? (
        <div className="flex justify-center py-10"><Spinner className="h-8 w-8 text-brand" /></div>
      ) : rows.length === 0 ? (
        <EmptyState title="Belum ada jadwal" description="Klik “+ Jadwal” untuk membuat sesi pertama." />
      ) : (
        <div className="space-y-3">
          {rows.map((s) => (
            <Card key={s.id}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="font-medium">{s.title}</h3>
                    <Badge tone={s.type === 'webinar' ? 'info' : 'brand'}>{s.type}</Badge>
                  </div>
                  <p className="mt-1 text-sm text-muted">
                    {tanggal(s.start_at)}{s.end_at ? ` – ${tanggal(s.end_at)}` : ''}
                  </p>
                  {s.zoom_url && (
                    <a href={s.zoom_url} target="_blank" rel="noreferrer" className="mt-1 inline-block text-sm text-brand hover:underline">
                      🔗 Tautan Zoom
                    </a>
                  )}
                </div>
                <Button size="sm" variant="danger" onClick={() => remove(s)}>Hapus</Button>
              </div>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}