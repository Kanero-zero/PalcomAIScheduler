# Test Plan End-to-End: 9 Skenario Demonstrasi PALCOM AI Scheduler

Dokumen ini merupakan panduan resmi pengujian end-to-end (E2E), verifikasi alur operasional, dan skrip demonstrasi interaktif untuk sistem **PALCOM AI Scheduler**. Panduan ini disusun untuk pengujian internal (QA) dan demonstrasi penilaian kompetisi *Agentic AI PalComTech*.

---

## 1. Pemetaan Skenario terhadap Matriks Penilaian Kompetisi

| Matriks Penilaian | Bobot | Aspek yang Diuji | Skenario Terkait |
|---|---|---|---|
| **Business / Work Impact** | 25% | Mengatasi kendala operasional BAAK, percepatan pergantian instruktur izin dari jam ke detik tanpa bentrok jadwal | Skenario 1, 2, 7, 8 |
| **Agentic AI Capability** | 20% | Penalaran otonom Gemini AI dalam mencocokkan kompetensi skill instruktur, ketersediaan waktu, dan prioritas rekomendasi | Skenario 2, 3, 4, 9 |
| **Innovation & Creativity** | 15% | Integrasi deteksi jadwal dinamis, mitigasi fallback mandiri, dan audit trail activity log berbasis timeline | Skenario 5, 6, 9 |
| **Usability & User Experience** | 15% | Antarmuka Livewire responsif, visualisasi status AI, review approval/rejection intuitif, dan filter activity log | Skenario 7, 8, 9 |
| **Accuracy & Reliability** | 10% | Validasi batasan keras (hard constraints: waktu, skill, kapasitas ruangan), transaksi database atomik, data safety guard | Skenario 3, 4, 5, 8 |
| **Implementation & Scalability** | 10% | Isolasi data demo aman (`--isolated`), proteksi registrasi publik, konfigurasi VPS Nginx + PHP 8.3 | Skenario 1, 9, Dokumen VPS |
| **Presentation & Demonstration** | 5% | Idempotensi seeder, eksekusi satu baris, dan skrip demonstrasi yang jelas dan terukur | Seluruh Skenario |

---

## 2. Persiapan Lingkungan & Isolasi Data Demo

### A. Eksekusi Data Demo Standar (Database Lokal/Staging)
Data demo menggunakan tanggal referensi **`2026-10-25`** dan prefix terisolasi **`[DEMO]`**. Perintah ini bersifat idempoten dan aman dijalankan berulang kali:
### A. Eksekusi Data Demo Standar (Database Terisolasi)
Data demo menggunakan tanggal referensi **`2026-10-25`** dan prefix terisolasi **`[DEMO]`**. Perintah ini berjalan pada database terisolasi (`database/demo.sqlite`) atau koneksi aktif:
```bash
# Menyiapkan data demo pada database terisolasi
php artisan scheduler:demo-data --isolated

# Atau jika DEMO_MODE=true aktif di .env (mode showcase web):
php artisan scheduler:demo-data
```

### B. Mode Isolasi Penuh (Aplikasi Web Membaca Database Demo)
Ketika `DEMO_MODE=true` diatur pada `.env`:
- Konfigurasi `database.default` otomatis mengarah ke koneksi `'demo'` (`database/demo.sqlite`).
- Seluruh antarmuka web (Dashboard, Jadwal, Approval, Activity Log) membaca dan menulis ke database demo fisik yang terpisah.
- Database operasional (`database.sqlite` / MySQL) terproteksi 100% dan tidak pernah disentuh.

### C. Pembersihan Data Demo
Untuk mereset atau membersihkan data demo secara aman:
```bash
php artisan scheduler:demo-data --clean --isolated
```

### D. Kredensial Pengujian
- **URL Dashboard:** `http://localhost:8000/dashboard` (atau URL server showcase: `https://palcomaischeduler.web.id/dashboard`)
- **Akun Admin:** `admin@palcomtech.ac.id`
- **Kata Sandi:** Sesuai konfigurasi `.env` (`DEMO_ADMIN_PASSWORD`) atau akun admin yang terdaftar.

---

## 3. Rincian & Langkah Uji 9 Skenario End-to-End

