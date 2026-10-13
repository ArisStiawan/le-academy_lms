'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { Button, Card, ErrorNote, Input } from '@/components/ui';
import { Ic } from '@/components/icons';
import { Mascot } from '@/components/mascot';
import api from '@/lib/api';

export default function RegisterPage() {
  const router = useRouter();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);

    if (password !== confirm) {
      setError('Konfirmasi password tidak sama.');
      return;
    }

    setSubmitting(true);
    try {
      await api.post('/auth/register', {
        name,
        email,
        password,
        password_confirmation: confirm,
      });
      router.replace('/login?registered=1');
    } catch (err) {
      const e = err as { message?: string; errors?: Record<string, string[]> };
      const firstFieldError = e.errors ? Object.values(e.errors)[0]?.[0] : undefined;
      setError(firstFieldError ?? e.message ?? 'Registrasi gagal.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      {/* Panel brand (kiri) */}
      <aside className="relative hidden overflow-hidden bg-gradient-to-br from-brand to-brand-dark p-10 text-white lg:flex lg:flex-col lg:justify-between">
        <div className="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-white/10" />
        <div className="pointer-events-none absolute -bottom-20 -left-12 h-72 w-72 rounded-full bg-white/10" />
        <div className="pointer-events-none absolute bottom-32 right-20 h-20 w-20 rounded-[24px] bg-mint/40" />

        <div className="relative flex items-center gap-2.5">
          <div className="grid h-11 w-11 place-items-center rounded-[15px] bg-white/20 font-display text-xl font-extrabold">
            LE
          </div>
          <span className="font-display text-lg font-extrabold">LE Academy</span>
        </div>

        <div className="relative">
          <Mascot className="h-28 w-28 drop-shadow-lg" />
          <h2 className="mt-5 max-w-sm font-display text-3xl font-extrabold leading-tight">
            Mulai perjalanan belajarmu hari ini. 🚀
          </h2>
          <ul className="mt-6 space-y-2.5 text-sm font-semibold text-white/90">
            <li className="flex items-center gap-2.5">
              <Ic.check className="h-5 w-5 text-mint" /> Gratis untuk mulai sebagai Siswa
            </li>
            <li className="flex items-center gap-2.5">
              <Ic.check className="h-5 w-5 text-mint" /> Akses kurikulum terstruktur
            </li>
            <li className="flex items-center gap-2.5">
              <Ic.check className="h-5 w-5 text-mint" /> Sertifikat &amp; gamifikasi XP
            </li>
          </ul>
        </div>

        <p className="relative text-sm font-semibold text-white/70">
          © {new Date().getFullYear()} LE Academy
        </p>
      </aside>

      {/* Form (kanan) */}
      <div className="flex items-center justify-center bg-background p-6">
        <div className="w-full max-w-md">
          <div className="mb-6 text-center lg:text-left">
            <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-[16px] bg-brand font-display text-xl font-extrabold text-brand-fg shadow-[0_4px_0_var(--brand-dark)] lg:mx-0">
              LE
            </div>
            <h1 className="font-display text-2xl font-extrabold">Daftar Akun Siswa</h1>
            <p className="mt-1 text-sm font-semibold text-muted">
              Akun baru otomatis terdaftar sebagai <strong>Siswa</strong>.
            </p>
          </div>

          <Card>
            <form onSubmit={onSubmit} className="space-y-4">
              {error && <ErrorNote>{error}</ErrorNote>}

              <Input
                label="Nama Lengkap"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Nama Anda"
              />
              <Input
                label="Email"
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="nama@email.com"
              />
              <Input
                label="Password"
                type="password"
                autoComplete="new-password"
                required
                minLength={8}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Minimal 8 karakter"
              />
              <Input
                label="Konfirmasi Password"
                type="password"
                autoComplete="new-password"
                required
                value={confirm}
                onChange={(e) => setConfirm(e.target.value)}
                placeholder="Ulangi password"
              />

              <Button type="submit" className="w-full" size="lg" loading={submitting}>
                Daftar
              </Button>
            </form>

            <p className="mt-4 text-center text-sm font-semibold text-muted">
              Sudah punya akun?{' '}
              <Link href="/login" className="font-extrabold text-brand hover:underline">
                Masuk
              </Link>
            </p>
          </Card>
        </div>
      </div>
    </div>
  );
}
