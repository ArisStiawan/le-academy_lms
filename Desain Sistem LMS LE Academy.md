# Desain Sistem LMS Multi Segmen — LE Academy

Turunan dari PRD: Arsitektur Sistem, ERD PostgreSQL, dan UML (Use Case, Activity, Sequence).

## 1. Arsitektur Sistem

```mermaid
flowchart TB
  subgraph Client["Client (Browser Responsif)"]
    U1[Admin] & U2[Mentor] & U3[Siswa] & U4[Karyawan] & U5[Lembaga Bimbingan]
  end
  CF[Cloudflare CDN + WAF]
  subgraph FE["Front-end: Next.js / React + TypeScript"]
    RT[Role-based Routing & Dashboard per Segmen]
  end
  subgraph BE["Back-end: Laravel REST API"]
    AUTH[Auth + RBAC]
    CORE[Modul: User, Kurikulum, Konten, Tugas/Ujian]
    GAM[Gamifikasi: XP, Level, Leaderboard]
    COM[Commerce: Paket, Voucher, Order]
    CERT[Sertifikat]
    RPT[Laporan & Analitik]
    NTF[Notifikasi]
  end
  DB[(PostgreSQL)]
  FS[(File Storage: video, materi)]
  subgraph EXT["Integrasi Eksternal"]
    ZOOM[Zoom API]
    MID[Midtrans API - prototype]
    SMTP[SMTP Email]
  end
  Client --> CF --> FE -->|HTTPS / JSON| BE
  BE --> DB
  BE --> FS
  CORE --> ZOOM
  COM --> MID
  NTF --> SMTP
  AUTH --- CORE
  CORE --- GAM
  COM --- CERT
  CORE --- RPT
```

Catatan: Midtrans pada ruang lingkup PRD hanya sampai prototype antarmuka; API disiapkan sebagai titik integrasi masa depan.

## 2. ERD (PostgreSQL)