### Skenario 1: Deteksi Otomatis Kelas Terdampak Pengajuan Izin
- **Tujuan:** Memverifikasi kemampuan sistem dalam mendeteksi seluruh kelas aktif yang terdampak ketika seorang instruktur mengajukan izin.
- **Data Awal:** Instruktur **Wahyu** mengajukan izin dinas luar kota pada **2026-10-25** pukul 13:00 - 18:00.
- **Langkah Pengujian:**
  1. Buka menu **Persetujuan Jadwal / Izin Instruktur**.
  2. Buka pengajuan izin Wahyu bertanda `[DEMO] Urusan Dinas Kampus ke Luar Kota`.
  3. Periksa daftar kelas terdampak yang dideteksi oleh Scheduling Engine.
- **Kriteria Keberhasilan (Pass):**
  - Terdeteksi tepat **2 kelas terdampak**:
    1. *[DEMO] Excel Bisnis* (13:00 - 15:00 di Lab 1)
    2. *[DEMO] Word Administrasi* (16:00 - 18:00 di Lab 1)
  - Activity log mencatat: `leave_submitted` dan deteksi kelas terdampak.

---

### Skenario 2: Rekomendasi Instruktur Pengganti Kompeten (Valid Match)
- **Tujuan:** Memverifikasi Gemini AI / Scheduling Engine berhasil memilih instruktur pengganti yang memiliki kompetensi skill yang sesuai dan slot waktu luang.
- **Data Awal:** 
  - Subjek kelas: Microsoft Excel & Microsoft Word.
  - Instruktur **Kanero**: Memiliki keahlian *Microsoft Excel* (Advanced) dan *Microsoft Word* (Advanced), tidak ada jadwal mengajar pada 13:00 - 18:00.
- **Langkah Pengujian:**
  1. Pada halaman review pengajuan izin Wahyu, periksa tab/panel **Rekomendasi AI / Kandidat Pengganti**.
  2. Evaluasi rekomendasi untuk kelas Excel Bisnis dan Word Administrasi.
- **Kriteria Keberhasilan (Pass):**
  - **Kanero** direkomendasikan sebagai kandidat utama dengan skor kesesuaian tinggi.
  - Keterangan AI menyebutkan kecocokan skill *Microsoft Excel* dan ketersediaan waktu.

---

### Skenario 3: Eliminasi Kandidat Tidak Kompeten (Skill Mismatch Guard)
- **Tujuan:** Memastikan instruktur yang tidak memiliki keahlian relevan tidak akan pernah direkomendasikan untuk mengajar mata kuliah tersebut.
- **Data Awal:** Instruktur **Dina Oktavia** aktif dan memiliki slot waktu kosong pada jam 13:00 - 18:00, namun keahliannya hanya *Desain Grafis*.
- **Langkah Pengujian:**
  1. Periksa daftar kandidat untuk kelas *[DEMO] Excel Bisnis* dan *[DEMO] Word Administrasi*.
  2. Pastikan Dina Oktavia dievaluasi oleh sistem.
- **Kriteria Keberhasilan (Pass):**
  - Dina Oktavia **tidak direkomendasikan** untuk mengajar kelas Excel maupun Word.
  - Alasan sistem: *Keahlian tidak memenuhi persyaratan mata kuliah (Desain Grafis != Microsoft Excel).*

---

### Skenario 4: Pencegahan Bentrok Jadwal Mengajar (Collision Prevention)
- **Tujuan:** Memastikan instruktur yang memiliki skill sesuai tetapi sedang mengajar di kelas/ruangan lain pada jam yang sama ditolak demi mencegah *double-booking*.
- **Data Awal:** Instruktur **Budi Santoso** memiliki keahlian *Microsoft Excel* (Intermediate), namun telah dijadwalkan mengajar kelas Excel lain pada pukul 13:00 - 15:00 di Lab 2.
- **Langkah Pengujian:**
  1. Periksa evaluasi kandidat untuk slot 13:00 - 15:00 (*[DEMO] Excel Bisnis*).
  2. Amati status ketersediaan Budi Santoso.
- **Kriteria Keberhasilan (Pass):**
  - Budi Santoso ditandai bentrok (*conflicted / unavailable*).
  - Sistem tidak menyarankan Budi Santoso untuk menggantikan Wahyu pada slot 13:00 - 15:00.

