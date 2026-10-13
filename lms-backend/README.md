# LMS Maxy Academy — Backend API

Backend REST API untuk **Learning Management System (LMS) Multi Segmen Maxy Academy**.
Satu platform yang melayani beberapa segmen pengguna sekaligus (siswa akademik, karyawan
pelatihan korporat, mentor, lembaga bimbingan B2B, dan administrator) melalui satu sistem
terintegrasi berbasis *Role Based Access Control* (RBAC).

Dibangun dengan **Laravel 11** (PHP 8.2+), **Laravel Sanctum** untuk autentikasi token,
**spatie/laravel-permission** untuk RBAC, dan **PostgreSQL** sebagai basis data.

---

## ✨ Fitur Utama

### 1. Autentikasi & Otorisasi
- Register, login, logout, dan profil (`/auth/me`) menggunakan token Bearer Sanctum.
- Setiap user baru otomatis mendapat role `siswa`.
- RBAC dengan 5 peran: `admin`, `mentor`, `siswa`, `karyawan`, `lembaga`.
- **Multi-tenant**: konten & data milik lembaga (`institution_id`); mentor/admin hanya
  mengakses datanya sendiri, `admin` tanpa lembaga = platform admin (akses semua).
- **Rate limiting** pada login/register (anti brute-force) & token kedaluwarsa 7 hari.
- Semua endpoint `/api/*` selalu merespons **JSON** (validasi → 422, unauthenticated → 401).

### 2. Konten Pembelajaran
- **Module** — unit materi dengan `xp_reward` (XP yang diberikan saat modul diselesaikan).
- **Assessment** — tugas/ujian yang terikat ke module, memiliki `max_score`, `xp_reward`,
  dan `due_at` (tenggat).

### 3. Pengumpulan & Penilaian Tugas
- Siswa mengumpulkan jawaban (teks dan/atau file upload, maks 10 MB).
- Satu siswa = satu submission per assessment (submit pertama `201`, update `200`).
- Jawaban dikunci setelah dinilai.
- Mentor/admin dapat melihat daftar submission dan memberi nilai + feedback.
- Penilaian otomatis memicu pemberian XP (event `TaskGraded`).

### 4. Gamifikasi
- **XP Log** — catatan perolehan XP dengan sumber (`task_completion`, `material_completion`)
  dan `reference_id` unik (anti duplikasi XP untuk sumber yang sama).
- **Level** — tingkatan berdasarkan `min_xp`.
- **User Level** — total XP user; naik level otomatis saat melewati ambang batas.
- Dikelola oleh `GamificationService` di dalam transaksi + row lock agar aman dari race condition.

### 5. Commerce (Paket Belajar, Voucher, Order)
- **Package** — paket belajar berisi kumpulan module (pivot `package_module`), punya harga & status aktif.
- **Voucher** — diskon tipe `percent` atau `fixed`, dengan batas pemakaian (`max_uses`) dan masa berlaku.
- **Order** — harga asli, nominal diskon, harga akhir; status `pending` → `paid` → `cancelled`.
- Alur checkout: **preview** harga+diskon → **buat order** → **bayar** (simulasi) → **enrollment** otomatis.
- Pembayaran pada tahap ini adalah **simulasi** (Midtrans disiapkan sebagai integrasi masa depan).
- `GET /orders` untuk riwayat order user.

### 6. Lembaga Bimbingan (B2B)
- **Import siswa massal** ke sebuah paket (email belum terdaftar akan dibuat akun otomatis
  dengan password sementara). Khusus `admin`/`mentor`.
- **Undangan (invite)** berbasis token dengan masa berlaku. Link undangan bisa dibuka tanpa login;
  penerimaan undangan membuat/menautkan akun ke lembaga dan otomatis enroll ke paket (jika ada).

### 7. Sertifikat
- Siswa mengajukan sertifikat (**syarat: progres enrollment 100%**).
- Admin/mentor menyetujui (generate kode unik `CERT-XXXXXXXX-YYYY`) atau menolak dengan catatan.
- Lihat daftar sertifikat (admin/mentor), sertifikat milik sendiri, dan **verifikasi publik** via kode.

### 8. Event & Listener
| Event | Listener | Efek |
|-------|----------|------|
| `TaskGraded` | `AwardTaskXp` | Beri XP jika nilai ≥ threshold (default 70% dari max_score) |
| `MaterialCompleted` | `AwardMaterialXp` | Beri XP sebesar `xp_reward` modul |
| `OrderPaid` | `EnrollUserOnPayment` | Buat enrollment otomatis setelah pembayaran |

---

## 👥 Peran Pengguna

| Role | Deskripsi singkat |
|------|-------------------|
| `admin` | Mengelola pengguna, konten, gamifikasi, voucher, sertifikat |
| `mentor` | Mengelola modul/tugas, menilai submission, approve sertifikat, import siswa |
| `siswa` | Belajar, mengumpulkan tugas, kumpulkan XP, ajukan sertifikat |
| `karyawan` | Mengikuti pelatihan korporat (segmen pelatihan) |
| `lembaga` | Mitra B2B: mendaftarkan siswa, mengelola paket/voucher |

