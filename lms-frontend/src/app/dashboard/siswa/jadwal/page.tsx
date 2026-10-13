'use client';

import { useState } from 'react';
import { Badge, Button, Card, EmptyState, ErrorNote, PageHeader, Spinner } from '@/components/ui';
import { Ic } from '@/components/icons';
import { apiError, listOf, tanggal, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface ScheduleItem {
  id: number;
  title: string;
  type: string;
  description: string | null;
  start_at: string;
  end_at: string | null;
  zoom_url: string | null;
  participants_count?: number;
  mentor?: { name: string };
  is_registered?: boolean;
}

type Tab = 'tersedia' | 'saya';

function ScheduleCard({
  s,
  busy,
  onToggle,
}: {
  s: ScheduleItem;
  busy: boolean;
  onToggle: (s: ScheduleItem) => void;
}) {
  const registered = !!s.is_registered;
  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-display text-lg font-extrabold">{s.title}</h3>
            <Badge tone={s.type === 'webinar' ? 'info' : 'brand'}>
              {s.type === 'webinar' ? 'Webinar' : 'Bimbingan'}
            </Badge>
            {registered && (
              <Badge tone="success">
                <Ic.check className="h-3.5 w-3.5" /> Terdaftar
              </Badge>
            )}
          </div>
          <p className="mt-1 text-sm font-bold text-foreground">
            <Ic.calendar className="mr-1 inline h-4 w-4 align-[-3px]" />
            {tanggal(s.start_at)}
            {s.end_at ? ` – ${tanggal(s.end_at)}` : ''}
          </p>
          {s.description && <p className="mt-1 max-w-2xl text-sm font-semibold text-muted">{s.description}</p>}
          <p className="mt-1 text-xs font-semibold text-muted">
            Mentor: {s.mentor?.name ?? '—'}
            {typeof s.participants_count === 'number' ? ` · ${s.participants_count} peserta` : ''}
          </p>
        </div>

        <div className="flex flex-col items-end gap-2">
          {s.zoom_url && registered && (
            <a href={s.zoom_url} target="_blank" rel="noreferrer">
              <Button size="sm" variant="outline">
                <Ic.rocket className="h-4 w-4" /> Buka Zoom
              </Button>
            </a>
          )}
          <Button
            size="sm"
            variant={registered ? 'danger' : 'primary'}
            loading={busy}
            onClick={() => onToggle(s)}
          >
            {registered ? 'Batal Daftar' : 'Daftar'}
          </Button>
        </div>
      </div>
    </Card>
  );
}

export default function SiswaJadwalPage() {
  const [tab, setTab] = useState<Tab>('tersedia');
  const [busy, setBusy] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const available = useApi<unknown>('/schedules?type=bimbingan');
  const mine = useApi<unknown>('/my/schedules?type=bimbingan');

  // /schedules publik tak mengembalikan is_registered (belum terautentikasi),
  // jadi status "terdaftar" diambil dari /my/schedules lalu digabung.
  const registeredIds = new Set(listOf<ScheduleItem>(mine.data).map((s) => s.id));

  const source = tab === 'tersedia' ? available.data : mine.data;
  const rows = listOf<ScheduleItem>(source).map((s) =>
    tab === 'tersedia' ? { ...s, is_registered: registeredIds.has(s.id) } : s,
  );
  const loading = tab === 'tersedia' ? available.loading : mine.loading;
  const fetchError = tab === 'tersedia' ? available.error : mine.error;

  async function toggle(s: ScheduleItem) {
    setBusy(s.id);
    setError(null);
    setNotice(null);
    try {
      if (s.is_registered) {
        await api.post(`/schedules/${s.id}/unregister`);
        setNotice(`Pendaftaran “${s.title}” dibatalkan.`);
      } else {
        await api.post(`/schedules/${s.id}/register`);
        setNotice(`Berhasil mendaftar “${s.title}”. Tautan Zoom tersedia di tab “Jadwal Saya”.`);
      }
      await Promise.all([available.reload(), mine.reload()]);
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  return (
    <>
      <PageHeader
        title="Jadwal Bimbingan Daring"
        description="Ikuti sesi bimbingan online bersama mentor melalui Zoom."
      />

      {(error || fetchError) && (
        <div className="mb-4">
          <ErrorNote>{error ?? fetchError}</ErrorNote>
        </div>
      )}
      {notice && (
        <div className="mb-4 rounded-[14px] border-2 border-mint/30 bg-mint-soft px-4 py-3 text-sm font-bold text-mint-dark">
          {notice}
        </div>
      )}

      <div className="mb-5 inline-flex rounded-[14px] border-2 border-border bg-card p-1">
        <button
          onClick={() => setTab('tersedia')}
          className={`rounded-[10px] px-4 py-2 text-sm font-extrabold transition ${
            tab === 'tersedia' ? 'bg-brand text-brand-fg' : 'text-muted hover:text-brand-dark'
          }`}
        >
          Jadwal Tersedia
        </button>
        <button
          onClick={() => setTab('saya')}
          className={`rounded-[10px] px-4 py-2 text-sm font-extrabold transition ${
            tab === 'saya' ? 'bg-brand text-brand-fg' : 'text-muted hover:text-brand-dark'
          }`}
        >
          Jadwal Saya
        </button>
      </div>

      {loading ? (
        <div className="flex justify-center py-10">
          <Spinner className="h-8 w-8 text-brand" />
        </div>
      ) : rows.length === 0 ? (
        <EmptyState
          title={tab === 'tersedia' ? 'Belum ada jadwal bimbingan' : 'Kamu belum mendaftar bimbingan'}
          description={
            tab === 'tersedia'
              ? 'Jadwal bimbingan daring akan tampil di sini.'
              : 'Buka tab “Jadwal Tersedia” untuk memilih sesi.'
          }
        />
      ) : (
        <div className="space-y-3">
          {rows.map((s) => (
            <ScheduleCard key={s.id} s={s} busy={busy === s.id} onToggle={toggle} />
          ))}
        </div>
      )}
    </>
  );
}
