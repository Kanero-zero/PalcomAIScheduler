# Panduan Teknis Deployment VPS & Operasional: PALCOM AI Scheduler

Dokumen ini merupakan panduan resmi arsitektur deployment VPS (Virtual Private Server), konfigurasi server web, pengamanan rahasia, integritas database, dan prosedur pemulihan bencana (*disaster recovery*) untuk aplikasi **PALCOM AI Scheduler** (Laravel 13, PHP 8.3, Livewire 4, Flux UI, SQLite / MySQL).

---

## 1. Prasyarat Server & Spesifikasi Minimum

### A. Rekomendasi Spesifikasi Hardware
- **OS:** Ubuntu 24.04 LTS (Noble Numbat) atau Debian 12 (Bookworm) 64-bit.
- **CPU:** Minimal 2 vCPU (disarankan 4 vCPU untuk performa optimal asset bundling dan antrean).
- **RAM:** Minimal 2 GB RAM + 2 GB Swap (disarankan 4 GB RAM).
- **Storage:** Minimal 25 GB SSD / NVMe.
- **Network:** IP Publik statis, port `80` (HTTP), `443` (HTTPS), dan `22` (SSH) terbuka.

### B. Paket Perangkat Lunak Utama
1. **Web Server:** Nginx 1.24+
2. **PHP Runtime:** PHP 8.3-FPM
   - Ekstensi wajib: `php8.3-cli`, `php8.3-fpm`, `php8.3-common`, `php8.3-sqlite3` (atau `php8.3-mysql`), `php8.3-mbstring`, `php8.3-xml`, `php8.3-curl`, `php8.3-zip`, `php8.3-bcmath`, `php8.3-intl`, `php8.3-pcntl`
3. **Dependency Managers:**
   - Composer 2.7+
   - Node.js 20.x LTS / 22.x LTS & npm 10+