---

### Skenario 5: Evaluasi Kelayakan Kapasitas & Status Ruangan
- **Tujuan:** Memverifikasi evaluasi ruangan mendeteksi ruangan yang kapasitasnya kurang atau berstatus perbaikan (*maintenance*).
- **Data Awal:** Kelas *[DEMO] Pemrograman Web Lanjut* (30 mahasiswa) dijadwalkan di *[DEMO] Lab 3 Mini* (kapasitas 15, status maintenance).
- **Langkah Pengujian:**
  1. Buka izin Wahyu sesi pagi (08:00 - 11:00).
  2. Periksa evaluasi ruangan untuk kelas Web Lanjut.
- **Kriteria Keberhasilan (Pass):**
  - Sistem menandai adanya *Room Constraint Issue*: kapasitas ruangan (15) lebih kecil dari jumlah mahasiswa (30).
  - Status ruangan terdeteksi *maintenance*, dan sistem merekomendasikan pemindahan ke Lab 1 atau Lab 2 yang berkapasitas 35/30.

---

### Skenario 6: Pengajuan Izin Tanpa Kelas Terdampak (Zero Impact Flow)
- **Tujuan:** Memverifikasi sistem menangani kasus izin di mana instruktur tidak memiliki jadwal mengajar pada jam tersebut tanpa memicu kepanikan sistem atau proses pergantian yang tidak perlu.
- **Data Awal:** Instruktur **Rizky Pratama** mengajukan izin pada 2026-10-25 pukul 08:00 - 12:00, namun tidak memiliki jadwal mengajar di hari tersebut.
- **Langkah Pengujian:**
  1. Buka pengajuan izin Rizky Pratama.
  2. Periksa ringkasan analisis AI.
- **Kriteria Keberhasilan (Pass):**
  - Terdeteksi **0 kelas terdampak**.
  - Status rekomendasi AI ditandai **`not_applicable`** (*Analisis tidak diperlukan karena tidak ada jadwal terdampak*).
  - Tombol aksi penggantian dinonaktifkan secara anggun.

---

### Skenario 7: Alur Persetujuan Resmi (Approval & Live Schedule Update)
- **Tujuan:** Memverifikasi alur persetujuan admin berjalan secara atomik, memperbarui jadwal mengajar secara instan, dan mencatat log audit transaksi.
- **Data Awal:** Kelas *[DEMO] Excel Pagi Disetujui* (10:00 - 12:00) telah disetujui dengan pengganti **Kanero**.
- **Langkah Pengujian:**
  1. Buka halaman **Jadwal Mengajar** pada tanggal `2026-10-25`.
  2. Periksa pengajar kelas *Excel Pagi Disetujui*.
  3. Buka halaman **Activity Log**.
- **Kriteria Keberhasilan (Pass):**
  - Jadwal mengajar telah beralih ke instruktur **Kanero**.
  - Pada Activity Log, tercatat dua riwayat berurutan:
    - `approval`: *Admin menyetujui Kanero menggantikan Wahyu.*
    - `schedule_updated`: *Jadwal kuliah diperbarui dengan instruktur Kanero.*
  - Identitas admin tercatat sebagai aktor pelaksana.

---

### Skenario 8: Alur Penolakan Usulan dengan Alasan Audit (Rejection Flow)
- **Tujuan:** Memverifikasi admin dapat menolak usulan pergantian dengan mencantumkan alasan wajib, menjaga jadwal asli tetap utuh, dan menyimpan alasan penolakan dalam audit trail.
- **Data Awal:** Kelas *[DEMO] Word Sore Ditolak* (19:00 - 21:00), permohonan substitusi ditolak admin karena instruktur melebihi batas SKS mingguan.
- **Langkah Pengujian:**
  1. Buka halaman riwayat persetujuan untuk pengajuan izin Word Sore.
  2. Periksa status substitusi dan alasan penolakan.
  3. Buka halaman jadwal untuk kelas tersebut.