---

## 🧱 Tumpukan Teknologi

- **PHP** ^8.2 · **Laravel** ^11
- **Laravel Sanctum** (auth token) · **spatie/laravel-permission** (RBAC)
- **PostgreSQL** (default) — dapat diganti sesuai konfigurasi `.env`
- **Laravel Tinker** (REPL), **PHPUnit** (testing), **Laravel Pint** (code style)

---

## 🔌 Daftar Endpoint API

Base URL: `/api/v1`

### Publik (tanpa token)
| Method | Endpoint | Keterangan |
|--------|----------|------------|
| POST | `/auth/register` | Registrasi user baru |
| POST | `/auth/login` | Login → dapat token Bearer |
| GET | `/packages` | Daftar paket aktif |
| GET | `/packages/{package}` | Detail paket |
| GET | `/institution/invites/{token}` | Lihat detail undangan lembaga |
| POST | `/institution/invites/{token}/accept` | Terima undangan (buat/aktifkan akun) |
| GET | `/certificates/verify/{code}` | Verifikasi sertifikat (publik) |

### Butuh Token (`Authorization: Bearer <token>`)
| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| GET | `/auth/me` | Semua | Data user yang login |
| POST | `/auth/logout` | Semua | Hapus token aktif |
| POST | `/assessments/{assessment}/submit` | Siswa | Kumpulkan tugas (201 baru / 200 update) |
| GET | `/assessments/{assessment}/submissions` | mentor, admin | Daftar submission |
| PATCH | `/submissions/{submission}/grade` | mentor, admin | Nilai submission |
| POST | `/institution/students/import` | mentor, admin | Import siswa massal ke paket |
| POST | `/institution/students/invite` | mentor, admin | Buat undangan lembaga |
| GET | `/checkout/preview` | Semua | Preview harga + diskon voucher |
| POST | `/checkout` | Semua | Buat order (status pending) |
| POST | `/orders/{order}/pay` | Pemilik order | Simulasi pembayaran → enrollment |
| GET | `/orders` | Semua | Riwayat order milik user |
| POST | `/certificates/request` | Siswa | Ajukan sertifikat (progres 100%) |
| GET | `/certificates/mine` | Semua | Sertifikat milik sendiri |
| GET | `/certificates` | mentor, admin | Daftar semua sertifikat (filter `status`) |
| PATCH | `/certificates/{certificate}/approve` | mentor, admin | Setujui sertifikat |
| PATCH | `/certificates/{certificate}/reject` | mentor, admin | Tolak sertifikat |

---

## 🚀 Instalasi & Menjalankan

```bash
# 1. Install dependency
composer install

# 2. Siapkan environment
copy .env.example .env      # Windows
php artisan key:generate

# 3. Konfigurasi database (PostgreSQL) di .env
#    DB_CONNECTION=pgsql
#    DB_HOST=127.0.0.1
#    DB_PORT=5432
#    DB_DATABASE=lms_learn
#    DB_USERNAME=...
#    DB_PASSWORD=...

# 4. Migrasi + seed (role & level)
php artisan migrate --seed

# 5. Jalankan server
php artisan serve
```

Server berjalan di `http://127.0.0.1:8000`.

### Seed Data
- `RoleSeeder` — 5 role dasar (dijalankan oleh `DatabaseSeeder`).
- `LevelSeeder` — level gamifikasi (dijalankan manual jika perlu):
  ```bash
  php artisan db:seed --class=LevelSeeder
  ```

---

## 🧪 Testing

```bash
php artisan test        # menjalankan test suite (PHPUnit)
./vendor/bin/pint       # perbaiki format kode
```

Skenario pengujian manual (register/login, submit tugas, penilaian, pemberian XP,
event `MaterialCompleted`, checkout → enrollment, sertifikat) dapat dicoba langsung
menggunakan Postman atau `curl`/PowerShell terhadap endpoint di atas.

---

## 📁 Struktur Singkat

```
app/
├─ Events/            TaskGraded, MaterialCompleted, OrderPaid
├─ Listeners/         AwardTaskXp, AwardMaterialXp, EnrollUserOnPayment
├─ Http/Controllers/  Api/V1: Auth, AssessmentSubmission, Package,
│                     Checkout, Institution, Certificate
├─ Models/            User, Module, Assessment, Submission, Package,
│                     Order, Voucher, Enrollment, Certificate,
│                     Institution, InstitutionInvite, Level, UserLevel, XpLog
└─ Services/          GamificationService (XP & level)
```

---

## 🗺️ Roadmap

Sesuai PRD, beberapa modul berikut direncanakan pada pengembangan selanjutnya:
integrasi **Midtrans** (payment gateway nyata), **Zoom API** (webinar), notifikasi **SMTP/email**,
kurikulum & konten materi (video/dokumen), forum diskusi, ulasan, jadwal bimbingan/webinar,
leaderboard, serta dasbor analitik & laporan.

---

## 📄 Lisensi

Proyek pengembangan LMS Maxy Academy — Kelompok 10, Universitas 'Aisyiyah Yogyakarta.
