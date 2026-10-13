/* eslint-disable @next/next/no-img-element */
import Link from 'next/link';
import { Badge, Button, Card } from '@/components/ui';
import { Ic } from '@/components/icons';
import { Mascot } from '@/components/mascot';

/* ─── Data dummy (halaman publik, tanpa backend) ─── */

const STATS = [
  { value: '12.000+', label: 'Siswa aktif' },
  { value: '180+', label: 'Mentor berpengalaman' },
  { value: '4.9/5', label: 'Rating kepuasan' },
  { value: '320+', label: 'Kelas & webinar' },
];

const HIGHLIGHTS = [
  { icon: Ic.bolt, title: 'Gamifikasi XP', desc: 'Setiap materi & tugas memberi poin XP.', tone: 'bg-sun-soft text-sun-dark' },
  { icon: Ic.trophy, title: 'Leaderboard', desc: 'Bersaing sehat antar siswa.', tone: 'bg-brand-soft text-brand-dark' },
  { icon: Ic.award, title: 'Sertifikat', desc: 'Bukti kelulusan siap dibagikan.', tone: 'bg-mint-soft text-mint-dark' },
  { icon: Ic.calendar, title: 'Webinar Live', desc: 'Belajar langsung dari para mentor.', tone: 'bg-sky-soft text-sky-dark' },
];

type Paket = {
  name: string;
  price: string;
  period: string;
  tagline: string;
  features: string[];
  cta: string;
  variant: 'primary' | 'outline';
  popular?: boolean;
  accent: string;
};

const PAKET: Paket[] = [
  {
    name: 'Gratis',
    price: 'Rp0',
    period: 'selamanya',
    tagline: 'Coba dulu, kenalan dengan cara belajar di LE.',
    features: [
      'Akses 3 kursus dasar',
      'Kuis & latihan terbatas',
      'Leaderboard & poin XP',
      'Forum diskusi komunitas',
    ],
    cta: 'Mulai Gratis',
    variant: 'outline',
    accent: 'text-mint-dark',
  },
  {
    name: 'Reguler',
    price: 'Rp149.000',
    period: '/ bulan',
    tagline: 'Untuk yang ingin belajar terarah dan konsisten.',
    features: [
      'Semua kursus dasar & menengah',
      'Bimbingan grup tiap minggu',
      'Sertifikat tiap kursus selesai',
      'Gamifikasi XP penuh',
    ],
    cta: 'Pilih Reguler',
    variant: 'outline',
    accent: 'text-brand-dark',
  },
  {
    name: 'Pro',
    price: 'Rp299.000',
    period: '/ bulan',
    tagline: 'Paling laris — pendampingan intensif per siswa.',
    features: [
      'Semua benefit paket Reguler',
      'Bimbingan privat 1-on-1',
      'Prioritas dijawab mentor',
      'Tryout & analisis progres',
      'Sertifikat premium',
    ],
    cta: 'Pilih Pro',
    variant: 'primary',
    popular: true,
    accent: 'text-brand-dark',
  },
  {
    name: 'Intensif',
    price: 'Rp599.000',
    period: '/ 3 bulan',
    tagline: 'Persiapan serius dengan kelas live intensif.',
    features: [
      'Semua benefit paket Pro',
      'Kelas live intensif tiap pekan',
      'Pendampingan karier & jurusan',
      'Akses semua webinar premium',
    ],
    cta: 'Pilih Intensif',
    variant: 'outline',
    accent: 'text-coral-dark',
  },
];

type Webinar = {
  title: string;
  mentor: string;
  role: string;
  desc: string;
  date: string;
  img: string;
  accent: string;
};

