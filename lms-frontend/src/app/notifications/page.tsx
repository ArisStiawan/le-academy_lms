'use client';

import { useState } from 'react';
import { Badge, Button, Card, CardHeader, EmptyState, ErrorNote, PageHeader, Spinner } from '@/components/ui';
import { apiError, listOf, tanggal, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Notif {
  id: number;
  type: string;
  title: string;
  message: string | null;
  is_read: boolean;
  created_at: string;
}

const TYPE_TONE: Record<string, 'info' | 'success' | 'warning' | 'danger' | 'neutral'> = {
  info: 'info', success: 'success', warning: 'warning', danger: 'danger',
};

export default function NotificationsPage() {
  const notifs = useApi<unknown>('/notifications');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | 'all' | null>(null);

  const rows = listOf<Notif>(notifs.data);
  const unread = rows.filter((n) => !n.is_read).length;

  async function markRead(n: Notif) {
    if (n.is_read) return;
    setError(null);
    setBusy(n.id);
    try {
      await api.patch(`/notifications/${n.id}/read`);
      await notifs.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  async function markAll() {
    setError(null);
    setBusy('all');
    try {
      await api.patch('/notifications/read-all');
      await notifs.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  async function remove(n: Notif) {
    setError(null);
    setBusy(n.id);
    try {
      await api.delete(`/notifications/${n.id}`);
      await notifs.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  return (
    <>
      <PageHeader
        title="Notifikasi"
        description={unread > 0 ? `${unread} notifikasi belum dibaca` : 'Semua notifikasi sudah dibaca'}
        action={
          unread > 0 ? (
            <Button variant="outline" loading={busy === 'all'} onClick={markAll}>Tandai Semua Dibaca</Button>
          ) : undefined
        }
      />

      {error && <div className="mb-4"><ErrorNote>{error}</ErrorNote></div>}

      <Card>
        <CardHeader title="Kotak Masuk" subtitle={`${rows.length} notifikasi`} />
        {notifs.loading ? (
          <div className="flex justify-center py-8"><Spinner className="h-8 w-8 text-brand" /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="Tidak ada notifikasi" description="Anda akan menerima pemberitahuan di sini." />
        ) : (
          <ul className="space-y-2">
            {rows.map((n) => (
              <li
                key={n.id}
                className={`flex items-start justify-between gap-3 rounded-[14px] border-2 px-3.5 py-3 text-sm ${
                  n.is_read ? 'border-border' : 'border-brand/40 bg-brand-soft'
                }`}
              >
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <Badge tone={TYPE_TONE[n.type] ?? 'neutral'}>{n.type}</Badge>
                    <span className="font-extrabold">{n.title}</span>
                    {!n.is_read && <span className="h-2 w-2 rounded-full bg-brand" />}
                  </div>
                  {n.message && <p className="mt-1 text-muted">{n.message}</p>}
                  <p className="mt-1 text-xs text-muted">{tanggal(n.created_at)}</p>
                </div>
                <div className="flex shrink-0 gap-2">
                  {!n.is_read && (
                    <Button size="sm" variant="outline" loading={busy === n.id} onClick={() => markRead(n)}>
                      Baca
                    </Button>
                  )}
                  <Button size="sm" variant="danger" loading={busy === n.id} onClick={() => remove(n)}>
                    Hapus
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </>
  );
}
