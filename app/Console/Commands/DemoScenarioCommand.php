<?php

namespace App\Console\Commands;

use Database\Seeders\DemoScenarioSeeder;
use Illuminate\Console\Command;

class DemoScenarioCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheduler:demo-data {--clean : Bersihkan data demo secara aman tanpa menyentuh data operasional}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menyiapkan atau membersihkan 9 skenario demonstrasi & pengujian PALCOM AI Scheduler';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('PERINGATAN: Perintah penyedia data demo DILARANG dijalankan di lingkungan produksi demi keamanan data!');

            return self::FAILURE;
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
    }
}