```mermaid
erDiagram
  ROLES ||--o{ USERS : memiliki
  ROLES ||--o{ ROLE_PERMISSIONS : punya
  PERMISSIONS ||--o{ ROLE_PERMISSIONS : diberikan
  INSTITUTIONS ||--o{ USERS : menaungi
  USERS ||--o| PROFILES : detail
  USERS ||--o{ CURRICULA : membuat
  INSTITUTIONS ||--o{ CURRICULA : memiliki
  CURRICULA ||--o{ MODULES : berisi
  MODULES ||--o{ CONTENTS : berisi
  MODULES ||--o{ ASSESSMENTS : memiliki
  ASSESSMENTS ||--o{ SUBMISSIONS : dikerjakan
  USERS ||--o{ SUBMISSIONS : mengumpulkan
  CURRICULA ||--o{ PACKAGES : dijual_sebagai
  PACKAGES ||--o{ ORDERS : dipesan
  USERS ||--o{ ORDERS : membeli
  VOUCHERS ||--o{ ORDERS : dipakai
  ORDERS ||--o| PAYMENTS : dibayar
  ORDERS ||--o{ ENROLLMENTS : menghasilkan
  USERS ||--o{ ENROLLMENTS : terdaftar
  PACKAGES ||--o{ ENROLLMENTS : mencakup
  USERS ||--o{ SCHEDULES : mentor_mengampu
  CURRICULA ||--o{ SCHEDULES : dijadwalkan
  SCHEDULES ||--o| WEBINARS : zoom
  CURRICULA ||--o{ FORUM_THREADS : diskusi
  FORUM_THREADS ||--o{ FORUM_POSTS : balasan
  USERS ||--o{ FORUM_POSTS : menulis
  CURRICULA ||--o{ REVIEWS : diulas
  USERS ||--o{ REVIEWS : menulis
  USERS ||--o{ XP_LOGS : memperoleh
  LEVELS ||--o{ USERS_LEVEL : tingkat
  USERS ||--o| USERS_LEVEL : status
  ENROLLMENTS ||--o{ CERTIFICATES : terbit
  USERS ||--o{ CERTIFICATES : dimiliki
  USERS ||--o{ NOTIFICATIONS : menerima
  USERS ||--o{ ACTIVITY_LOGS : tercatat

  ROLES { bigint id PK
    varchar name "admin, mentor, siswa, karyawan, lembaga" }
  PERMISSIONS { bigint id PK
    varchar code }
  ROLE_PERMISSIONS { bigint role_id FK
    bigint permission_id FK }
  INSTITUTIONS { bigint id PK
    varchar name
    varchar status
    timestamp created_at }
  USERS { bigint id PK
    bigint role_id FK
    bigint institution_id FK
    varchar name
    varchar email
    varchar password_hash
    boolean is_active
    timestamp created_at }
  PROFILES { bigint id PK
    bigint user_id FK
    varchar phone
    varchar photo_url
    varchar company_name "karyawan"
    varchar school_name "siswa"
    text bio "mentor" }
  CURRICULA { bigint id PK
    bigint owner_id FK
    bigint institution_id FK
    varchar title
    text description
    varchar status }
  MODULES { bigint id PK
    bigint curriculum_id FK
    varchar title
    int sort_order }
  CONTENTS { bigint id PK
    bigint module_id FK
    varchar type "video, dokumen, link"
    varchar title
    varchar file_url }
  ASSESSMENTS { bigint id PK
    bigint module_id FK
    varchar type "tugas, ujian"
    varchar title
    int max_score
    int xp_reward
    timestamp due_at }
  SUBMISSIONS { bigint id PK
    bigint assessment_id FK
    bigint user_id FK
    int score
    text feedback
    timestamp submitted_at }
  PACKAGES { bigint id PK
    bigint curriculum_id FK
    bigint seller_institution_id FK
    varchar name
    numeric price
    boolean is_active }
  VOUCHERS { bigint id PK
    bigint institution_id FK
    varchar code
    numeric discount_value
    varchar discount_type
    date expired_at }
  ORDERS { bigint id PK
    bigint user_id FK
    bigint package_id FK
    bigint voucher_id FK
    numeric total
    varchar status }
  PAYMENTS { bigint id PK
    bigint order_id FK
    varchar method
    varchar midtrans_ref
    varchar status
    timestamp paid_at }
  ENROLLMENTS { bigint id PK
    bigint user_id FK
    bigint package_id FK
    bigint order_id FK
    numeric progress_pct
    varchar status }
  SCHEDULES { bigint id PK
    bigint curriculum_id FK
    bigint mentor_id FK
    timestamp start_at
    timestamp end_at
    varchar type "bimbingan, webinar" }
  WEBINARS { bigint id PK
    bigint schedule_id FK
    varchar zoom_meeting_id
    varchar join_url }
  FORUM_THREADS { bigint id PK
    bigint curriculum_id FK
    bigint author_id FK
    varchar title }
  FORUM_POSTS { bigint id PK
    bigint thread_id FK
    bigint user_id FK
    text body
    timestamp created_at }
  REVIEWS { bigint id PK
    bigint curriculum_id FK
    bigint user_id FK
    int rating
    text comment }
  XP_LOGS { bigint id PK
    bigint user_id FK
    int points
    varchar source
    timestamp created_at }
  LEVELS { bigint id PK
    varchar name
    int min_xp }
  USERS_LEVEL { bigint user_id FK
    bigint level_id FK
    int total_xp }
  CERTIFICATES { bigint id PK
    bigint user_id FK
    bigint enrollment_id FK
    varchar status "diajukan, disetujui, terbit"
    varchar certificate_no
    timestamp issued_at }
  NOTIFICATIONS { bigint id PK
    bigint user_id FK
    varchar channel "app, email"
    text message
    boolean is_read }
  ACTIVITY_LOGS { bigint id PK
    bigint user_id FK
    varchar action
    timestamp created_at }
```

Leaderboard dibuat sebagai VIEW/materialized view dari `users_level.total_xp` (tidak perlu tabel terpisah). Indeks disarankan pada `users.email` (unique), `enrollments(user_id, package_id)`, `xp_logs(user_id, created_at)`.

## 3. UML

### 3.1 Use Case Diagram

```mermaid
flowchart LR
  AD([Administrator]) --- A1(Kelola pengguna, role, permission)
  AD --- A2(Kelola kurikulum, konten, gamifikasi)
  AD --- A3(Kelola voucher & terbitkan sertifikat)
  AD --- A4(Monitor aktivitas, analitik, penjualan)

  MT([Mentor]) --- M1(Kelola modul, video, tugas, ujian)
  MT --- M2(Kelola jadwal bimbingan)
  MT --- M3(Kelola forum & ulasan)
  MT --- M4(Lihat laporan perkembangan peserta)

  SW([Siswa]) --- S1(Akses materi, tugas, ujian, jadwal)
  SW --- S2(Kerjakan evaluasi & terima feedback)
  SW --- S3(Lihat poin XP & leaderboard)

  KR([Karyawan]) --- K1(Ikuti pelatihan & webinar)
  KR --- K2(Ajukan sertifikasi)
  KR --- K3(Lihat riwayat & laporan pelatihan)

  LB([Lembaga Bimbingan]) --- L1(Daftarkan & kelola siswa)
  LB --- L2(Kelola kurikulum & modul sendiri)
  LB --- L3(Tentukan harga paket & bagikan voucher)
  LB --- L4(Dasbor pemasukan & progres siswa)

  A1 -. include .-> LG(Login + RBAC)
  M1 -. include .-> LG
  S1 -. include .-> LG
  K1 -. include .-> LG
  L1 -. include .-> LG
  S2 -. include .-> PR(Update XP)
  K2 -. include .-> A3
```

