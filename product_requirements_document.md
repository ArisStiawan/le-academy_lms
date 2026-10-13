# PRODUCT REQUIREMENTS DOCUMENT (PRD)
**Nama Produk:** Learning Management System (LMS) Multi Segmen Berbasis Web
**Metode Pengembangan:** Scrum & User Centered Design (UCD)

## 1. Ringkasan Eksekutif (Executive Summary)
LE Academy membutuhkan platform pembelajaran digital (*Learning Management System*) yang mampu mengakomodasi berbagai segmen pengguna secara bersamaan dalam satu sistem yang terintegrasi. Berbeda dengan LMS konvensional yang menerapkan konsep *One Size Fits All*, LMS ini menggunakan pendekatan *Multi Segment Learning Platform*. Hal ini memastikan bahwa setiap pengguna (seperti siswa, karyawan korporat, atau mentor) akan mendapatkan antarmuka, fungsionalitas, dan alur pengalaman belajar yang disesuaikan dengan peran serta kebutuhan spesifik mereka.

## 2. Tujuan Produk
*   Merancang LMS multi-segmen yang adaptif terhadap lima peran pengguna: Administrator, Mentor, Siswa, Karyawan, dan Lembaga Bimbingan.
*   Meningkatkan pengalaman pengguna yang personal menggunakan pendekatan *User Centered Design* (UCD).
*   Menyediakan fitur gamifikasi (poin, *level*, dan *leaderboard*) untuk meningkatkan motivasi belajar siswa.
*   Menyediakan dasbor mandiri untuk Mentor dan Lembaga Bimbingan agar dapat mengelola kegiatan pembelajaran tanpa bergantung secara penuh pada Administrator.

## 3. Ruang Lingkup dan Batasan (Scope & Limitations)
*   **Platform:** Sistem dirancang hanya berbasis *web* dan tidak mencakup pengembangan aplikasi *mobile native* (Android/iOS).
*   **Fokus Pengguna:** Sistem dibatasi untuk mendukung lima jenis pengguna (Role) utama.
*   **Pembayaran:** Perancangan alur pembayaran dibatasi pada *prototype* antarmuka (sandbox mock), belum mencakup integrasi nyata dengan *payment gateway* pihak ketiga (Midtrans disiapkan sebagai roadmap masa depan).
*   **Teknologi:** Tidak mencakup fitur yang menggunakan Kecerdasan Buatan (*Artificial Intelligence*), seperti rekomendasi belajar otomatis.

## 4. Pengguna Sasaran dan Kebutuhan Fungsional (Target Users & Functional Requirements)

**A. Administrator**
*   Administrator mengelola seluruh pengguna sistem seperti mentor, siswa, karyawan, dan lembaga bimbingan.
*   Administrator mengelola kurikulum, konten pembelajaran, dan fitur gamifikasi.
*   Administrator mengatur peran pengguna (*role*) dan hak akses (*permission*).
*   Administrator memonitor aktivitas pengguna dan melihat laporan analitik sistem.
*   Administrator mengelola *voucher* dan menerbitkan sertifikat.
*   Administrator memiliki akses ke dasbor pemantauan (*monitoring*) penjualan.

**B. Mentor (Pengajar)**
*   Mentor mengelola modul pembelajaran, jadwal bimbingan, video, tugas, dan ujian secara mandiri.
*   Mentor mengelola forum diskusi dan ulasan yang diberikan oleh siswa atau karyawan.
*   Mentor dapat melihat laporan perkembangan belajar dari siswa dan karyawan yang diampunya.
*   Mentor mengelola profil dan keamanan akun pribadi mereka.

**C. Siswa (Akademik)**
*   Siswa dapat mengakses materi, tugas, ujian, dan jadwal bimbingan secara daring.
*   Siswa dapat mengerjakan evaluasi (tugas/ujian) dan menerima umpan balik (*feedback*).
*   Siswa dapat melihat hasil belajar, papan peringkat (*leaderboard*), dan poin XP dari sistem gamifikasi.
*   Siswa dapat mengelola profil dan keamanan akun pribadi mereka.