const WEBINARS: Webinar[] = [
  {
    title: 'Strategi Belajar Efektif di Era Digital',
    mentor: 'Kak Rani Pratiwi',
    role: 'Mentor Manajemen Waktu',
    desc: 'Atur waktu, atasi distraksi, dan bangun kebiasaan belajar yang tahan lama.',
    date: 'Sabtu, 18 Okt · 19.00 WIB',
    img: 'https://randomuser.me/api/portraits/women/44.jpg',
    accent: 'bg-brand-soft text-brand-dark',
  },
  {
    title: 'Ngobrol Santai: Karier di Bidang Data',
    mentor: 'Kak Dito Nugraha',
    role: 'Mentor Data & Analitik',
    desc: 'Kenali peluang karier data sejak dini dan langkah pertama untuk memulainya.',
    date: 'Rabu, 22 Okt · 16.00 WIB',
    img: 'https://randomuser.me/api/portraits/men/32.jpg',
    accent: 'bg-sky-soft text-sky-dark',
  },
  {
    title: 'Persiapan UTBK: Tips & Trik Jitu',
    mentor: 'Kak Sari Melati',
    role: 'Mentor UTBK & Penalaran',
    desc: 'Pola soal, trik cepat, dan cara latihan yang bikin skor naik.',
    date: 'Sabtu, 25 Okt · 09.00 WIB',
    img: 'https://randomuser.me/api/portraits/women/68.jpg',
    accent: 'bg-sun-soft text-sun-dark',
  },
  {
    title: 'Dasar Public Speaking untuk Pelajar',
    mentor: 'Kak Bima Saputra',
    role: 'Mentor Komunikasi',
    desc: 'Berani tampil, bicara runtut, dan memukau di depan kelas maupun panggung.',
    date: 'Selasa, 28 Okt · 15.30 WIB',
    img: 'https://randomuser.me/api/portraits/men/75.jpg',
    accent: 'bg-mint-soft text-mint-dark',
  },
  {
    title: 'Mengenal AI & Coding untuk Pemula',
    mentor: 'Kak Adelia Putri',
    role: 'Mentor Teknologi',
    desc: 'Ngoding dari nol dengan cara menyenangkan, plus serba-serbi dunia AI.',
    date: 'Jumat, 31 Okt · 19.30 WIB',
    img: 'https://randomuser.me/api/portraits/women/65.jpg',
    accent: 'bg-coral-soft text-coral-dark',
  },
  {
    title: 'Kesehatan Mental & Motivasi Belajar',
    mentor: 'Kak Nadia Kusuma',
    role: 'Mentor Psikologi Belajar',
    desc: 'Kelola stres ujian dan jaga semangat belajar tetap menyala.',
    date: 'Sabtu, 1 Nov · 10.00 WIB',
    img: 'https://randomuser.me/api/portraits/women/79.jpg',
    accent: 'bg-brand-soft text-brand-dark',
  },
];

