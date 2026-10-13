'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { useAuth } from '@/lib/auth-context';
import { dashboardFor } from '@/lib/roles';
import { Button, Card, ErrorNote, Input } from '@/components/ui';
import { Ic } from '@/components/icons';
import { Mascot } from '@/components/mascot';

export default function LoginPage() {
  const router = useRouter();
  const { login } = useAuth();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await login(email, password);
      // Ambil roles terbaru dari response login tersimpan di localStorage
      const roles: string[] = JSON.parse(localStorage.getItem('lms_roles') ?? '[]');
      router.replace(dashboardFor(roles));
    } catch (err) {
      const e = err as { message?: string; errors?: Record<string, string[]> };
      const firstFieldError = e.errors ? Object.values(e.errors)[0]?.[0] : undefined;
      setError(firstFieldError ?? e.message ?? 'Login gagal.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      {/* Panel brand (kiri) */}
      <aside className="relative hidden overflow-hidden bg-brand p-10 text-white lg:flex lg:flex-col lg:justify-between">
        <div className="pointer-events-none absolute -left-16 -top-16 h-64 w-64 rounded-full bg-white/10" />
        <div className="pointer-events-none absolute -bottom-24 -right-12 h-72 w-72 rounded-full bg-white/10" />
        <div className="pointer-events-none absolute right-16 top-24 h-24 w-24 rounded-[28px] bg-sun/30" />

        <div className="relative flex items-center gap-2.5">
          <div className="grid h-11 w-11 place-items-center rounded-[15px] bg-white/20 font-display text-xl font-extrabold">
            M
          </div>
          <span className="font-display text-lg font-extrabold">Maxy Academy</span>
        </div>

        <div className="relative">
          <Mascot className="h-28 w-28 drop-shadow-lg" />
          <h2 className="mt-5 max-w-sm font-display text-3xl font-extrabold leading-tight">
            Belajar jadi seru — kumpulkan XP, jaga runtutan, raih sertifikat!
          </h2>
          <ul className="mt-6 space-y-2.5 text-sm font-semibold text-white/90">
            <li className="flex items-center gap-2.5">
              <Ic.flame className="h-5 w-5 text-sun" /> Runtutan harian &amp; poin XP
            </li>
            <li className="flex items-center gap-2.5">
              <Ic.trophy className="h-5 w-5 text-sun" /> Leaderboard antar siswa
            </li>
            <li className="flex items-center gap-2.5">
              <Ic.grad className="h-5 w-5 text-sun" /> Kurikulum &amp; jadwal bimbingan
            </li>
          </ul>
        </div>

        <p className="relative text-sm font-semibold text-white/70">
          © {new Date().getFullYear()} Maxy Academy
        </p>
      </aside>

      {/* Form (kanan) */}
      <div className="flex items-center justify-center bg-background p-6">
        <div className="w-full max-w-md">
          <div className="mb-6 text-center lg:text-left">
            <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-[16px] bg-brand font-display text-xl font-extrabold text-brand-fg shadow-[0_4px_0_var(--brand-dark)] lg:mx-0">
              M
            </div>
            <h1 className="font-display text-2xl font-extrabold">Masuk ke LMS Maxy Academy</h1>
            <p className="mt-1 text-sm font-semibold text-muted">Gunakan email dan password akun Anda.</p>
          </div>

          <Card>
            <form onSubmit={onSubmit} className="space-y-4">
              {error && <ErrorNote>{error}</ErrorNote>}

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
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
              />

              <Button type="submit" className="w-full" size="lg" loading={submitting}>
                Masuk
              </Button>
            </form>

            <p className="mt-4 text-center text-sm font-semibold text-muted">
              Belum punya akun?{' '}
              <Link href="/register" className="font-extrabold text-brand hover:underline">
                Daftar di sini
              </Link>
            </p>
          </Card>

          <p className="mt-6 text-center text-sm font-semibold">
            <Link href="/" className="text-muted hover:underline">
              ← Kembali ke beranda
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
