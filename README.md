# PALCOM AI SCHEDULER

Sistem Otomasi Penjadwalan Instruktur & Ruangan LKP PalComTech berbasis **Agentic AI**.  
Aplikasi ini secara cerdas mengidentifikasi dampak ketika instruktur berhalangan hadir/izin, mencocokkan kompetensi instruktur pengganti, memeriksa jadwal bentrok, mengecek ketersediaan lab/ruangan, serta menyajikan rekomendasi dengan persetujuan admin (_Human-in-the-Loop_).

---

## 📋 Kebutuhan Sistem (Prerequisites)

Sebelum menjalankan aplikasi di komputer lain, pastikan sudah terinstall:

- **PHP** >= 8.3 (dengan ekstensi `pdo_sqlite`, `mbstring`, `openssl`, `curl`)
- **Composer** (Package Manager PHP)
- **Node.js** (v18+ atau v20+) & **NPM**
- **Git**

---

## 🚀 Panduan Menjalankan Project di Komputer Lain

Ikuti langkah-langkah berikut di terminal / PowerShell / Git Bash:

### 1. Clone & Masuk ke Folder Project

```bash
git clone <URL_REPOSITORY_ANDA>
cd PalcomAIScheduler
```

### 2. Install Dependensi PHP (Composer)

```bash
composer install
```

### 3. Install Dependensi Frontend (NPM)

```bash
npm install
```

### 4. Setup File Environment (`.env`)

Salin file konfigurasi contoh ke `.env`:

- **Windows (PowerShell / CMD):**
    ```cmd
    copy .env.example .env
    ```
- **Linux / macOS / Git Bash:**
    ```bash
    cp .env.example .env
    ```

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Migrasi Database & Seeder Data Contoh

Project ini menggunakan **SQLite** secara default (mudah dan tidak memerlukan konfigurasi server database tambahan).

```bash
php artisan migrate --seed
```

_(Atau jika migrasi sudah dijalankan sebelumnya: `php artisan db:seed`)_

---

## 🔑 Akun Login Default (Admin BAAK)

Setelah menjalankan seeder, gunakan kredensial berikut untuk masuk:

| Keterangan           | Email                    | Password   |
| -------------------- | ------------------------ | ---------- |
| **Admin Utama BAAK** | `admin@palcomtech.ac.id` | `password` |
| **Admin Demo**       | `admin@example.com`      | `password` |

---

### 7. Build Aset Frontend

Untuk development aktif:

```bash
npm run dev
```

Atau untuk membuat bundle production:

```bash
npm run build
```

### 8. Jalankan Server Lokal

Di jendela terminal baru, jalankan:

```bash
php artisan serve
```

Aplikasi sekarang dapat diakses melalui browser di:
👉 **`http://localhost:8000`**

---

## 📂 Panduan Khusus untuk Anggota Tim (Desain & UI)

Untuk partner tim yang mengembangkan tampilan, file-file penting yang dapat dimodifikasi:

| Kebutuhan                   | Lokasi File                                         |
| --------------------------- | --------------------------------------------------- |
| **Sidebar & Navigasi**      | `resources/views/layouts/app/sidebar.blade.php`     |
| **Halaman Dashboard**       | `resources/views/dashboard.blade.php`               |
| **Halaman AI Scheduler**    | `resources/views/pages/ai-scheduler.blade.php`      |
| **Halaman Jadwal Mengajar** | `resources/views/pages/schedules.blade.php`         |
| **Halaman Instruktur**      | `resources/views/pages/instructors.blade.php`       |
| **Halaman Ruangan / Lab**   | `resources/views/pages/rooms.blade.php`             |
| **Halaman Kelas Kursus**    | `resources/views/pages/course-classes.blade.php`    |
| **Halaman Izin Instruktur** | `resources/views/pages/instructor-leaves.blade.php` |
| **Daftar Rute (Routing)**   | `routes/web.php`                                    |
| **Model Basis Data**        | `app/Models/`                                       |

---

## 🧪 Menjalankan Automated Tests

Untuk memastikan seluruh rute, model, dan relasi berfungsi normal:

```bash
php artisan test --compact
```