export default function HomePage() {
  return (
    <div className="min-h-screen">
      {/* Navbar */}
      <header className="sticky top-0 z-20 border-b border-border bg-card/80 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
          <div className="flex items-center gap-2.5">
            <div className="grid h-10 w-10 place-items-center rounded-[14px] bg-brand font-display text-lg font-extrabold text-brand-fg shadow-[0_3px_0_var(--brand-dark)]">
              LE
            </div>
            <span className="font-display text-lg font-extrabold">LE Academy</span>
          </div>
          <nav className="hidden items-center gap-6 text-sm font-extrabold text-muted md:flex">
            <Link href="#paket" className="transition hover:text-brand-dark">Paket</Link>
            <Link href="#webinar" className="transition hover:text-brand-dark">Webinar</Link>
            <Link href="#kenapa" className="transition hover:text-brand-dark">Kenapa LE</Link>
          </nav>
          <div className="flex items-center gap-2">
            <Link href="/login">
              <Button variant="outline" size="sm">Masuk</Button>
            </Link>
            <Link href="/register">
              <Button size="sm">Daftar</Button>
            </Link>
          </div>
        </div>
      </header>

      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-brand-soft" />
        <div className="pointer-events-none absolute -right-20 top-10 h-56 w-56 rounded-[48px] bg-sun-soft" />
        <div className="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 lg:grid-cols-2 lg:py-24">
          <div className="text-center lg:text-left">
            <Badge tone="brand" className="mb-4">
              <Ic.sparkle className="h-4 w-4" /> LMS Multi-Segmen
            </Badge>
            <h1 className="font-display text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl">
              Belajar tanpa batas, <span className="text-brand">dengan cara yang seru</span>
            </h1>
            <p className="mx-auto mt-4 max-w-xl text-base font-semibold text-muted lg:mx-0">
              Kurikulum terstruktur, gamifikasi XP, leaderboard, sertifikat, dan webinar live
              bersama mentor — semuanya dalam satu platform. Mulai gratis, tanpa kartu kredit.
            </p>
            <div className="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
              <Link href="/register">
                <Button size="lg">
                  Mulai Belajar Gratis <Ic.rocket className="h-5 w-5" />
                </Button>
              </Link>
              <Link href="#paket">
                <Button size="lg" variant="outline">Lihat Paket</Button>
              </Link>
            </div>
            <p className="mt-4 text-sm font-semibold text-muted">
              <Ic.check className="mr-1 inline h-4 w-4 text-mint" />
              Gratis untuk mulai · Tanpa kartu kredit
            </p>
          </div>

          <div className="relative flex justify-center">
            <div className="relative grid h-64 w-64 place-items-center rounded-[48px] bg-brand-soft sm:h-80 sm:w-80">
              <div className="pointer-events-none absolute -left-4 top-6 h-14 w-14 rounded-2xl bg-sun/70" />
              <div className="pointer-events-none absolute -right-2 bottom-8 h-10 w-10 rounded-full bg-mint" />
              <div className="pointer-events-none absolute right-6 top-2 h-8 w-8 rounded-xl bg-coral" />
              <Mascot className="h-44 w-44 sm:h-56 sm:w-56" />
            </div>
          </div>
        </div>

        {/* Social proof */}
        <div className="relative mx-auto max-w-6xl px-4 pb-6">
          <div className="grid grid-cols-2 gap-4 rounded-[22px] border border-border bg-card p-6 shadow-[0_10px_30px_-18px_rgba(35,37,77,0.35)] sm:grid-cols-4">
            {STATS.map((s) => (
              <div key={s.label} className="text-center">
                <p className="font-display text-2xl font-extrabold text-brand sm:text-3xl">{s.value}</p>
                <p className="mt-0.5 text-xs font-extrabold uppercase tracking-wide text-muted">{s.label}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Kenapa LE (highlights) */}
      <section id="kenapa" className="mx-auto max-w-6xl px-4 py-12">
        <div className="mb-6 text-center">
          <h2 className="font-display text-2xl font-extrabold sm:text-3xl">Kenapa belajar di LE Academy?</h2>
          <p className="mt-1 text-sm font-semibold text-muted">Belajar jadi kebiasaan yang bikin ketagihan.</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {HIGHLIGHTS.map((h) => (
            <Card key={h.title} className="flex flex-col gap-3">
              <span className={`grid h-12 w-12 place-items-center rounded-2xl ${h.tone}`}>
                <h.icon className="h-6 w-6" />
              </span>
              <div>
                <h3 className="font-display text-base font-extrabold">{h.title}</h3>
                <p className="mt-0.5 text-sm font-semibold text-muted">{h.desc}</p>
              </div>
            </Card>
          ))}
        </div>
      </section>

      {/* Paket */}
      <section id="paket" className="mx-auto max-w-6xl px-4 py-12">
        <div className="mb-8 text-center">
          <Badge tone="brand" className="mb-3"><Ic.box className="h-4 w-4" /> Paket Belajar</Badge>
          <h2 className="font-display text-2xl font-extrabold sm:text-3xl">Pilih paket yang pas untukmu</h2>
          <p className="mx-auto mt-2 max-w-2xl text-sm font-semibold text-muted">
            Dari gratis sampai intensif — bisa upgrade kapan saja seiring progres belajarmu.
          </p>
        </div>

        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {PAKET.map((p) => (
            <Card
              key={p.name}
              className={`relative flex flex-col ${
                p.popular
                  ? 'border-2 border-brand shadow-[0_20px_45px_-22px_rgba(109,93,246,0.65)]'
                  : 'transition hover:-translate-y-1'
              }`}
            >
              {p.popular && (
                <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand px-3 py-1 text-xs font-extrabold text-brand-fg shadow-[0_3px_0_var(--brand-dark)]">
                  ⭐ Paling Populer
                </span>
              )}
              <h3 className="font-display text-lg font-extrabold">{p.name}</h3>
              <p className="mt-1 text-xs font-semibold text-muted">{p.tagline}</p>
              <p className={`mt-4 font-display text-3xl font-extrabold ${p.accent}`}>
                {p.price}
                <span className="ml-1 text-sm font-bold text-muted">{p.period}</span>
              </p>
              <ul className="mt-5 flex-1 space-y-2.5 text-sm font-semibold">
                {p.features.map((f) => (
                  <li key={f} className="flex items-start gap-2">
                    <Ic.check className="mt-0.5 h-4 w-4 shrink-0 text-mint" />
                    <span>{f}</span>
                  </li>
                ))}
              </ul>
              <Link href="/register" className="mt-6">
                <Button className="w-full" variant={p.variant}>{p.cta}</Button>
              </Link>
            </Card>
          ))}
        </div>
        <p className="mt-4 text-center text-xs font-semibold text-muted">
          Harga contoh untuk pratinjau. Semua paket bisa dibatalkan kapan saja.
        </p>
      </section>

      {/* Webinar */}
      <section id="webinar" className="mx-auto max-w-6xl px-4 py-12">
        <div className="mb-8 text-center">
          <Badge tone="info" className="mb-3"><Ic.calendar className="h-4 w-4" /> Webinar</Badge>
          <h2 className="font-display text-2xl font-extrabold sm:text-3xl">Webinar bersama para mentor</h2>
          <p className="mx-auto mt-2 max-w-2xl text-sm font-semibold text-muted">
            Sesi daring gratis setiap pekan. Daftar akun untuk ikut dan bertanya langsung.
          </p>
        </div>

        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {WEBINARS.map((w) => (
            <Card key={w.title} className="flex flex-col p-0 transition hover:-translate-y-1 hover:shadow-[0_18px_40px_-20px_rgba(35,37,77,0.4)]">
              <div className="relative">
                <img
                  src={w.img}
                  alt={`Foto ${w.mentor}`}
                  loading="lazy"
                  className="h-44 w-full object-cover"
                />
                <span className="absolute left-3 top-3 rounded-full bg-card/90 px-3 py-1 text-xs font-extrabold text-coral-dark backdrop-blur">
                  ● Live
                </span>
              </div>

              <div className="flex flex-1 flex-col p-5">
                <div className="flex items-center gap-2">
                  <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-[12px] ${w.accent}`}>
                    <Ic.grad className="h-5 w-5" />
                  </span>
                  <div className="min-w-0">
                    <p className="truncate text-sm font-extrabold leading-tight">{w.mentor}</p>
                    <p className="truncate text-xs font-semibold text-muted">{w.role}</p>
                  </div>
                </div>

                <h3 className="mt-3 font-display text-base font-extrabold leading-snug">{w.title}</h3>
                <p className="mt-1 line-clamp-2 flex-1 text-sm font-semibold text-muted">{w.desc}</p>

                <div className="mt-4 flex items-center gap-2 text-xs font-bold text-muted">
                  <Ic.calendar className="h-4 w-4" />
                  {w.date}
                </div>
                <Link href="/register" className="mt-4">
                  <Button className="w-full" variant="outline" size="sm">Daftar Gratis</Button>
                </Link>
              </div>
            </Card>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="mx-auto max-w-6xl px-4 py-12">
        <div className="relative overflow-hidden rounded-[28px] bg-brand px-6 py-12 text-center text-white sm:px-12">
          <div className="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-white/10" />
          <div className="pointer-events-none absolute -bottom-14 -right-8 h-52 w-52 rounded-full bg-white/10" />
          <Mascot className="mx-auto h-24 w-24" />
          <h2 className="mt-4 font-display text-2xl font-extrabold sm:text-3xl">Siap mulai belajar dengan seru?</h2>
          <p className="mx-auto mt-2 max-w-xl text-sm font-semibold text-white/90">
            Buat akun gratis dalam 1 menit dan raih sertifikat pertamamu minggu ini.
          </p>
          <div className="mt-6 flex flex-wrap justify-center gap-3">
            <Link href="/register">
              <Button size="lg" variant="sun">
                Daftar Gratis Sekarang <Ic.rocket className="h-5 w-5" />
              </Button>
            </Link>
            <Link href="/login">
              <Button size="lg" variant="outline">Sudah punya akun</Button>
            </Link>
          </div>
        </div>
      </section>

      <footer className="border-t border-border py-8 text-center text-sm font-semibold text-muted">
        © {new Date().getFullYear()} LE Academy — LMS Multi-Segmen
      </footer>
    </div>
  );
}