Semua aktor juga memiliki use case "Kelola profil & keamanan akun".

### 3.2 Activity Diagram — Pembelian Paket hingga Sertifikat

```mermaid
flowchart TD
  S((Mulai)) --> A[Pengguna pilih paket belajar]
  A --> B{Punya voucher?}
  B -- Ya --> C[Input voucher, validasi]
  C --> D{Valid?}
  D -- Tidak --> C
  D -- Ya --> E[Hitung total diskon]
  B -- Tidak --> E
  E --> F[Buat order & bayar via Midtrans]
  F --> G{Pembayaran berhasil?}
  G -- Tidak --> H[Order dibatalkan / expired] --> X((Selesai))
  G -- Ya --> I[Enrollment aktif + notifikasi email]
  I --> J[Belajar: materi, tugas, ujian]
  J --> K[Nilai & feedback; tambah XP]
  K --> L{Progres 100%?}
  L -- Tidak --> J
  L -- Ya --> M[Ajukan sertifikat]
  M --> N{Admin setuju?}
  N -- Tidak --> O[Revisi / ditolak] --> X
  N -- Ya --> P[Sertifikat terbit] --> X
```

### 3.3 Activity Diagram — Pengerjaan Evaluasi & Gamifikasi

```mermaid
flowchart TD
  S((Mulai)) --> A[Siswa buka tugas/ujian]
  A --> B[Kerjakan & submit]
  B --> C[Mentor / sistem menilai]
  C --> D[Feedback tersimpan]
  D --> E[Tambah XP ke xp_logs]
  E --> F{Total XP lewati batas level?}
  F -- Ya --> G[Naik level] --> H[Perbarui leaderboard]
  F -- Tidak --> H
  H --> Z((Selesai))
```

### 3.4 Sequence Diagram — Login & RBAC

```mermaid
sequenceDiagram
  actor U as Pengguna
  participant FE as Next.js
  participant API as Laravel API
  participant DB as PostgreSQL
  U->>FE: Input email & password
  FE->>API: POST /auth/login
  API->>DB: Cek user + role + permission
  DB-->>API: Data user
  API-->>FE: Token + role
  FE-->>U: Redirect ke dasbor sesuai role
  U->>FE: Akses halaman
  FE->>API: GET /resource (Bearer token)
  API->>API: Cek permission (RBAC)
  API-->>FE: 200 data / 403 ditolak
```

### 3.5 Sequence Diagram — Pembayaran Paket

```mermaid
sequenceDiagram
  actor S as Siswa/Karyawan
  participant FE as Next.js
  participant API as Laravel API
  participant DB as PostgreSQL
  participant MID as Midtrans
  participant MAIL as SMTP
  S->>FE: Checkout paket (+ voucher)
  FE->>API: POST /orders
  API->>DB: Validasi voucher, simpan order (pending)
  API->>MID: Buat transaksi
  MID-->>API: Snap token
  API-->>FE: Snap token
  S->>MID: Bayar
  MID-->>API: Webhook notifikasi status
  API->>DB: Update payment, buat enrollment
  API->>MAIL: Kirim email konfirmasi
  API-->>FE: Status berhasil
  FE-->>S: Akses kelas terbuka
```

### 3.6 Sequence Diagram — Pengajuan Sertifikat

```mermaid
sequenceDiagram
  actor K as Karyawan
  participant API as Laravel API
  participant DB as PostgreSQL
  actor AD as Administrator
  K->>API: POST /certificates (enrollment_id)
  API->>DB: Cek progres 100%
  DB-->>API: Memenuhi syarat
  API->>DB: Simpan status "diajukan"
  AD->>API: PATCH /certificates/{id} approve
  API->>DB: Status "terbit" + nomor sertifikat
  API-->>K: Notifikasi sertifikat siap diunduh
```