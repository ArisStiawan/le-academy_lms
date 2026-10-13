# 🧪 Panduan Testing LMS Backend — Semua Fitur

Panduan & skrip pengujian untuk **seluruh fitur** API LMS Maxy Academy.
Mencakup autentikasi, RBAC, penugasan, gamifikasi, commerce, lembaga B2B, sertifikat,
serta **tenant scoping** (isolasi data antar-lembaga) dan **kewajiban enrollment**.

> **Hasil saat ini:** manual **82 PASS, 0 FAIL** · PHPUnit **71 passed** ✅

---

## 1. Prasyarat

- Server berjalan di `http://127.0.0.1:8000` (`php artisan serve`).
- Database PostgreSQL sudah dikonfigurasi di `.env`.
- Semua script berada di folder `tests/manual/`.
- Jalankan perintah **dari root project** `lms-backend`.

---

## 2. Setup Database (sekali)

```powershell
# Reset DB + seed role (admin, mentor, siswa, karyawan, lembaga)
php artisan migrate:fresh --seed --force

# Seed level gamifikasi (Pemula, Menengah, Mahir, Ahli)
php artisan db:seed --class=LevelSeeder --force

# Seed data uji lengkap (module, assessment, paket, voucher, institusi, user)
php artisan tinker tests/manual/setup_all.php
```

`setup_all.php` menghasilkan ID tetap: `assessment=1`, `package_active=1`, `package_inactive=2`.

### Akun uji yang dibuat (password: `password123`)

| Email | Role | Institusi |
|-------|------|-----------|
| `mentor@test.com` | mentor | Lembaga Uji |
| `mentor2@test.com` | mentor | Lembaga Lain |
| `admin@test.com` | admin (platform) | — |
| `siswa@test.com` | siswa | — |
| `siswa2@test.com` | siswa | — |
| `buyer@test.com` | siswa | — |
| `noenroll@test.com` | siswa (belum enroll) | — |
| `lembaga@test.com` | lembaga | Lembaga Uji |

---

## 3. Automated Testing (PHPUnit)

Test terintegrasi (tidak butuh server berjalan) yang mencakup **Auth, Assessment/Submission,
Certificate, Checkout, Institution, dan Tenant Scoping**:

```powershell
php artisan test
```

> Hasil saat ini: **71 passed (186 assertions)** ✅

Berkas test: `tests/Feature/{AuthTest, AssessmentSubmissionTest, CertificateTest, CheckoutTest, InstitutionTest, TenantScopingTest}.php`

---

## 4. Menjalankan Test Manual (End-to-End)

```powershell
powershell -ExecutionPolicy Bypass -File tests/manual/master_test.ps1
```

Script otomatis menjalankan semua skenario dan mencetak `PASS`/`FAIL` per pengujian,
diakhiri ringkasan `TOTAL: x PASS, y FAIL`.

> ⚠️ Test bersifat *stateful* (membuat order, enrollment, sertifikat). Untuk hasil bersih,
> jalankan ulang **langkah Setup Database** sebelum menjalankan test.

---

## 5. Matriks Pengujian

### 🔐 AUTH — `AuthController`
| # | Skenario | Endpoint | Expected |
|---|----------|----------|----------|
| 1 | Register user baru | `POST /auth/register` | 201, role default `siswa` |
| 2 | Register email duplikat | `POST /auth/register` | 422 |
| 3 | Register password tidak cocok | `POST /auth/register` | 422 |
| 4 | Login password salah | `POST /auth/login` | 422 |
| 5 | Ambil profil | `GET /auth/me` | 200 |
| 6 | Akses tanpa token | `GET /auth/me` | 401 |
| 7 | Logout | `POST /auth/logout` | 200 |
| 8 | Pakai token setelah logout | `GET /auth/me` | 401 |

### 🛡️ RBAC
| # | Skenario | Endpoint | Expected |
|---|----------|----------|----------|
| 9 | Daftar submission sebagai siswa | `GET /assessments/1/submissions` | 403 |
| 10 | Daftar submission sebagai mentor | `GET /assessments/1/submissions` | 200 |
| 11 | Import siswa sebagai siswa | `POST /institution/students/import` | 403 |
| 12 | Daftar semua sertifikat sebagai siswa | `GET /certificates` | 403 |