- **Kriteria Keberhasilan (Pass):**
  - Status substitusi: **`rejected`**.
  - Alasan penolakan tampil jelas: *"Usulan instruktur pengganti belum dapat disetujui karena beban mengajar sudah melampaui batas maksimum SKS mingguan."*
  - Instruktur pada jadwal asli tetap **Wahyu** (tidak berubah).
  - Activity Log mencatat aktivitas `rejection` beserta alasan penolakannya.

---

### Skenario 9: Resiliensi Kegagalan AI (Gemini Fallback & Audit Trail)
- **Tujuan:** Memverifikasi ketahanan sistem ketika Google Gemini API mengalami timeout, rate-limiting (HTTP 429), atau jaringan terputus. Sistem wajib secara otomatis beralih ke engine deterministik tanpa menampilkan error layar putih kepada pengguna.
- **Pembedaan Validasi (Simulasi vs Eksekusi Nyata):**
  1. **Log Data Seeder (Showcase Audit Trail):**
     - Pada seeder awal, tercatat entri `[DEMO SIMULATION]` bertipe `ai` dengan state `fallback` dan metadata `'simulation' => true`.
     - Tujuannya agar penilai/juri dapat langsung mengamati bagaimana status fallback divisualisasikan pada timeline audit log tanpa harus memutus koneksi internet.
  2. **Eksekusi Pengujian Nyata (Real Failure Execution Test):**
     - Diuji secara otomatis melalui `test('skenario gemini fallback yang benar-benar dieksekusi saat api gagal menghasilkan fallback status valid')`.
     - Menggunakan `Http::fake([... => 503])` yang memanggil `SchedulingEngine::evaluateWithAi()`.
     - Membuktikan secara nyata bahwa saat API gagal, sistem tidak melempar exception fatal, rekomendasi tetap dihasilkan via rule-based deterministik, dan log fallback baru tercipta secara otomatis.
- **Langkah Pengujian UI:**
  1. Buka halaman **Activity Log**.
  2. Terapkan filter status AI ke **`fallback`**.
  3. Buka detail log *Simulasi Fallback AI: Layanan Gemini Dialihkan ke Deterministik*.
- **Kriteria Keberhasilan (Pass):**
  - Status AI menampilkan badge **`fallback`**.
  - Deskripsi log menyatakan: *"[SIMULASI] Layanan Gemini AI disimulasikan mengalami timeout atau batasan kuota. Rekomendasi dialihkan otomatis ke hasil deterministik sistem."*
  - Metadata memuat `'fallback_used' => true` dan `'simulation' => true`.
  - Sistem tetap menyediakan rekomendasi pengganti berbasis algoritma rule-based internal.

---

## 4. Matriks Pengujian Otomatis (*Automated Regression Suite*)

Seluruh skenario di atas diverifikasi melalui rangkaian automated test Pest:

```bash
# 1. Uji Integritas & Keamanan Data Skenario Demo
php artisan test --filter=DemoScenarioTest

# 2. Uji Layanan & Penyimpanan Activity Log
php artisan test --filter=ActivityLogTest

# 3. Uji Alur Persetujuan & Penolakan Substitusi
php artisan test --filter=ScheduleApprovalTest

# 4. Uji Nonaktif Registrasi Publik (Security PR #19)
php artisan test --filter=RegistrationDisabledTest

# 5. Jalankan Seluruh Suite Uji Terpadu
php artisan test --compact
```

---

## 5. Ringkasan Kesiapan Demonstrasi Juri

- [x] **Data Idempoten:** Seluruh data demo dapat dibuat dan dihapus ulang kapan saja menggunakan `php artisan scheduler:demo-data`.
- [x] **Tanpa Risiko Kerusakan Data:** Operasi `clean()` hanya menghapus entitas ber-prefix `[DEMO]`, menjaga integritas data riil.
- [x] **Keamanan Produksi Terjamin:** Diberlakukan perlindungan ganda via `ALLOW_DEMO_DATA=true` dan `--force-demo`.
- [x] **Audit Lengkap:** Setiap tindakan tercatat pada tabel `activity_logs` dengan dukungan filter status AI (`success`, `fallback`, `not_applicable`).
- [x] **Siap Uji Online VPS:** Seluruh skrip deployment dan konfigurasi Nginx PHP 8.3 telah diselaraskan pada PR #18.
