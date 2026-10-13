'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Badge, Button, Card, EmptyState, ErrorNote, PageHeader, Spinner } from '@/components/ui';
import { Ic } from '@/components/icons';
import { apiError, objOf, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface MaterialDetail {
  id: number;
  type: string;
  title: string;
  description: string | null;
  body: string | null;
  file_url: string | null;
  external_url: string | null;
  xp_reward: number;
  is_completed: boolean;
  module: { id: number; title: string; description: string | null; curriculum: string | null };
  module_progress: { total: number; completed: number; progress_pct: number };
}

const TYPE_LABEL: Record<string, string> = { video: 'Video', dokumen: 'Dokumen', link: 'Tautan', quiz: 'Kuis' };
const TYPE_EMOJI: Record<string, string> = { video: '🎬', dokumen: '📄', link: '🔗', quiz: '📝' };

/** Tebal ringan: **teks** → <strong>. */
function inline(text: string): ReactNode {
  return text.split(/(\*\*[^*]+\*\*)/g).map((part, i) =>
    part.startsWith('**') && part.endsWith('**') ? (
      <strong key={i} className="font-extrabold text-foreground">
        {part.slice(2, -2)}
      </strong>
    ) : (
      <span key={i}>{part}</span>
    ),
  );
}

/** Render isi bacaan ringan: '## ' judul, '- ' butir, paragraf biasa. */
function BodyView({ body }: { body: string }) {
  const blocks: ReactNode[] = [];
  let list: string[] = [];

  const flush = (key: string) => {
    if (list.length === 0) return;
    blocks.push(
      <ul key={key} className="space-y-2">
        {list.map((li, i) => (
          <li key={i} className="flex gap-2.5 text-[15px] font-semibold leading-relaxed text-foreground/90">
            <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-brand" />
            <span>{inline(li)}</span>
          </li>
        ))}
      </ul>,
    );
    list = [];
  };

  body.split('\n').forEach((raw, i) => {
    const line = raw.trim();
    if (!line) {
      flush(`list-${i}`);
      return;
    }
    if (line.startsWith('## ')) {
      flush(`list-${i}`);
      blocks.push(
        <h2 key={i} className="font-display text-lg font-extrabold text-brand-dark">
          {line.slice(3)}
        </h2>,
      );
      return;
    }
    if (line.startsWith('- ')) {
      list.push(line.slice(2));
      return;
    }
    flush(`list-${i}`);
    blocks.push(
      <p key={i} className="text-[15px] font-semibold leading-relaxed text-foreground/90">
        {inline(line)}
      </p>,
    );
  });
  flush('list-end');

  return <div className="space-y-4">{blocks}</div>;
}

export default function SiswaMateriDetailPage() {
  const params = useParams<{ id: string }>();
  const { data, loading, error, reload } = useApi<unknown>(`/my/materials/${params.id}`);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  const m = objOf<MaterialDetail>(data);

  async function complete() {
    if (!m) return;
    setBusy(true);
    setErr(null);
    setNotice(null);
    try {
      const res = await api.post(`/contents/${m.id}/complete`);
      const isNew = (res.data?.data as { is_new?: boolean })?.is_new;
      setNotice(
        isNew && m.xp_reward > 0 ? `Mantap! +${m.xp_reward} XP untuk “${m.title}”.` : `“${m.title}” sudah ditandai selesai.`,
      );
      await reload();
    } catch (e) {
      setErr(apiError(e));
    } finally {
      setBusy(false);
    }
  }

  if (loading) {
    return (
      <div className="flex justify-center py-20">
        <Spinner className="h-8 w-8 text-brand" />
      </div>
    );
  }
  if (error) {
    return <ErrorNote>{error}</ErrorNote>;
  }
  if (!m) {
    return <EmptyState title="Materi tidak ditemukan" description="Materi mungkin sudah dipindahkan atau kamu tidak punya akses." />;
  }

  return (
    <>
      <Link href="/dashboard/siswa/materi" className="mb-4 inline-flex items-center gap-1.5 text-sm font-extrabold text-brand hover:underline">
        ← Kembali ke daftar materi
      </Link>

      <PageHeader
        title={m.title}
        description={m.description ?? undefined}
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone="brand">
              {TYPE_EMOJI[m.type] ?? '📚'} {TYPE_LABEL[m.type] ?? m.type}
            </Badge>
            {m.xp_reward > 0 && <Badge tone="warning">+{m.xp_reward} XP</Badge>}
          </div>
        }
      />

      {(err || notice) && (
        <div className="mb-4 space-y-2">
          {err && <ErrorNote>{err}</ErrorNote>}
          {notice && (
            <div className="rounded-[14px] border-2 border-mint/30 bg-mint-soft px-4 py-3 text-sm font-bold text-mint-dark">
              {notice}
            </div>
          )}
        </div>
      )}

      <div className="grid gap-5 lg:grid-cols-[1fr_280px]">
        <Card className="lg:order-1">
          <div className="mb-4 flex flex-wrap items-center gap-2 border-b border-border pb-3">
            <span className="grid h-9 w-9 place-items-center rounded-[12px] bg-brand-soft text-base">
              {TYPE_EMOJI[m.type] ?? '📚'}
            </span>
            <span className="text-sm font-extrabold text-muted">{m.module.title}</span>
            {m.module.curriculum && <Badge tone="neutral">{m.module.curriculum}</Badge>}
          </div>

          {m.body ? (
            <BodyView body={m.body} />
          ) : (
            <div className="space-y-4">
              <p className="text-[15px] font-semibold leading-relaxed text-muted">
                Materi ini belum memiliki isi bacaan. Silakan buka tautan lampiran bila tersedia.
              </p>
              {(m.external_url || m.file_url) && (
                <a href={m.external_url ?? m.file_url ?? '#'} target="_blank" rel="noreferrer">
                  <Button variant="outline" size="sm">
                    Buka Lampiran
                  </Button>
                </a>
              )}
            </div>
          )}
        </Card>

        <div className="space-y-4 lg:order-2">
          <Card>
            <p className="text-xs font-extrabold uppercase tracking-wide text-muted">Progres Modul</p>
            <p className="mt-1 font-display text-2xl font-extrabold">
              {m.module_progress.completed}
              <span className="text-base text-muted"> / {m.module_progress.total}</span>
            </p>
            <div className="mt-2 h-2 overflow-hidden rounded-full bg-brand-soft">
              <div className="h-full rounded-full bg-brand transition-all" style={{ width: `${m.module_progress.progress_pct}%` }} />
            </div>
            <p className="mt-1 text-xs font-bold text-muted">{m.module_progress.progress_pct}% selesai</p>

            <div className="mt-4">
              {m.is_completed ? (
                <Badge tone="success" className="w-full justify-center py-2">
                  <Ic.check className="h-4 w-4" /> Sudah Selesai
                </Badge>
              ) : (
                <Button variant="success" className="w-full" loading={busy} onClick={complete}>
                  Tandai Selesai
                </Button>
              )}
            </div>
          </Card>

          <Card className="bg-brand-soft/60">
            <p className="text-sm font-extrabold text-brand-dark">{m.module.title}</p>
            {m.module.description && <p className="mt-1 text-xs font-semibold text-muted">{m.module.description}</p>}
          </Card>
        </div>
      </div>
    </>
  );
}