4. **SSL / TLS:** Certbot & python3-certbot-nginx (Let's Encrypt)
5. **Utilitas Sistem:** `git`, `curl`, `unzip`, `tar`, `gzip`, `cron`, `supervisor` (atau systemd).

---

## 2. Struktur Direktori & Alur Rilis (*Release Architecture*)

Direktori root aplikasi berada pada:
```
/var/www/PalcomAIScheduler/
```

### Hak Akses & Kepemilikan (*Ownership & Permissions*)
```bash
# Set pemilik direktori ke user deployment dan web server (www-data)
sudo chown -R $USER:www-data /var/www/PalcomAIScheduler

# Berikan izin tulis untuk storage dan cache ke grup web server
sudo chmod -R 775 /var/www/PalcomAIScheduler/storage
sudo chmod -R 775 /var/www/PalcomAIScheduler/bootstrap/cache
sudo chmod -R 775 /var/www/PalcomAIScheduler/database
```

Jika menggunakan SQLite, letakkan file database di luar web root pada `/var/lib/palcom-ai-scheduler/database.sqlite` demi keamanan dan ketahanan data:
```bash
sudo mkdir -p /var/lib/palcom-ai-scheduler
sudo touch /var/lib/palcom-ai-scheduler/database.sqlite
sudo chown -R www-data:www-data /var/lib/palcom-ai-scheduler
sudo chmod 775 /var/lib/palcom-ai-scheduler
sudo chmod 664 /var/lib/palcom-ai-scheduler/database.sqlite
```

---

## 3. Konfigurasi Nginx & HTTPS

File konfigurasi server Nginx siap pakai tersedia di [`deploy/nginx/palcom-ai-scheduler.conf`](deploy/nginx/palcom-ai-scheduler.conf).

### Langkah Pemasangan:
```bash
# 1. Salin konfigurasi ke sites-available
sudo cp deploy/nginx/palcom-ai-scheduler.conf /etc/nginx/sites-available/palcom-ai-scheduler

# 2. Aktifkan konfigurasi dengan symbolic link
sudo ln -sf /etc/nginx/sites-available/palcom-ai-scheduler /etc/nginx/sites-enabled/

# 3. Uji sintaks Nginx
sudo nginx -t

# 4. Reload Nginx
sudo systemctl reload nginx

# 5. Pasang sertifikat SSL gratis via Certbot
sudo certbot --nginx -d palcomaischeduler.web.id
```

> [!IMPORTANT]
> **Perlindungan Konfigurasi HTTPS (Certbot):**
> Setelah `certbot --nginx` dijalankan, Certbot akan menyisipkan blok sertifikat SSL (`listen 443 ssl;` dan jalur certificate Let's Encrypt) langsung ke file konfigurasi Nginx aktif. **Jangan pernah menimpa file `/etc/nginx/sites-available/palcom-ai-scheduler` secara utuh dengan file template mentah** setelah SSL aktif, karena hal tersebut akan menghapus konfigurasi HTTPS yang sudah berjalan.

### Fitur Keamanan Konfigurasi Nginx:
- **HTTP/2 & TLS 1.3** untuk transfer data terenkripsi dan cepat.
- **Security Headers:** `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`.
- **Gzip Compression:** Mengompres file JS, CSS, JSON, SVG, dan HTML hingga 70%.
- **Asset Caching:** Browser caching 1 tahun (`max-age=31536000, immutable`) untuk aset statis Vite ter-bundle di `public/build/`.
- **Blokir File Tersembunyi:** Melarang akses langsung ke `.env`, `.git`, `.htaccess`, dsb.

---

## 4. Konfigurasi Lingkungan (`.env`) & Keamanan Rahasia

### A. Standar Keamanan `.env.production`
Dilarang keras meng-commit file `.env` ke Git! Siapkan file `.env` langsung di VPS:

```ini
APP_NAME="PALCOM AI Scheduler"
APP_ENV=production
APP_KEY=base64:GENERATE_VIA_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://palcomaischeduler.web.id
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null

# Database: SQLite Production (Direkomendasikan WAL Mode)
DB_CONNECTION=sqlite
DB_DATABASE=/var/lib/palcom-ai-scheduler/database.sqlite
DB_FOREIGN_KEYS=true

# Cache & Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

CACHE_STORE=database

# Queue
QUEUE_CONNECTION=database

# Google Gemini AI Integration (Production Secret)
GEMINI_API_KEY=AIzaSy_PROD_SECRET_KEY_HERE
GEMINI_MODEL=gemini-2.5-flash
GEMINI_REQUEST_TIMEOUT=15

# Admin Whitelist
ADMIN_EMAILS=admin@palcomtech.ac.id

# Showcase Demo Mode (Opsional untuk instance demo publik juri lomba)
# Nilai default false demi keamanan produksi. Setel true HANYA jika server ini adalah server showcase demo.
DEMO_MODE=false
```

### B. Zero-Leakage Checklist
- [x] Pastikan `APP_DEBUG=false` untuk mencegah kebocoran stack trace dan kredensial database saat terjadi unhandled exception.
- [x] Pastikan `GEMINI_API_KEY` hanya disetel pada `.env` server atau injected secrets CI/CD; dilarang keras ditulis di kode atau di-commit ke repositori.
- [x] Sesi cookie disetel `SESSION_SECURE_COOKIE=true` agar hanya dikirim melalui koneksi HTTPS.

### C. Menjalankan Data Demo Terisolasi pada Showcase Publik
Untuk kebutuhan penilaian juri pada website publik:
1. **Isolasi Database Penuh:** Saat `DEMO_MODE=true` aktif, aplikasi web dan perintah Artisan secara otomatis dialihkan ke koneksi database demo mandiri (`database/demo.sqlite`). Database master operasional (`database.sqlite`) dilindungi sepenuhnya dan tidak tersentuh.
2. Jika server VPS difungsikan sebagai instance showcase demo publik, setel di `.env`:
   ```ini
   DEMO_MODE=true
   ```
3. Eksekusi pengisian data skenario demo ke database demo terisolasi:
   ```bash
   php artisan scheduler:demo-data
   ```
4. Reset atau pembersihan data demo kapan saja:
   ```bash
   php artisan scheduler:demo-data --clean
   ```
*(Seluruh 9 skenario demo berjalan di dalam database terisolasi, tanpa mengubah data master operasional).*

---

## 5. Integritas Database, Migrasi, & Backup

### A. Aturan Migrasi Non-Destruktif
1. **DILARANG** menjalankan `php artisan migrate:fresh` atau `php artisan migrate:refresh` pada server produksi karena akan menghapus seluruh data jadwal dan riwayat persetujuan!
2. Gunakan perintah migrasi non-destruktif standar:
   ```bash
   php artisan migrate --force
   ```
3. Seluruh migrasi PALCOM AI Scheduler dirancang menggunakan `constrained()`, `cascadeOnDelete()`, dan nullable foreign keys yang aman tanpa merusak integritas referensial.

### B. Mekanisme Backup Database Online yang Konsisten
Skrip otomatis tersedia di [`deploy/scripts/backup-database.sh`](deploy/scripts/backup-database.sh).

> [!IMPORTANT]
> **Konsistensi SQLite Snapshot:**
> Menyalin file mentah (`cp`) pada database SQLite aktif dengan mode WAL berisiko membaca *torn pages* atau menghasilkan snapshot korup. Skrip backup menggunakan **SQLite Online Backup API** (melalui `sqlite3 .backup` atau ekstensi PHP `SQLite3::backup()`) untuk menjamin snapshot point-in-time yang konsisten tanpa mengunci database operasional.
> Jika snapshot gagal atau menghasilkan file kosong, skrip keluar dengan kode error (exit 1) dan proses deployment segera dihentikan demi keamanan data.

Pasang cron job backup harian:
```bash
# Buka crontab
crontab -e

# Tambahkan backup otomatis setiap hari jam 02:00 dini hari
0 2 * * * /bin/bash /var/www/PalcomAIScheduler/deploy/scripts/backup-database.sh >> /var/log/scheduler-backup.log 2>&1
```

### C. Skrip Pemulihan (*Disaster Recovery*)
Skrip restore cepat tersedia di [`deploy/scripts/restore-database.sh`](deploy/scripts/restore-database.sh):
```bash
# Menjalankan pemulihan dari arsip backup
sudo bash deploy/scripts/restore-database.sh /var/backups/palcom-ai-scheduler/scheduler_sqlite_20261011_020000.sqlite.gz
```

---

## 6. Alur Deployment Terkelola (*Graceful Deployment Runbook*)

Skrip deployment produksi satu langkah tersedia di [`deploy/scripts/deploy.sh`](deploy/scripts/deploy.sh):

```bash
# Menjalankan deployment rilis baru
cd /var/www/PalcomAIScheduler
bash deploy/scripts/deploy.sh
```

### Prinsip Keamanan `deploy.sh`:
1. **Graceful Maintenance Mode:** Mengaktifkan maintenance mode terkelola (`php artisan down --retry=60`) selama proses migrasi dan pembaruan aset, menghindari klaim keliru *zero-downtime*.
2. **Penanganan Kegagalan Otomatis (Fail-Safe Trap):** Jika terjadi kegagalan pada langkah mana pun (`set -e` / trap `ERR`), skrip secara otomatis menjalankan `php artisan up` agar aplikasi tidak tertinggal offline dalam status maintenance mode.
3. **Backup Online Wajib:** Melakukan backup SQLite yang konsisten sebelum migrasi. *Jika backup gagal, deployment langsung dibatalkan sebelum perubahan apa pun diterapkan.*
4. **Git Pull Non-Destruktif:** Mengambil commit terbaru secara fast-forward (`git pull --ff-only origin main`). Dilarang menggunakan `git reset --hard` yang dapat menghilangkan modifikasi lokal atau riwayat branch.
5. **Penyesuaian Izin Terarah (Targeted Permissions):** Hanya direktori `storage`, `bootstrap/cache`, dan file SQLite yang disesuaikan hak aksesnya ke `www-data`. Tidak melakukan `chown -R` sembarangan pada seluruh direktori root aplikasi.
6. **Smoke Test Otomatis:** Memverifikasi rute kesehatan `/up`, halaman utama `/`, redirect modul auth terproteksi (`302`), dan proteksi file sensitif (`.env` 403/404) via `deploy/scripts/smoke-test.sh`.

---

## 7. Prosedur Rollback (*Emergency Rollback*)

Jika rilis mengalami kegagalan kritis setelah deploy:
```bash
# Jalankan skrip rollback otomatis (opsional: sertakan commit target / tag release)
bash deploy/scripts/rollback.sh
# Atau rollback ke commit tertentu:
bash deploy/scripts/rollback.sh <commit-sha-atau-tag>
```

Langkah manual rollback:
1. `php artisan down`
2. `git checkout <commit-sebelumnya>` (kembali ke versi stabil)
3. `composer install --no-dev --optimize-autoloader`
4. `npm run build`
5. Jika ada migrasi yang harus dibatalkan: `php artisan migrate:rollback --step=1 --force` atau pulihkan snapshot DB via `restore-database.sh`.
6. `php artisan optimize:clear && php artisan optimize`
7. `php artisan up`

---

## 8. Pemantauan & Operasional Pasca-Deploy (*Monitoring*)

### A. Pengecekan Log Laravel
```bash
# Monitor log secara real-time
tail -f storage/logs/laravel.log

# Cari error atau exception
grep -i "error" storage/logs/laravel.log | tail -n 20
```

### B. Service Queue Worker (Systemd)
File unit service systemd tersedia di [`deploy/systemd/palcom-scheduler-queue.service`](deploy/systemd/palcom-scheduler-queue.service).
```bash
# Pasang unit service
sudo cp deploy/systemd/palcom-scheduler-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now palcom-scheduler-queue

# Periksa status
sudo systemctl status palcom-scheduler-queue
```

### C. Smoke Test Lokal & Remote
```bash
# Uji kesehatan aplikasi
bash deploy/scripts/smoke-test.sh http://127.0.0.1:8000
```

---

## 9. Daftar Akses yang Dibutuhkan dari Penyedia Server (*Access Checklist*)

Sebelum melakukan eksekusi deployment langsung ke server VPS produksi, berikut daftar akses dan parameter yang perlu disiapkan oleh Project Leader / Administrator Server:

| Kebutuhan Akses | Keterangan / Format | Status Kesiapan |
|---|---|---|
| **Alamat IP / Host VPS** | IPv4 publik (contoh: `103.xxx.xxx.xxx`) | Menunggu konfirmasi |
| **Akses SSH** | User deploy (sudo non-root) + SSH Public Key | Menunggu konfirmasi |
| **Domain / Subdomain** | DNS A-Record mengarah ke IP VPS (contoh: `palcomaischeduler.web.id`) | Menunggu konfirmasi |
| **Kunci API Gemini** | Production Google Gemini API Key (`AIza...`) | Siap dimasukkan ke `.env` |
| **Port Firewall VPS** | Inbound port 22 (SSH), 80 (HTTP), 443 (HTTPS) terbuka | Siap dikonfigurasi |

*(Catatan Keamanan: Seluruh kredensial dan kata sandi server tidak boleh dicatat dalam repositori Git).*
