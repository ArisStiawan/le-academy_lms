'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Badge, Button, Card, EmptyState, ErrorNote, PageHeader, Spinner, StatCard } from '@/components/ui';
import { Ic } from '@/components/icons';
import { apiError, objOf, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Content {
  id: number;
  type: string;
  title: string;
  description: string | null;
  file_url: string | null;
  external_url: string | null;
  xp_reward: number;
  is_completed: boolean;
}

interface ModuleItem {
  id: number;
  title: string;
  description: string | null;
  curriculum: string | null;
  xp_reward: number;
  total: number;
  completed: number;
  progress_pct: number;
  contents: Content[];
}

interface MaterialsData {
  summary: { modules: number; contents: number; completed: number; xp_earned: number };
  modules: ModuleItem[];
}

const TYPE_LABEL: Record<string, string> = {
  video: 'Video',
  dokumen: 'Dokumen',
  link: 'Tautan',
  quiz: 'Kuis',
};

const TYPE_EMOJI: Record<string, string> = {
  video: '🎬',
  dokumen: '📄',
  link: '🔗',
  quiz: '📝',
};

export default function SiswaMateriPage() {
  const { data, loading, error, reload } = useApi<unknown>('/my/materials');
  const [busy, setBusy] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const payload = objOf<MaterialsData>(data);
  const modules = payload?.modules ?? [];
  const summary = payload?.summary;

  async function complete(content: Content) {
    setBusy(content.id);
    setNotice(null);
    try {
      const res = await api.post(`/contents/${content.id}/complete`);
      const isNew = (res.data?.data as { is_new?: boolean })?.is_new;
      setNotice(
        isNew && content.xp_reward > 0
          ? `Mantap! +${content.xp_reward} XP untuk “${content.title}”.`
          : `“${content.title}” sudah ditandai selesai.`,
      );
      await reload();
    } catch (err) {
      setNotice(apiError(err));
    } finally {
      setBusy(null);
    }
  }

  return (
    <>
      <PageHeader
        title="Materi Belajar"
        description="Semua materi dari paket yang kamu ikuti. Tandai selesai untuk mengumpulkan XP."
      />

      {error && (
        <div className="mb-4">
          <ErrorNote>{error}</ErrorNote>
        </div>
      )}
      {notice && (
        <div className="mb-4 rounded-[14px] border-2 border-mint/30 bg-mint-soft px-4 py-3 text-sm font-bold text-mint-dark">
          {notice}
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Modul" value={summary?.modules ?? 0} icon={<Ic.book className="h-6 w-6" />} />
        <StatCard label="Total Materi" value={summary?.contents ?? 0} tone="info" icon={<Ic.box className="h-6 w-6" />} />
        <StatCard label="Selesai" value={summary?.completed ?? 0} tone="success" icon={<Ic.check className="h-6 w-6" />} />
        <StatCard label="XP Terkumpul" value={summary?.xp_earned ?? 0} tone="warning" icon={<Ic.bolt className="h-6 w-6" />} />
      </div>

      <div className="mt-6 space-y-5">
        {loading ? (
          <div className="flex justify-center py-10">
            <Spinner className="h-8 w-8 text-brand" />
          </div>
        ) : modules.length === 0 ? (
          <EmptyState
            title="Belum ada materi"
            description="Materi akan muncul setelah kamu terdaftar di sebuah paket belajar."
          />
        ) : (
          modules.map((m) => (
            <Card key={m.id}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="font-display text-lg font-extrabold">{m.title}</h3>
                    {m.curriculum && <Badge tone="brand">{m.curriculum}</Badge>}
                  </div>
                  {m.description && <p className="mt-1 max-w-2xl text-sm font-semibold text-muted">{m.description}</p>}
                </div>
                <Badge tone={m.progress_pct >= 100 ? 'success' : 'info'}>
                  {m.completed}/{m.total} selesai
                </Badge>
              </div>

              <div className="mt-3 h-2 overflow-hidden rounded-full bg-brand-soft">
                <div className="h-full rounded-full bg-brand transition-all" style={{ width: `${m.progress_pct}%` }} />
              </div>

              <ul className="mt-4 space-y-2">
                {m.contents.length === 0 && (
                  <li className="rounded-[14px] border-2 border-dashed border-border px-4 py-3 text-sm font-semibold text-muted">
                    Modul ini belum punya materi.
                  </li>
                )}
                {m.contents.map((c) => (
                  <li
                    key={c.id}
                    className="flex flex-wrap items-center gap-3 rounded-[14px] border border-border px-3 py-3"
                  >
                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-[14px] bg-brand-soft text-lg">
                      {TYPE_EMOJI[c.type] ?? '📚'}
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-bold">{c.title}</span>
                        <Badge tone="neutral">{TYPE_LABEL[c.type] ?? c.type}</Badge>
                        {c.xp_reward > 0 && <Badge tone="warning">+{c.xp_reward} XP</Badge>}
                      </div>
                      {c.description && <p className="mt-0.5 line-clamp-2 text-xs font-semibold text-muted">{c.description}</p>}
                    </div>

                    <div className="flex items-center gap-2">
                      <Link href={`/dashboard/siswa/materi/${c.id}`}>
                        <Button size="sm" variant="outline">
                          {c.is_completed ? 'Baca Lagi' : 'Buka'}
                        </Button>
                      </Link>
                      {c.is_completed ? (
                        <Badge tone="success">
                          <Ic.check className="h-3.5 w-3.5" /> Selesai
                        </Badge>
                      ) : (
                        <Button size="sm" variant="success" loading={busy === c.id} onClick={() => complete(c)}>
                          Tandai Selesai
                        </Button>
                      )}
                    </div>
                  </li>
                ))}
              </ul>
            </Card>
          ))
        )}
      </div>
    </>
  );
}
