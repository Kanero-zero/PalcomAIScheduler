<?php

namespace App\Console\Commands;

use Database\Seeders\DemoScenarioSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoScenarioCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheduler:demo-data {--clean : Bersihkan data demo secara aman tanpa menyentuh data operasional} {--isolated : Jalankan secara eksplisit pada database SQLite terisolasi (database/demo.sqlite)} {--database= : Gunakan nama koneksi database tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menyiapkan atau membersihkan 9 skenario demonstrasi pada database terisolasi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 1. Tentukan koneksi target:
        $connection = $this->option('database');

        if ($this->option('isolated')) {
            $connection = 'demo';
        }

        // 2. Proteksi Lingkungan Produksi: Pastikan DEMO_MODE aktif jika di lingkungan produksi
        if (app()->environment('production')) {
            $isDemoMode = (bool) env('DEMO_MODE', false);
            if (! $isDemoMode) {
                $this->error('PERINGATAN KEAMANAN: Mode demo (DEMO_MODE=true) tidak aktif di server produksi.');
                $this->line('<fg=yellow>Perintah ini hanya dapat dijalankan jika DEMO_MODE=true untuk menjamin isolasi database fisik.</>');

                return self::FAILURE;
            }

            // Di production, selalu paksa koneksi 'demo' (demo.sqlite) demi isolasi total
            $connection = 'demo';
        }

        // 3. Verifikasi Keamanan Tingkat Keras: DILARANG KERAS menulis ke file database operasional
        if ($connection) {
            $targetDbFile = config("database.connections.{$connection}.database") ?? database_path('demo.sqlite');
            $operationalDbFile = config('database.connections.sqlite.database');

            if (! app()->environment('testing') && realpath($targetDbFile) && realpath($operationalDbFile) && realpath($targetDbFile) === realpath($operationalDbFile)) {
                $this->error('PERINGATAN KRITIS: Target database terdeteksi sebagai database operasional.');
                $this->error('Perintah scheduler:demo-data DILARANG KERAS mengeksekusi atau membersihkan database operasional demi integritas data riil!');

                return self::FAILURE;
            }
        }

        // 4. Pastikan file database terisolasi tersedia dan termigrasi
        if ($connection === 'demo') {
            $demoDbPath = config('database.connections.demo.database', database_path('demo.sqlite'));
            if (! file_exists($demoDbPath)) {
                if (! file_exists(dirname($demoDbPath))) {
                    mkdir(dirname($demoDbPath), 0775, true);
                }
                touch($demoDbPath);
            }

            $this->call('migrate', ['--database' => 'demo', '--force' => true]);
        }

        $originalDefault = config('database.default');

        try {
            if ($connection) {
                config(['database.default' => $connection]);
                DB::purge($connection);
                DB::setDefaultConnection($connection);
            }

            if ($this->option('clean')) {
                $this->warn('Membersihkan data skenario demonstrasi...');
                $deletedCount = DemoScenarioSeeder::clean();
                $this->info("Data demo berhasil dibersihkan secara aman ({$deletedCount} pengajuan izin demo beserta jadwal, substitusi, dan activity log terkait telah dihapus).");
                $this->line('<fg=gray>Data operasional pengguna tetap aman dan tidak mengalami perubahan.</>');

                return self::SUCCESS;
            }

            $this->info('================================================================');
            $this->info('     PENYIAP DATA SKENARIO DEMO — PALCOM AI SCHEDULER           ');
            $this->info('================================================================');

            $seeder = new DemoScenarioSeeder;
            $seeder->setCommand($this);
            $seeder->run();

            $this->newLine();
            $this->table(
                ['No', 'Skenario', 'Deskripsi / Kondisi', 'Status Kesiapan'],
                [
                    ['1', 'Izin Banyak Kelas Terdampak', 'Wahyu izin (13:00 - 18:00) pada 2 kelas: Excel Bisnis & Word Administrasi', 'Tersedia'],
                    ['2', 'Instruktur Pengganti Valid', 'Kanero memenuhi kualifikasi Advanced Excel & Word, jam mengajar kosong', 'Tersedia'],
                    ['3', 'Kandidat Tidak Kompeten', 'Dina Oktavia hanya memiliki keahlian Desain Grafis (didiskualifikasi)', 'Tersedia'],
                    ['4', 'Kandidat Bentrok Jadwal', 'Budi Santoso memiliki keahlian Excel namun sedang mengajar di Lab 2', 'Tersedia'],
                    ['5', 'Kendala Ruangan', 'Kelas Web di Lab 3 Mini: kapasitas kurang (15 < 30) & status maintenance', 'Tersedia'],
                    ['6', 'Izin Tanpa Kelas Terdampak', 'Rizky Pratama mengajukan izin namun tidak memiliki jadwal mengajar', 'Tersedia'],
                    ['7', 'Approval Berhasil', 'Persetujuan Kanero untuk kelas Excel tercatat di substitusi & activity log', 'Tersedia'],
                    ['8', 'Reject Beserta Alasan', 'Penolakan usulan pengganti dengan alasan beban SKS maksimum tercatat', 'Tersedia'],
                    ['9', 'Gemini Fallback', 'Log simulasi fallback AI terintegrasi otomatis ke hasil deterministik', 'Tersedia'],
                ]
            );

            $this->line('<fg=green>✓ Seluruh 9 skenario siap digunakan untuk presentasi juri maupun automated testing.</>');
            $this->line('<fg=gray>Untuk membersihkan kembali data demo kapan saja: php artisan scheduler:demo-data --clean</>');

            return self::SUCCESS;
        } finally {
            if ($connection && $originalDefault) {
                config(['database.default' => $originalDefault]);
                DB::purge($connection);
                DB::setDefaultConnection($originalDefault);
            }
        }
    }
}
