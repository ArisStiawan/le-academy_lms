'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMemo, useState } from 'react';
import { Badge, Button, Card, EmptyState, ErrorNote, PageHeader, Spinner } from '@/components/ui';
import { Ic } from '@/components/icons';
import { apiError, objOf, tanggal, useApi } from '@/lib/use-api';
import api from '@/lib/api';

interface Question {
  id: number;
  question: string;
  options: string[];
}

interface AssessmentDetail {
  id: number;
  type: 'tugas' | 'ujian' | string;
  title: string;
  module: { id: number; title: string; curriculum: string | null };
  max_score: number;
  passing_score: number | null;
  xp_reward: number;
  due_at: string | null;
  is_submitted: boolean;
  is_graded: boolean;
  score: number | null;
  feedback: string | null;
  submitted_at: string | null;
  questions: Question[];
}

interface SubmitResult {
  score: number;
  correct_count: number | null;
  total_questions: number | null;
  feedback: string | null;
}

const LETTERS = 'ABCDEFGH';

export default function SiswaTugasDetailPage() {
  const params = useParams<{ id: string }>();
  const { data, loading, error, reload } = useApi<unknown>(`/my/assessments/${params.id}`);

  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [answerText, setAnswerText] = useState('');
  const [saving, setSaving] = useState(false);
  const [result, setResult] = useState<SubmitResult | null>(null);
  const [err, setErr] = useState<string | null>(null);

  const a = objOf<AssessmentDetail>(data);
  const questions: Question[] = useMemo(() => a?.questions ?? [], [a]);
  const hasQuiz = questions.length > 0;
  const answeredCount = Object.keys(answers).length;

  async function submit() {
    if (!a) return;
    setErr(null);
    setSaving(true);
    try {
      const payload: Record<string, unknown> = hasQuiz ? { answers } : { answer_text: answerText };
      const res = await api.post(`/assessments/${a.id}/submit`, payload);
      const d = (res.data?.data ?? {}) as Partial<SubmitResult> & { score?: number };
      setResult({
        score: d.score ?? 0,
        correct_count: d.correct_count ?? null,
        total_questions: d.total_questions ?? null,
        feedback: d.feedback ?? null,
      });
      await reload();
    } catch (e) {
      setErr(apiError(e));
    } finally {
      setSaving(false);
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
  if (!a) {
    return <EmptyState title="Tugas tidak ditemukan" description="Tugas mungkin sudah dipindahkan atau kamu tidak punya akses." />;
  }

  const passed = a.is_graded && a.passing_score != null && (a.score ?? 0) >= a.passing_score;
  const locked = a.is_graded && !hasQuiz; // tugas manual yang sudah dinilai tidak dapat diubah

  return (
    <>
      <Link href="/dashboard/siswa/tugas-ujian" className="mb-4 inline-flex items-center gap-1.5 text-sm font-extrabold text-brand hover:underline">
        ← Kembali ke daftar tugas
      </Link>

      <PageHeader
        title={a.title}
        description={`${a.module.title}${a.module.curriculum ? ` · ${a.module.curriculum}` : ''}`}
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone={a.type === 'ujian' ? 'danger' : 'brand'}>{a.type === 'ujian' ? 'Ujian' : 'Tugas'}</Badge>
            {a.xp_reward > 0 && <Badge tone="warning">+{a.xp_reward} XP</Badge>}
          </div>
        }
      />

      {err && (
        <div className="mb-4">
          <ErrorNote>{err}</ErrorNote>
        </div>
      )}

      {(a.is_graded || result) && (
        <Card className={`mb-5 border-2 ${passed ? 'border-mint/40 bg-mint-soft' : 'border-brand/30 bg-brand-soft/60'}`}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs font-extrabold uppercase tracking-wide text-muted">Nilai Kamu</p>
              <p className="font-display text-3xl font-extrabold">
                {a.score ?? result?.score ?? 0}
                <span className="text-base text-muted"> / {a.max_score}</span>
              </p>
              {a.passing_score != null && (
                <p className="mt-0.5 text-sm font-bold text-muted">
                  KKM {a.passing_score} · {passed ? 'Lulus 🎉' : 'Belum lulus'}
                </p>
              )}
            </div>
            {result?.correct_count != null && result.total_questions != null && (
              <div className="text-right">
                <p className="text-xs font-extrabold uppercase tracking-wide text-muted">Jawaban Benar</p>
                <p className="font-display text-2xl font-extrabold text-mint-dark">
                  {result.correct_count}
                  <span className="text-base text-muted"> / {result.total_questions}</span>
                </p>
              </div>
            )}
          </div>
          {(result?.feedback ?? a.feedback) && (
            <p className="mt-3 rounded-[14px] bg-card px-4 py-3 text-sm font-semibold text-foreground">
              💬 {result?.feedback ?? a.feedback}
            </p>
          )}
          {a.submitted_at && <p className="mt-2 text-xs font-bold text-muted">Dikumpulkan {tanggal(a.submitted_at)}</p>}
          {hasQuiz && (
            <p className="mt-3 text-xs font-bold text-muted">
              Kuis dapat dikerjakan ulang untuk memperbaiki nilai. Tekan “Kumpulkan Jawaban” lagi di bawah.
            </p>
          )}
        </Card>
      )}

      {!result && !a.is_graded && a.due_at && (
        <p className="mb-4 text-sm font-bold text-muted">Deadline: {tanggal(a.due_at)}</p>
      )}

      {locked ? (
        <EmptyState title="Tugas sudah dinilai" description="Tugas ini tidak dapat diubah lagi setelah dinilai mentor." />
      ) : (
        <div className="space-y-4">
          {hasQuiz ? (
            <>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-extrabold text-muted">
                  {questions.length} soal pilihan ganda · {answeredCount} terjawab
                </p>
                <Badge tone="info">Nilai maks {a.max_score}</Badge>
              </div>

              {questions.map((q, qi) => (
                <Card key={q.id}>
                  <div className="flex items-start gap-3">
                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-[12px] bg-brand-soft text-sm font-extrabold text-brand-dark">
                      {qi + 1}
                    </span>
                    <p className="pt-1 text-[15px] font-extrabold leading-relaxed">{q.question}</p>
                  </div>

                  <div className="mt-3 space-y-2 pl-0 sm:pl-11">
                    {q.options.map((opt, oi) => {
                      const picked = answers[q.id] === oi;
                      return (
                        <button
                          key={oi}
                          type="button"
                          onClick={() => setAnswers((prev) => ({ ...prev, [q.id]: oi }))}
                          className={`flex w-full items-center gap-3 rounded-[14px] border-2 px-3 py-2.5 text-left text-sm font-semibold transition ${
                            picked
                              ? 'border-brand bg-brand-soft text-brand-dark shadow-[0_3px_0_var(--brand)]'
                              : 'border-border bg-card hover:border-brand/40 hover:bg-brand-soft/50'
                          }`}
                        >
                          <span
                            className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-extrabold ${
                              picked ? 'bg-brand text-white' : 'bg-[#eef0f9] text-muted'
                            }`}
                          >
                            {picked ? <Ic.check className="h-4 w-4" /> : LETTERS[oi]}
                          </span>
                          <span>{opt}</span>
                        </button>
                      );
                    })}
                  </div>
                </Card>
              ))}

              <div className="flex flex-wrap items-center gap-3">
                <Button loading={saving} disabled={answeredCount === 0} onClick={submit}>
                  Kumpulkan Jawaban
                </Button>
                <Button
                  variant="ghost"
                  onClick={() => {
                    setAnswers({});
                    setResult(null);
                    setErr(null);
                  }}
                >
                  Reset Jawaban
                </Button>
              </div>
            </>
          ) : (
            <Card>
              <label className="block">
                <span className="mb-1.5 block text-sm font-extrabold">Jawaban kamu</span>
                <textarea
                  rows={6}
                  value={answerText}
                  onChange={(e) => setAnswerText(e.target.value)}
                  placeholder="Tulis jawabanmu di sini…"
                  className="w-full rounded-[14px] border-2 border-border bg-background px-4 py-3 text-sm font-semibold text-foreground outline-none transition placeholder:text-muted/70 focus:border-brand focus:bg-card focus:ring-4 focus:ring-brand-soft"
                />
              </label>
              <div className="mt-3">
                <Button loading={saving} disabled={!answerText.trim()} onClick={submit}>
                  Kumpulkan Jawaban
                </Button>
              </div>
            </Card>
          )}
        </div>
      )}
    </>
  );
}
