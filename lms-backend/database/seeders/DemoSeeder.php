<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Certificate;
use App\Models\Content;
use App\Models\ContentCompletion;
use App\Models\Curriculum;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Level;
use App\Models\Module;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Package;
use App\Models\Review;
use App\Models\Schedule;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Data contoh untuk pengembangan frontend.
 * Semua akun memakai password: password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'mentor', 'siswa', 'karyawan', 'lembaga'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $levelPemula = Level::firstOrCreate(['name' => 'Pemula'], ['min_xp' => 0]);
        Level::firstOrCreate(['name' => 'Menengah'], ['min_xp' => 500]);
        Level::firstOrCreate(['name' => 'Mahir'], ['min_xp' => 1500]);

        // --- Pengguna -------------------------------------------------------
        $this->user('Admin Platform', 'admin@le.test', 'admin');

        $instA = Institution::firstOrCreate(['name' => 'Bimbel Cerdas'], ['status' => 'active']);
        $instB = Institution::firstOrCreate(['name' => 'Bimbel Juara'], ['status' => 'active']);

        $mentor = $this->user('Mentor Budi', 'mentor@le.test', 'mentor', $instA->id);
        $this->user('Pemilik Bimbel Cerdas', 'lembaga@le.test', 'lembaga', $instA->id);
        $this->user('Pemilik Bimbel Juara', 'lembaga2@le.test', 'lembaga', $instB->id);
        $siswa = $this->user('Siswa Ani', 'siswa@le.test', 'siswa', $instA->id);
        $siswa2 = $this->user('Siswa Doni', 'siswa2@le.test', 'siswa', $instB->id);
        $karyawan = $this->user('Karyawan Citra', 'karyawan@le.test', 'karyawan');

        UserLevel::updateOrCreate(
            ['user_id' => $siswa->id],
            ['level_id' => $levelPemula->id, 'total_xp' => 720]
        );
        UserLevel::updateOrCreate(
            ['user_id' => $siswa2->id],
            ['level_id' => $levelPemula->id, 'total_xp' => 1200]
        );
        UserLevel::updateOrCreate(
            ['user_id' => $karyawan->id],
            ['level_id' => $levelPemula->id, 'total_xp' => 300]
        );

        // --- Paket & kurikulum (per lembaga + global) ------------------------
        $pkgA = Package::firstOrCreate(
            ['title' => 'Bimbingan UTBK Intensif'],
            [
                'institution_id' => $instA->id,
                'description' => 'Persiapan UTBK 3 bulan dengan mentor berpengalaman.',
                'price' => 1500000,
                'is_active' => true,
            ]
        );

        $pkgGlobal = Package::firstOrCreate(
            ['title' => 'Kelas Publik: Dasar Pemrograman'],
            [
                'institution_id' => null,
                'description' => 'Kelas pembuka untuk umum, gratis.',
                'price' => 0,
                'is_active' => true,
            ]
        );

        $curriculum = Curriculum::firstOrCreate(
            ['title' => 'UTBK — Penalaran Umum'],
            [
                'institution_id' => $instA->id,
                'owner_id' => $mentor->id,
                'description' => 'Materi penalaran umum untuk UTBK.',
                'status' => 'published',
            ]
        );

        $module = Module::firstOrCreate(
            ['title' => 'Logika Dasar'],
            [
                'institution_id' => $instA->id,
                'curriculum_id' => $curriculum->id,
                'description' => 'Pengantar logika dan silogisme.',
                'is_active' => true,
                'xp_reward' => 50,
            ]
        );

        Content::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Video: Pengantar Logika'],
            ['type' => 'video', 'external_url' => 'https://example.com/video-logika', 'sort_order' => 1, 'xp_reward' => 10]
        );
        Content::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Latihan Silogisme'],
            ['type' => 'quiz', 'description' => '10 soal latihan.', 'sort_order' => 2, 'xp_reward' => 20]
        );
        Content::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Modul PDF: Rangkuman Logika'],
            ['type' => 'dokumen', 'file_url' => 'https://example.com/rangkuman-logika.pdf', 'sort_order' => 3, 'xp_reward' => 10]
        );
        Content::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Kumpulan Soal Penalaran'],
            ['type' => 'link', 'external_url' => 'https://example.com/soal-penalaran', 'sort_order' => 4, 'xp_reward' => 5]
        );

        // Modul kedua supaya daftar materi & tugas tidak cuma satu modul.
        $module2 = Module::firstOrCreate(
            ['title' => 'Aljabar Dasar'],
            [
                'institution_id' => $instA->id,
                'curriculum_id' => $curriculum->id,
                'description' => 'Operasi aljabar, persamaan, dan pertidaksamaan linear.',
                'is_active' => true,
                'xp_reward' => 60,
            ]
        );

        Content::firstOrCreate(
            ['module_id' => $module2->id, 'title' => 'Video: Persamaan Linear'],
            ['type' => 'video', 'external_url' => 'https://example.com/video-aljabar', 'sort_order' => 1, 'xp_reward' => 15]
        );
        Content::firstOrCreate(
            ['module_id' => $module2->id, 'title' => 'Artikel: Tips Menyusun Persamaan'],
            ['type' => 'link', 'external_url' => 'https://example.com/artikel-aljabar', 'sort_order' => 2, 'xp_reward' => 5]
        );
        Content::firstOrCreate(
            ['module_id' => $module2->id, 'title' => 'Video: Pertidaksamaan Linear'],
            ['type' => 'video', 'external_url' => 'https://example.com/video-pertidaksamaan', 'sort_order' => 3, 'xp_reward' => 15]
        );
        Content::firstOrCreate(
            ['module_id' => $module2->id, 'title' => 'Kuis: Latihan Persamaan'],
            ['type' => 'quiz', 'description' => '8 soal latihan mandiri.', 'sort_order' => 4, 'xp_reward' => 20]
        );

        // Modul ketiga supaya daftar bervariasi (materi, tugas, ujian).
        $module3 = Module::firstOrCreate(
            ['title' => 'Penalaran Verbal'],
            [
                'institution_id' => $instA->id,
                'curriculum_id' => $curriculum->id,
                'description' => 'Pemahaman bacaan, analogi, dan penalaran verbal untuk UTBK.',
                'is_active' => true,
                'xp_reward' => 55,
            ]
        );

        Content::firstOrCreate(
            ['module_id' => $module3->id, 'title' => 'Video: Teknik Pemahaman Bacaan'],
            ['type' => 'video', 'external_url' => 'https://example.com/video-bacaan', 'sort_order' => 1, 'xp_reward' => 10]
        );
        Content::firstOrCreate(
            ['module_id' => $module3->id, 'title' => 'Modul PDF: Strategi Analogi'],
            ['type' => 'dokumen', 'file_url' => 'https://example.com/strategi-analogi.pdf', 'sort_order' => 2, 'xp_reward' => 10]
        );
        Content::firstOrCreate(
            ['module_id' => $module3->id, 'title' => 'Latihan Analogi Kata'],
            ['type' => 'quiz', 'description' => '15 soal analogi.', 'sort_order' => 3, 'xp_reward' => 15]
        );

        // --- Tugas & Ujian ---------------------------------------------------
        // Ringkasan status yang dihasilkan: 1 dinilai, 1 terkumpul, 1 terlambat, sisanya belum.
        Assessment::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Tugas: Analisis Silogisme'],
            [
                'type' => 'tugas',
                'max_score' => 100,
                'passing_score' => 70,
                'xp_reward' => 25,
                'due_at' => now()->addDays(5)->setTime(23, 59),
            ]
        );
        Assessment::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Ujian Tengah: Logika Dasar'],
            [
                'type' => 'ujian',
                'max_score' => 100,
                'passing_score' => 75,
                'xp_reward' => 50,
                'due_at' => now()->addDays(14)->setTime(23, 59),
            ]
        );
        Assessment::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Ujian Akhir: Penalaran Umum'],
            [
                'type' => 'ujian',
                'max_score' => 100,
                'passing_score' => 75,
                'xp_reward' => 80,
                'due_at' => now()->addDays(21)->setTime(23, 59),
            ]
        );
        Assessment::firstOrCreate(
            ['module_id' => $module2->id, 'title' => 'Tugas: Menyelesaikan Persamaan Linear'],
            [
                'type' => 'tugas',
                'max_score' => 100,
                'passing_score' => 70,
                'xp_reward' => 25,
                'due_at' => now()->addDays(9)->setTime(23, 59),
            ]
        );
        Assessment::firstOrCreate(
            ['module_id' => $module2->id, 'title' => 'Ujian: Tryout Aljabar'],
            [
                'type' => 'ujian',
                'max_score' => 100,
                'passing_score' => 70,
                'xp_reward' => 60,
                'due_at' => now()->addDays(30)->setTime(23, 59),
            ]
        );
        Assessment::firstOrCreate(
            ['module_id' => $module3->id, 'title' => 'Tugas: Analogi Kata'],
            [
                'type' => 'tugas',
                'max_score' => 100,
                'passing_score' => 70,
                'xp_reward' => 20,
                'due_at' => now()->addDays(3)->setTime(23, 59),
            ]
        );
        Assessment::firstOrCreate(
            ['module_id' => $module3->id, 'title' => 'Tugas: Ringkasan Bacaan'],
            [
                'type' => 'tugas',
                'max_score' => 100,
                'passing_score' => 70,
                'xp_reward' => 20,
                'due_at' => now()->subDays(2)->setTime(23, 59),
            ]
        );

        $pkgA->modules()->syncWithoutDetaching([
            $module->id => ['sort_order' => 1],
            $module2->id => ['sort_order' => 2],
            $module3->id => ['sort_order' => 3],
        ]);

        // --- Enrollment, order, sertifikat, ulasan ---------------------------
        $enroll = Enrollment::firstOrCreate(
            ['user_id' => $siswa->id, 'package_id' => $pkgA->id],
            ['progress_pct' => 40, 'enrolled_at' => now()->subDays(10)]
        );

        Enrollment::firstOrCreate(
            ['user_id' => $karyawan->id, 'package_id' => $pkgGlobal->id],
            ['progress_pct' => 100, 'enrolled_at' => now()->subDays(30)]
        );

        Order::firstOrCreate(
            ['user_id' => $siswa->id, 'package_id' => $pkgA->id],
            [
                'status' => 'paid',
                'original_price' => 1500000,
                'discount_amount' => 0,
                'final_price' => 1500000,
                'paid_at' => now()->subDays(10),
            ]
        );

        Order::firstOrCreate(
            ['user_id' => $siswa2->id, 'package_id' => $pkgA->id],
            [
                'status' => 'pending',
                'original_price' => 1500000,
                'discount_amount' => 0,
                'final_price' => 1500000,
            ]
        );

        Certificate::firstOrCreate(
            ['user_id' => $siswa->id, 'enrollment_id' => $enroll->id],
            ['status' => 'pending']
        );

        Review::firstOrCreate(
            ['user_id' => $siswa->id, 'package_id' => $pkgA->id],
            ['rating' => 5, 'comment' => 'Mentornya enak diajak diskusi, materinya padat.']
        );

        Schedule::firstOrCreate(
            ['title' => 'Tryout UTBK #1'],
            [
                'institution_id' => $instA->id,
                'mentor_id' => $mentor->id,
                'type' => 'webinar',
                'description' => 'Simulasi UTBK online.',
                'start_at' => now()->addDays(3)->setTime(9, 0),
                'end_at' => now()->addDays(3)->setTime(12, 0),
                'zoom_url' => 'https://zoom.us/j/example',
            ]
        );

        // Bimbingan daring (tatap muka online via Zoom) untuk siswa.
        Schedule::firstOrCreate(
            ['title' => 'Bimbingan Daring: Persiapan UTBK'],
            [
                'institution_id' => $instA->id,
                'mentor_id' => $mentor->id,
                'type' => 'bimbingan',
                'description' => 'Sesi tanya-jawab materi penalaran umum bersama mentor.',
                'start_at' => now()->addDays(2)->setTime(19, 0),
                'end_at' => now()->addDays(2)->setTime(20, 30),
                'zoom_url' => 'https://zoom.us/j/bimbingan-utbk',
            ]
        );

        Schedule::firstOrCreate(
            ['title' => 'Bimbingan Daring: Bedah Soal Aljabar'],
            [
                'institution_id' => $instA->id,
                'mentor_id' => $mentor->id,
                'type' => 'bimbingan',
                'description' => 'Pembahasan soal persamaan dan pertidaksamaan linear.',
                'start_at' => now()->addDays(4)->setTime(16, 0),
                'end_at' => now()->addDays(4)->setTime(17, 30),
                'zoom_url' => 'https://zoom.us/j/bimbingan-aljabar',
            ]
        );

        // --- Contoh pengumpulan: mengisi beragam status ----------------------
        // Status 'dinilai'
        $gradedTugas = Assessment::where('title', 'Tugas: Analisis Silogisme')->first();
        if ($gradedTugas) {
            Submission::firstOrCreate(
                ['assessment_id' => $gradedTugas->id, 'user_id' => $siswa->id],
                [
                    'answer_text' => 'Premis mayor dan minor sudah saya uraikan pada lampiran.',
                    'score' => 85,
                    'feedback' => 'Analisis rapi, perhatikan lagi penarikan kesimpulan.',
                    'submitted_at' => now()->subDays(2),
                ]
            );
        }

        // Status 'dinilai' (kedua, dengan catatan perbaikan)
        $gradedTugas2 = Assessment::where('title', 'Tugas: Menyelesaikan Persamaan Linear')->first();
        if ($gradedTugas2) {
            Submission::firstOrCreate(
                ['assessment_id' => $gradedTugas2->id, 'user_id' => $siswa->id],
                [
                    'answer_text' => 'Langkah pengerjaan soal 1-10 saya tulis lengkap di dokumen.',
                    'score' => 92,
                    'feedback' => 'Sangat baik, pengerjaan sudah sistematis.',
                    'submitted_at' => now()->subDay(),
                ]
            );
        }

        // Status 'terkumpul' (menunggu penilaian mentor)
        $pendingUjian = Assessment::where('title', 'Ujian Tengah: Logika Dasar')->first();
        if ($pendingUjian) {
            Submission::firstOrCreate(
                ['assessment_id' => $pendingUjian->id, 'user_id' => $siswa->id],
                [
                    'answer_text' => 'Jawaban ujian tengah (menunggu penilaian mentor).',
                    'submitted_at' => now()->subDay(),
                ]
            );
        }

        // Status 'terlambat': 'Tugas: Ringkasan Bacaan' jatuh tempo 2 hari lalu
        // dan sengaja TIDAK dikumpulkan (tanpa Submission), jadi dihitung terlambat.

        // --- Progres materi: sebagian konten ditandai selesai ----------------
        $completedTitles = [
            ['Logika Dasar', 'Video: Pengantar Logika'],
            ['Logika Dasar', 'Latihan Silogisme'],
            ['Aljabar Dasar', 'Video: Persamaan Linear'],
        ];
        foreach ($completedTitles as [$moduleTitle, $contentTitle]) {
            $mod = Module::where('title', $moduleTitle)->first();
            $content = $mod
                ? Content::where('module_id', $mod->id)->where('title', $contentTitle)->first()
                : null;
            if ($content) {
                ContentCompletion::firstOrCreate(
                    ['content_id' => $content->id, 'user_id' => $siswa->id],
                    ['completed_at' => now()->subDays(3)]
                );
            }
        }

        // --- Isi bacaan materi (dibuka sebagai halaman baca di dalam app) ----
        // Format ringan: '## ' judul bagian, '- ' butir daftar, '**' tebal.
        $bodies = [
            'Video: Pengantar Logika' => <<<'TXT'
## 1. Apa Itu Logika?

Logika adalah ilmu tentang cara berpikir yang runtut dan masuk akal. Dalam UTBK, logika dipakai untuk menguji kemampuan menarik kesimpulan dari beberapa pernyataan.

## 2. Konsep Dasar

- Pernyataan: kalimat yang bernilai benar atau salah.
- Premis: pernyataan yang menjadi dasar penarikan kesimpulan.
- Kesimpulan: pernyataan baru yang diturunkan dari premis.

## 3. Contoh Sederhana

Jika semua siswa rajin belajar, maka nilai mereka naik. Ani adalah siswa yang rajin belajar. Karena itu, nilai Ani naik.
TXT,
            'Latihan Silogisme' => <<<'TXT'
## 1. Silogisme Kategoris

Silogisme kategoris terdiri dari dua premis dan satu kesimpulan. Pola yang paling sering keluar di UTBK adalah modus ponens dan modus tollens.

## 2. Pola Penting

- Modus ponens: jika p maka q; p benar; maka q benar.
- Modus tollens: jika p maka q; q salah; maka p salah.
- Silogisme hipotetis: jika p maka q; jika q maka r; maka jika p maka r.

## 3. Tips Mengerjakan

Gambar diagram lingkaran untuk setiap premis, lalu periksa apakah kesimpulan pasti benar pada semua kemungkinan.
TXT,
            'Modul PDF: Rangkuman Logika' => <<<'TXT'
## Rangkuman Logika Dasar

Materi ini merangkum seluruh konsep logika yang diujikan pada penalaran umum UTBK.

## Poin Kunci

- Bedakan penalaran deduktif (umum ke khusus) dan induktif (khusus ke umum).
- Perhatikan kata kunci: semua, sebagian, tidak ada.
- Kesimpulan yang sah harus benar pada semua penafsiran premis.

## Latihan Mandiri

Kerjakan 10 soal silogisme pada bagian latihan, lalu bandingkan pola kesalahan yang muncul.
TXT,
            'Kumpulan Soal Penalaran' => <<<'TXT'
## Kumpulan Soal Penalaran Umum

Halaman ini memuat kumpulan soal penalaran yang sering keluar pada UTBK beserta pembahasannya.

## Cara Memakai

- Kerjakan tanpa melihat pembahasan terlebih dahulu.
- Beri tanda pada soal yang butuh waktu lebih dari dua menit.
- Ulangi soal bertanda setelah tiga hari.

## Fokus Materi

Soal terbagi menjadi tiga kelompok: silogisme, analitis, dan penalaran logis berkuantor.
TXT,
            'Video: Persamaan Linear' => <<<'TXT'
## 1. Mengenal Persamaan Linear

Persamaan linear adalah persamaan yang variabelnya berpangkat satu. Bentuk umumnya ax + b = c.

## 2. Langkah Penyelesaian

- Kumpulkan suku yang memuat variabel di satu ruas.
- Pindahkan konstanta ke ruas lainnya.
- Bagi kedua ruas dengan koefisien variabel.

## 3. Contoh

Selesaikan 3x + 5 = 20.
Kurangi 5 dari kedua ruas sehingga 3x = 15, lalu bagi 3 sehingga x = 5.
TXT,
            'Artikel: Tips Menyusun Persamaan' => <<<'TXT'
## Menyusun Persamaan dari Soal Cerita

Banyak siswa kesulitan bukan pada penyelesaian, melainkan pada mengubah kalimat cerita menjadi persamaan.

## Langkah Praktis

- Tentukan hal yang ditanyakan, beri nama dengan satu huruf.
- Terjemahkan tiap kalimat menjadi operasi matematika.
- Periksa kembali satuan dan besar nilainya.

## Contoh

Umur ayah tiga kali umur anak. Jika jumlah umur mereka 48 tahun, misalkan umur anak x, maka x + 3x = 48 sehingga x = 12 tahun.
TXT,
            'Video: Pertidaksamaan Linear' => <<<'TXT'
## 1. Pertidaksamaan Linear

Pertidaksamaan memakai tanda kurang dari, lebih dari, kurang dari sama dengan, atau lebih dari sama dengan.

## 2. Aturan Penting

- Mengalikan atau membagi dengan bilangan negatif membalik arah tanda.
- Himpunan penyelesaian digambar pada garis bilangan.

## 3. Contoh

Selesaikan 2x - 4 lebih dari 6. Tambahkan 4 sehingga 2x lebih dari 10, lalu bagi 2 sehingga x lebih dari 5.
TXT,
            'Kuis: Latihan Persamaan' => <<<'TXT'
## Latihan Persamaan & Pertidaksamaan

Latihan mandiri untuk mengukur pemahaman setelah menonton video persamaan dan pertidaksamaan linear.

## Petunjuk

- Waktu pengerjaan 20 menit.
- Tidak perlu kalkulator.
- Tulis langkah pengerjaan, bukan hanya jawaban akhir.

## Target

Minimal 6 dari 8 soal benar sebelum melanjutkan ke modul penalaran verbal.
TXT,
            'Video: Teknik Pemahaman Bacaan' => <<<'TXT'
## 1. Membaca Efektif

Pemahaman bacaan menguji kecepatan menemukan gagasan utama. Jangan membaca kata per kata; cari kalimat topik tiap paragraf.

## 2. Teknik

- Baca pertanyaan lebih dahulu, lalu cari kata kuncinya di teks.
- Tandai kalimat yang memuat kesimpulan penulis.
- Waspadai pilihan jawaban yang benar tetapi tidak dibahas pada teks.

## 3. Jenis Pertanyaan

Pertanyaan gagasan utama, pernyataan yang sesuai atau tidak sesuai, serta simpulan isi bacaan.
TXT,
            'Modul PDF: Strategi Analogi' => <<<'TXT'
## Strategi Menjawab Analogi

Soal analogi meminta kita menemukan hubungan antara dua kata, lalu menerapkan hubungan yang sama pada pasangan lain.

## Jenis Hubungan

- Bagian ke keseluruhan, misalnya daun ke pohon.
- Fungsi, misalnya pisau ke memotong.
- Sinonim dan antonim.

## Langkah Cepat

Tulis hubungan pasangan soal dalam satu kalimat, lalu uji apakah kalimat itu tetap masuk akal pada pilihan jawaban.
TXT,
            'Latihan Analogi Kata' => <<<'TXT'
## Latihan Analogi Kata

Latihan ini berisi soal analogi dengan berbagai jenis hubungan kata.

## Petunjuk

- Tentukan hubungan pasangan soal dalam satu kalimat.
- Uji setiap pilihan dengan kalimat yang sama.
- Pilih jawaban yang hubungannya paling tepat, bukan yang paling mirip bunyinya.

## Target

15 soal dengan minimal 12 jawaban benar.
TXT,
        ];

        foreach ($bodies as $title => $body) {
            $content = Content::where('title', $title)->first();
            if ($content) {
                $content->update(['body' => $body]);
            }
        }

        // --- Soal pilihan ganda untuk tugas & ujian ---------------------------
        $questions = [
            'Tugas: Analisis Silogisme' => [
                ['Semua mamalia bernapas dengan paru-paru. Paus adalah mamalia. Kesimpulan yang tepat adalah...', ['Paus bernapas dengan paru-paru', 'Paus bukan mamalia', 'Paus tidak perlu bernapas', 'Paus adalah ikan'], 0],
                ['Jika hari hujan maka jalanan basah. Ternyata jalanan tidak basah. Kesimpulan yang sah adalah...', ['Hari tidak hujan', 'Hari hujan', 'Jalanan pasti kering besok', 'Tidak dapat disimpulkan'], 0],
                ['Semua guru di sekolah itu lulusan sarjana. Bu Rina mengajar di sekolah itu. Maka...', ['Bu Rina lulusan sarjana', 'Bu Rina belum tentu sarjana', 'Bu Rina bukan guru', 'Sekolah itu tidak punya guru'], 0],
            ],
            'Ujian Tengah: Logika Dasar' => [
                ['Beberapa siswa gemar matematika. Semua yang gemar matematika rajin berlatih. Kesimpulan yang tepat adalah...', ['Beberapa siswa rajin berlatih', 'Semua siswa rajin berlatih', 'Tidak ada siswa yang rajin', 'Semua siswa gemar matematika'], 0],
                ['Jika x lebih besar dari 5, maka x bilangan positif. Diketahui x = -3. Kesimpulan yang sah adalah...', ['x tidak lebih besar dari 5', 'x bilangan positif', 'x = 5', 'Tidak dapat disimpulkan'], 0],
                ['Penalaran dari hal khusus menuju kesimpulan umum disebut...', ['Induktif', 'Deduktif', 'Silogisme', 'Abduktif'], 0],
            ],
            'Ujian Akhir: Penalaran Umum' => [
                ['Semua pegawai memakai seragam. Budi tidak memakai seragam. Maka...', ['Budi bukan pegawai', 'Budi pegawai baru', 'Budi lupa memakai seragam', 'Budi tetap pegawai'], 0],
                ['Kesimpulan yang benar pada modus tollens adalah...', ['Menyangkal akibat untuk menyangkal sebab', 'Membenarkan sebab untuk membenarkan akibat', 'Menyangkal sebab untuk menyangkal akibat', 'Membenarkan akibat untuk membenarkan sebab'], 0],
                ['Deret 2, 6, 12, 20, ... memiliki pola berupa...', ['n(n+1)', '2n', 'n kuadrat', '3n - 1'], 0],
            ],
            'Tugas: Menyelesaikan Persamaan Linear' => [
                ['Nilai x yang memenuhi 3x + 5 = 20 adalah...', ['5', '8', '15', '25'], 0],
                ['Nilai x dari 2x - 4 = 10 adalah...', ['7', '3', '6', '14'], 0],
                ['Umur ayah tiga kali umur anak. Jumlah umur mereka 48 tahun. Umur anak adalah...', ['12 tahun', '16 tahun', '24 tahun', '36 tahun'], 0],
            ],
            'Ujian: Tryout Aljabar' => [
                ['Himpunan penyelesaian dari 2x - 4 lebih dari 6 adalah...', ['x lebih dari 5', 'x kurang dari 5', 'x lebih dari 10', 'x kurang dari 10'], 0],
                ['Bentuk sederhana dari 4x + 2y - x + 3y adalah...', ['3x + 5y', '5x + 5y', '3x + y', '5x + y'], 0],
                ['Jika 5x = 45, maka nilai 2x + 1 adalah...', ['19', '18', '20', '9'], 0],
            ],
            'Tugas: Analogi Kata' => [
                ['Daun : Pohon = ...', ['Rambut : Kepala', 'Buku : Meja', 'Air : Gelas', 'Kunci : Pintu'], 0],
                ['Pisau : Memotong = Sapu : ...', ['Menyapu', 'Membersihkan lantai', 'Kotor', 'Alat'], 0],
                ['Panas : Dingin = Tinggi : ...', ['Rendah', 'Besar', 'Jauh', 'Cepat'], 0],
            ],
            'Tugas: Ringkasan Bacaan' => [
                ['Gagasan utama sebuah paragraf biasanya terletak pada...', ['Kalimat topik', 'Kalimat terakhir saja', 'Kalimat yang terpanjang', 'Kalimat yang memuat angka'], 0],
                ['Pernyataan berikut yang sesuai dengan bacaan biasanya...', ['Sesuai dengan isi teks secara tersurat', 'Sesuai dengan pendapat pribadi pembaca', 'Selalu memakai kata yang sama persis', 'Tidak berkaitan dengan gagasan utama'], 0],
                ['Langkah pertama memahami bacaan panjang adalah...', ['Membaca pertanyaan terlebih dahulu', 'Menghafal seluruh paragraf', 'Menghitung jumlah kata', 'Membaca dari paragraf terakhir'], 0],
            ],
        ];

        foreach ($questions as $title => $rows) {
            $assessment = Assessment::where('title', $title)->first();
            if (! $assessment) {
                continue;
            }

            foreach ($rows as $index => [$question, $options, $correct]) {
                AssessmentQuestion::firstOrCreate(
                    ['assessment_id' => $assessment->id, 'question' => $question],
                    [
                        'options' => $options,
                        'correct_index' => $correct,
                        'sort_order' => $index + 1,
                    ]
                );
            }
        }

        Notification::firstOrCreate(
            ['user_id' => $siswa->id, 'title' => 'Selamat datang di LE Academy'],
            ['type' => 'info', 'message' => 'Lengkapi profil dan mulai belajar hari ini.']
        );

        $this->command?->info('DemoSeeder selesai. Login: siswa@le.test / password');
    }

    private function user(string $name, string $email, string $role, ?int $institutionId = null): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'institution_id' => $institutionId,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }
}