**D. Karyawan (Pelatihan Korporat)**
*   Karyawan dapat mengikuti program pelatihan dan *webinar*, serta mengerjakan evaluasinya.
*   Karyawan dapat mengajukan sertifikasi setelah pelatihan selesai dan melihat riwayat pembelajaran.
*   Karyawan dapat melihat laporan perkembangan pelatihan mereka sendiri.
*   Karyawan dapat mengelola profil dan keamanan akun pribadi mereka.

**E. Lembaga Bimbingan (Mitra B2B)**
*   Lembaga bimbingan dapat mengelola dan mendaftarkan siswanya sendiri ke dalam sistem.
*   Lembaga bimbingan dapat membuat serta mengelola kurikulum dan modul pembelajaran miliknya secara mandiri.
*   Lembaga bimbingan dapat menentukan harga paket belajar dan mendistribusikan *voucher* diskon.
*   Lembaga bimbingan dapat mengakses dasbor pemasukan penjualan paket belajar serta melacak perkembangan siswanya.

## 5. Kebutuhan Non-Fungsional (Non-Functional Requirements)
*   **Usability:** Antarmuka dirancang agar sederhana dan intuitif dengan pendekatan UCD.
*   **Performance:** Mampu memuat dasbor dan konten dengan waktu respons yang cepat pada kondisi akses bersamaan.
*   **Security:** Menggunakan kontrol akses berbasis peran (*Role Based Access Control*) serta mengenkripsi data pengguna dan transaksi.
*   **Scalability:** Sistem dapat menampung penambahan volume pengguna dan institusi mitra di masa mendatang.
*   **Reliability:** Meminimalkan waktu henti (*downtime*) agar proses belajar tidak terganggu.
*   **Compatibility:** Bersifat responsif sehingga dapat diakses melalui peramban *desktop*, *tablet*, maupun *mobile*.

## 6. Arsitektur dan Spesifikasi Teknis (Technical Architecture)
*   **Arsitektur Desain UI/UX:** Menggunakan Figma untuk pembuatan *wireframe* dan prototipe interaktif.
*   **Front-end:** Menggunakan JavaScript/TypeScript dengan *framework* React.js (Next.js) dan CSS.
*   **Back-end:** Menggunakan PHP dengan *framework* Laravel (REST API).
*   **Database:** Menggunakan PostgreSQL.
*   **Infrastruktur & Deployment:** *Cloud hosting* (VPS), *Content Delivery Network* via Cloudflare, dan *File Storage* untuk aset multimedia.
*   **Integrasi Eksternal:** Zoom API (untuk *webinar*), Midtrans API (untuk pembayaran), dan SMTP (untuk notifikasi email).

## 7. Timeline Pelaksanaan (Metode Scrum)
Pelaksanaan proyek ini direncanakan memakan waktu kurang lebih 5 bulan, terbagi menjadi 7 Sprint dengan alur kegiatan sebagai berikut:
*   **Sprint 1:** Riset kebutuhan pengguna dan penyusunan *User Persona*.
*   **Sprint 2:** Perancangan arsitektur sistem dan *Entity Relationship Diagram* (ERD).
*   **Sprint 3:** Perancangan *Use Case Diagram*.
*   **Sprint 4:** Perancangan *Activity Diagram* dan *Sequence Diagram*.
*   **Sprint 5:** Perancangan *Low-fidelity* (*Wireframe*).
*   **Sprint 6:** Perancangan *High-fidelity* (UI interaktif).
*   **Sprint 7:** Penyusunan dokumentasi, laporan akhir proyek, dan penyerahan prototipe. (Catatan: Timeline ini difokuskan pada Perancangan & Prototipe. Fase Development (Coding & Testing) memerlukan Sprint tambahan di luar ruang lingkup laporan ini).