### 📝 ASSESSMENT & SUBMISSION — `AssessmentSubmissionController`
*(Siswa @1 sudah ter-enroll lewat paket #1 di seksi commerce sebelum submit.)*

| # | Skenario | Endpoint | Expected |
|---|----------|----------|----------|
| 13a | Submit ke modul berpaket tanpa enrollment | `POST /assessments/1/submit` | 403 |
| 13 | Submit pertama kali | `POST /assessments/1/submit` | 201 (+ `submitted_at`) |
| 14 | Submit kedua kali (update) | `POST /assessments/1/submit` | 200 |
| 15 | Submit kosong | `POST /assessments/1/submit` | 422 |
| 16 | Submit tanpa token | `POST /assessments/1/submit` | 401 |
| 17 | Nilai submission sebagai mentor | `PATCH /submissions/{id}/grade` | 200 |
| 18 | Nilai sebagai siswa | `PATCH /submissions/{id}/grade` | 403 |
| 19 | Nilai melebihi max_score (150) | `PATCH /submissions/{id}/grade` | 422 |
| 20 | Nilai negatif | `PATCH /submissions/{id}/grade` | 422 |
| 21 | Submit setelah dinilai | `POST /assessments/1/submit` | 422 |

### 🔒 TENANT SCOPING (isolasi antar-lembaga)
| # | Skenario | Endpoint | Expected |
|---|----------|----------|----------|
| 21a | Mentor lembaga lain lihat submission | `GET /assessments/1/submissions` | 403 |
| 21b | Mentor lembaga lain nilai submission | `PATCH /submissions/{id}/grade` | 403 |
| 21c | Platform admin lihat submission mana pun | `GET /assessments/1/submissions` | 200 |
| 21d | Mentor pemilik lihat submission | `GET /assessments/1/submissions` | 200 |
| 21e | Mentor lembaga lain daftar sertifikat | `GET /certificates` | 200 (kosong) |
| 21f | Mentor lembaga lain approve sertifikat | `PATCH /certificates/{id}/approve` | 403 |

### 🎮 GAMIFIKASI — `GamificationService` + Listeners
| # | Skenario | Cara | Expected |
|---|----------|------|----------|
| 22 | XP dari penilaian tugas ≥ threshold | `verify_gamification.php` | `XP_TASK=50` |
| 23 | XP dari event `MaterialCompleted` | `verify_gamification.php` | `MATLOG=30` (dari `module.xp_reward`) |
| 24 | XP idempotent (sumber sama tidak dobel) | `verify_gamification.php` | `IDEMPOTENT_AFTER=180` |
| 25 | Naik level otomatis melewati ambang | `verify_gamification.php` | `LEVELUP=Mahir` |

### 🛒 COMMERCE: PAKET & VOUCHER — `PackageController`, `CheckoutController`
| # | Skenario | Endpoint | Expected |
|---|----------|----------|----------|
| 26 | Daftar paket (publik) | `GET /packages` | 200 |
| 27 | Detail paket | `GET /packages/1` | 200 |
| 28 | Paket nonaktif | `GET /packages/2` | 404 |
| 29 | Preview tanpa voucher | `GET /checkout/preview?package_id=1` | 200, `final_price=200000` |
| 30 | Preview voucher persen 10% | `...&voucher_code=DISC10` | 200, `final_price=180000` |
| 31 | Preview voucher tidak valid | `...&voucher_code=NGAWUR` | 422 |
| 32 | Preview voucher kedaluwarsa | `...&voucher_code=EXPIRED` | 422 |
| 33 | Preview paket nonaktif | `GET /checkout/preview?package_id=2` | 422 |

### 💳 COMMERCE: CHECKOUT, ORDER, PAYMENT — `CheckoutController`
| # | Skenario | Endpoint | Expected |
|---|----------|----------|----------|
| 34 | Buat order | `POST /checkout` | 201 |
| 35 | Buat order duplikat (masih pending) | `POST /checkout` | 409 |
| 36 | Riwayat order | `GET /orders` | 200 |
| 37 | Bayar order milik orang lain | `POST /orders/{id}/pay` | 403 |
| 38 | Bayar order sendiri | `POST /orders/{id}/pay` | 200 (+ `enrollment_id`) |
| 39 | Bayar order yang sudah dibayar | `POST /orders/{id}/pay` | 422 |
| 40 | Checkout setelah ter-enroll | `POST /checkout` | 422 |
| 41 | Checkout dengan voucher berbatas (LIMIT1) | `POST /checkout` | 201 |
| 42 | Bayar order siswa2 (voucher LIMIT1) | `POST /orders/{id}/pay` | 200 |
| 43 | Pakai voucher yang sudah habis | `POST /checkout` | 422 |
| 44 | Checkout dengan voucher fixed | `POST /checkout` | 201 |
| 45 | Bayar order buyer | `POST /orders/{id}/pay` | 200 |

### 🏢 INSTITUSI B2B — `InstitutionController`
| # | Skenario | Endpoint | Expected |
|---|----------|----------|----------|
| 46 | Import siswa (2 email baru) | `POST /institution/students/import` | 201, `created_and_enrolled=2`, tanpa `temp_password` |
| 47 | Import ulang (sudah ter-enroll) | `POST /institution/students/import` | 201, `skipped_already_enrolled=2` |
| 48 | Buat undangan | `POST /institution/students/invite` | 201 |
| 49 | Lihat undangan (publik) | `GET /institution/invites/{token}` | 200 |
| 50 | Terima undangan (publik) | `POST /institution/invites/{token}/accept` | 201 (+ token) |
| 51 | Lihat undangan yang sudah dipakai | `GET /institution/invites/{token}` | 410 |
| 52 | Terima undangan 2x | `POST /institution/invites/{token}/accept` | 410 |
| 53 | Undangan kedaluwarsa | `GET /institution/invites/EXPIREDTOKEN123` | 410 |
| 54 | Undangan oleh admin tanpa lembaga | `POST /institution/students/invite` | 422 |

### 🎓 SERTIFIKAT — `CertificateController`
*(`set_progress_100.php` men-set progres siswa & siswa2 menjadi 100%; buyer dibiarkan 0%)*

| # | Skenario | Endpoint | Expected |
|---|----------|----------|----------|
| 55 | Ajukan sertifikat | `POST /certificates/request` | 201 |
| 56 | Ajukan sertifikat duplikat | `POST /certificates/request` | 409 |
| 57 | Ajukan dengan progres < 100% | `POST /certificates/request` | 422 |
| 58 | Sertifikat milik sendiri | `GET /certificates/mine` | 200 |
| 59 | Daftar semua sertifikat (mentor) | `GET /certificates` | 200 |
| 60 | Approve sebagai siswa | `PATCH /certificates/{id}/approve` | 403 |
| 60a | Approve oleh mentor lembaga lain | `PATCH /certificates/{id}/approve` | 403 |
| 61 | Approve sebagai mentor pemilik | `PATCH /certificates/{id}/approve` | 200 |
| 62 | Approve ulang | `PATCH /certificates/{id}/approve` | 422 |
| 63 | Verifikasi publik kode valid | `GET /certificates/verify/{code}` | 200, `valid:true` |
| 64 | Verifikasi kode salah | `GET /certificates/verify/SALAH123` | 404 |
| 65 | Ajukan sertifikat ke-2 (siswa2) | `POST /certificates/request` | 201 |
| 66 | Reject oleh admin | `PATCH /certificates/{id}/reject` | 200, `status:rejected` |

> Beberapa baris matriks dijalankan sebagai beberapa assertion (status + isi body),
> sehingga total assertion = **82**.

---

## 6. Berkas Pendukung di `tests/manual/`

| Berkas | Fungsi |
|--------|--------|
| `setup_all.php` | Seed seluruh data uji |
| `verify_gamification.php` | Verifikasi XP, idempotency, dan naik level |
| `set_progress_100.php` | Set progres enrollment → 100% untuk uji sertifikat |
| `master_test.ps1` | Menjalankan seluruh skenario HTTP & mencetak PASS/FAIL |

---

## 7. Catatan Teknis

- Semua endpoint `/api/*` mengembalikan **JSON** (validasi → 422, unauthenticated → 401),
  sehingga pengujian tidak bergantung pada header `Accept`.
- Pembayaran pada `POST /orders/{id}/pay` masih berupa **simulasi** (Midtrans = roadmap).
- XP bersifat **idempotent** per (`source`, `reference_id`) berkat unique constraint di `xp_logs`.
- Auto-enrollment terjadi melalui event `OrderPaid` → listener `EnrollUserOnPayment`.
- **Rate limiting**: `login` maks 10/menit per email+IP, `register` maks 20/menit per IP
  (percobaan melebihi batas → **429**).
- **Token Sanctum** kedaluwarsa dalam 7 hari (`SANCTUM_EXPIRATION`, default `10080` menit).
- **Tenant scoping**: konten (`modules`/`packages`) punya `institution_id`. Mentor/admin hanya
  bisa melihat & menilai data lembaganya; `admin` tanpa lembaga = platform admin (akses semua).
  Rule `User::canAccessInstitution()` bersifat *null-safe* (null == konten global).
- **Kewajiban enrollment**: submit ke modul yang terikat paket hanya boleh oleh peserta yang
  sudah ter-enroll di paket terkait (modul tanpa paket tetap bebas).
- **CORS** aktif via `config/cors.php` (atur `CORS_ALLOWED_ORIGINS` di `.env` untuk produksi).
