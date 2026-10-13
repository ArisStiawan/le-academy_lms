'use client';

import { useState } from 'react';
import {
  Badge, Button, Card, CardHeader, EmptyState, ErrorNote, Input, PageHeader, Spinner,
} from '@/components/ui';
import { apiError, listOf, tanggal, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Curriculum { id: number; title: string }
interface Thread {
  id: number;
  title: string;
  body: string;
  is_closed: boolean;
  is_pinned: boolean;
  posts_count?: number;
  user?: { name: string };
  updated_at: string;
}
interface Post { id: number; body: string; user?: { name: string }; created_at: string }

export default function MentorForumPage() {
  const curricula = useApi<unknown>('/curricula');
  const [curriculumId, setCurriculumId] = useState<number | null>(null);
  const threads = useApi<unknown>(curriculumId ? `/forum/threads?curriculum_id=${curriculumId}` : null, [curriculumId]);

  const [openThread, setOpenThread] = useState<Thread | null>(null);
  const [threadDetail, setThreadDetail] = useState<{ thread: Thread; posts: Post[] } | null>(null);
  const [newTitle, setNewTitle] = useState('');
  const [newBody, setNewBody] = useState('');
  const [reply, setReply] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const curric = listOf<Curriculum>(curricula.data);
  const threadList = listOf<Thread>(threads.data);

  async function openThreadDetail(t: Thread) {
    setError(null);
    setOpenThread(t);
    try {
      const { data } = await api.get<{ data: Thread & { posts: Post[] } }>(`/forum/threads/${t.id}`);
      setThreadDetail({ thread: data.data, posts: data.data.posts ?? [] });
    } catch (err) {
      setError(apiError(err));
    }
  }

  async function createThread(e: React.FormEvent) {
    e.preventDefault();
    if (!curriculumId) return;
    setError(null);
    setBusy(true);
    try {
      await api.post('/forum/threads', { curriculum_id: curriculumId, title: newTitle, body: newBody });
      setNewTitle('');
      setNewBody('');
      await threads.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(false);
    }
  }

  async function sendReply(e: React.FormEvent) {
    e.preventDefault();
    if (!openThread) return;
    setError(null);
    setBusy(true);
    try {
      await api.post('/forum/posts', { thread_id: openThread.id, body: reply });
      setReply('');
      await openThreadDetail(openThread);
      await threads.reload();
    } catch (err) {
      setError(apiError(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <PageHeader title="Forum Diskusi" description="Diskusi kelas per kurikulum." />

      {error && <div className="mb-4"><ErrorNote>{error}</ErrorNote></div>}

      <Card className="mb-6">
        <CardHeader title="Pilih Kurikulum" />
        {curricula.loading ? (
          <Spinner className="h-6 w-6 text-brand" />
        ) : (
          <select
            className="h-10 w-full max-w-md rounded-[14px] border border-border bg-card px-3 text-sm outline-none focus:border-brand"
            value={curriculumId ?? ''}
            onChange={(e) => {
              setCurriculumId(e.target.value ? Number(e.target.value) : null);
              setOpenThread(null);
              setThreadDetail(null);
            }}
          >
            <option value="">— Pilih kurikulum —</option>
            {curric.map((c) => (
              <option key={c.id} value={c.id}>{c.title}</option>
            ))}
          </select>
        )}
      </Card>

      {curriculumId && (
        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader title="Thread" />
            <form onSubmit={createThread} className="mb-4 space-y-3 rounded-[14px] border border-border p-3">
              <Input placeholder="Judul thread" required value={newTitle} onChange={(e) => setNewTitle(e.target.value)} />
              <textarea
                className="min-h-20 w-full rounded-[14px] border border-border bg-card p-3 text-sm outline-none focus:border-brand"
                placeholder="Isi diskusi…"
                required
                value={newBody}
                onChange={(e) => setNewBody(e.target.value)}
              />
              <Button type="submit" size="sm" loading={busy}>Buat Thread</Button>
            </form>

            {threads.loading ? (
              <Spinner className="h-6 w-6 text-brand" />
            ) : threadList.length === 0 ? (
              <EmptyState title="Belum ada thread" />
            ) : (
              <ul className="space-y-2">
                {threadList.map((t) => (
                  <li key={t.id}>
                    <button
                      onClick={() => openThreadDetail(t)}
                      className={`w-full rounded-[14px] border px-3 py-2 text-left text-sm transition hover:bg-brand-soft ${
                        openThread?.id === t.id ? 'border-brand' : 'border-border'
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        <span className="font-medium">{t.title}</span>
                        {t.is_closed && <Badge tone="danger">ditutup</Badge>}
                        {t.is_pinned && <Badge tone="warning">pinned</Badge>}
                      </div>
                      <p className="mt-0.5 text-xs text-muted">
                        {t.user?.name ?? '-'} • {t.posts_count ?? 0} balasan • {tanggal(t.updated_at)}
                      </p>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card>
            <CardHeader title={threadDetail?.thread.title ?? 'Detail Thread'} />
            {!threadDetail ? (
              <EmptyState title="Pilih thread" description="Klik salah satu thread untuk melihat diskusi." />
            ) : (
              <>
                <div className="mb-4 max-h-64 space-y-3 overflow-y-auto rounded-[14px] border border-border p-3">
                  <div className="rounded-[14px] bg-brand-soft p-3 text-sm">
                    <p className="font-medium">{threadDetail.thread.user?.name ?? '-'}</p>
                    <p className="mt-1">{threadDetail.thread.body}</p>
                  </div>
                  {threadDetail.posts.map((p) => (
                    <div key={p.id} className="rounded-[14px] border border-border p-3 text-sm">
                      <p className="font-medium">{p.user?.name ?? '-'}</p>
                      <p className="mt-1">{p.body}</p>
                      <p className="mt-1 text-xs text-muted">{tanggal(p.created_at)}</p>
                    </div>
                  ))}
                </div>
                <form onSubmit={sendReply} className="space-y-3">
                  <textarea
                    className="min-h-20 w-full rounded-[14px] border border-border bg-card p-3 text-sm outline-none focus:border-brand"
                    placeholder={threadDetail.thread.is_closed ? 'Thread ditutup.' : 'Tulis balasan…'}
                    disabled={threadDetail.thread.is_closed}
                    required
                    value={reply}
                    onChange={(e) => setReply(e.target.value)}
                  />
                  <Button type="submit" size="sm" disabled={threadDetail.thread.is_closed} loading={busy}>
                    Kirim Balasan
                  </Button>
                </form>
              </>
            )}
          </Card>
        </div>
      )}
    </>
  );
}