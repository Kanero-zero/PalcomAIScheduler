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
sudo certbot --nginx -d scheduler.palcomtech.ac.id
```

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
APP_URL=https://scheduler.palcomtech.ac.id
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
ALLOW_DEMO_DATA=false
```

### B. Zero-Leakage Checklist
- [x] Pastikan `APP_DEBUG=false` untuk mencegah kebocoran stack trace dan kredensial database saat terjadi unhandled exception.
- [x] Pastikan `GEMINI_API_KEY` hanya disetel pada `.env` server atau injected secrets CI/CD; dilarang keras ditulis di kode atau di-commit ke repositori.
- [x] Sesi cookie disetel `SESSION_SECURE_COOKIE=true` agar hanya dikirim melalui koneksi HTTPS.

### C. Menjalankan Data Demo pada Showcase Publik (Proteksi Ganda)
Untuk kebutuhan penilaian juri pada website publik:
1. Secara default, `php artisan scheduler:demo-data` diblokir total pada `APP_ENV=production`.
2. Jika server VPS difungsikan sebagai instance showcase demo publik, setel di `.env`:
   ```ini
   ALLOW_DEMO_DATA=true
   ```
3. Eksekusi data demo dengan flag konfirmasi eksplisit:
   ```bash
   php artisan scheduler:demo-data --force-demo
   ```
4. Reset atau pembersihan data demo kapan saja:
   ```bash
   php artisan scheduler:demo-data --clean --force-demo
   ```
*(Seluruh 9 skenario demo diisolasi dengan tag `[DEMO]` dan tanggal referensi `2026-10-25`, tanpa menggunakan `migrate:fresh`).*

---

## 5. Integritas Database, Migrasi, & Backup

### A. Aturan Migrasi Non-Destruktif
1. **DILARANG** menjalankan `php artisan migrate:fresh` atau `php artisan migrate:refresh` pada server produksi karena akan menghapus seluruh data jadwal dan riwayat persetujuan!
2. Gunakan perintah migrasi non-destruktif standar:
   ```bash
   php artisan migrate --force
   ```
3. Seluruh migrasi PALCOM AI Scheduler dirancang menggunakan `constrained()`, `cascadeOnDelete()`, dan nullable foreign keys yang aman tanpa merusak integritas referensial.

### B. Skrip Backup Otomatis
Skrip otomatis tersedia di [`deploy/scripts/backup-database.sh`](deploy/scripts/backup-database.sh).
Skrip ini mendukung backup SQLite dan MySQL secara otomatis dengan kompresi gzip dan retensi 14 hari.

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

## 6. Alur Deployment Otomatis (*Deployment Runbook*)

Skrip deployment produksi satu langkah tersedia di [`deploy/scripts/deploy.sh`](deploy/scripts/deploy.sh):

```bash
# Menjalankan deployment rilis baru
cd /var/www/PalcomAIScheduler
bash deploy/scripts/deploy.sh
```

### Tahapan yang Dijalankan oleh `deploy.sh`:
1. **Pre-flight Check:** Memastikan PHP 8.3, Composer, Node.js, dan Git tersedia.
2. **Maintenance Mode:** Mengaktifkan maintenance mode ramah pengguna (`php artisan down --retry=60`).
3. **Backup Otomatis Wajib:** Membuat snapshot database sebelum migrasi. *Jika backup gagal, deployment langsung dibatalkan (exit 1).*
4. **Git Pull Aman:** Mengambil commit terbaru dari branch target secara fast-forward (`git pull --ff-only origin main`) tanpa `git reset --hard`.
5. **Composer Install:** Memasang paket PHP produksi (`composer install --no-dev --optimize-autoloader --no-interaction`).
6. **Frontend Build:** Mengompilasi CSS & JS (`npm ci && npm run build`).
7. **Database Migration:** Menjalankan migrasi baru (`php artisan migrate --force`).
8. **Cache Optimization:** Mengoptimalkan performa Laravel:
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
9. **Queue Restart:** Memuat ulang antrean worker (`php artisan queue:restart`).
10. **Permission Fix:** Menjamin file storage dan SQLite database di `/var/lib/palcom-ai-scheduler/database.sqlite` dapat ditulis oleh `www-data`.
11. **Maintenance Mode Up:** Membuka kembali aplikasi (`php artisan up`).
12. **Smoke Test:** Menjalankan verifikasi otomatis endpoint HTTP 200 via `deploy/scripts/smoke-test.sh`.

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
| **Domain / Subdomain** | DNS A-Record mengarah ke IP VPS (contoh: `scheduler.palcomtech.ac.id`) | Menunggu konfirmasi |
| **Kunci API Gemini** | Production Google Gemini API Key (`AIza...`) | Siap dimasukkan ke `.env` |
| **Port Firewall VPS** | Inbound port 22 (SSH), 80 (HTTP), 443 (HTTPS) terbuka | Siap dikonfigurasi |

*(Catatan Keamanan: Seluruh kredensial dan kata sandi server tidak boleh dicatat dalam repositori Git).*
