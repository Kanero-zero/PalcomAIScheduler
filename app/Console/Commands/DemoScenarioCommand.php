<?php

namespace App\Console\Commands;

use Database\Seeders\PalcomDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DemoScenarioCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheduler:demo-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menyiapkan 12 skenario demo SCHEDORA AI pada database SQLite terisolasi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah scheduler:demo-data dilarang dijalankan pada lingkungan production.');

            return self::FAILURE;
        }

        $demoConnection = config('database.connections.demo');
        $demoDatabase = is_array($demoConnection) ? ($demoConnection['database'] ?? null) : null;

        if (($demoConnection['driver'] ?? null) !== 'sqlite' || ! is_string($demoDatabase) || $demoDatabase === '') {
            $this->error('Koneksi demo wajib menggunakan database SQLite terisolasi.');

            return self::FAILURE;
        }

        if ($this->usesOperationalDatabase($demoDatabase)) {
            $this->error('Target database demo sama dengan database operasional. Eksekusi dibatalkan.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($demoDatabase));

        if (! File::exists($demoDatabase)) {
            File::put($demoDatabase, '');
        }

        $originalConnection = DB::getDefaultConnection();

        try {
            config(['database.default' => 'demo']);
            DB::purge('demo');
            DB::setDefaultConnection('demo');

            $migrationExitCode = $this->call('migrate', [
                '--database' => 'demo',
                '--force' => true,
            ]);

            if ($migrationExitCode !== self::SUCCESS) {
                $this->error('Migrasi database demo gagal.');

                return self::FAILURE;
            }

            $seedExitCode = $this->call('db:seed', [
                '--class' => PalcomDemoSeeder::class,
                '--database' => 'demo',
                '--force' => true,
            ]);

            if ($seedExitCode !== self::SUCCESS) {
                $this->error('Pembuatan skenario demo gagal.');

                return self::FAILURE;
            }

            $this->info('Dua belas skenario demo SCHEDORA AI siap pada database SQLite terisolasi.');

            return self::SUCCESS;
        } finally {
            config(['database.default' => $originalConnection]);
            DB::purge('demo');
            DB::setDefaultConnection($originalConnection);
        }
    }

    private function usesOperationalDatabase(string $demoDatabase): bool
    {
        $operationalConnection = DB::getDefaultConnection();

        if (config("database.connections.{$operationalConnection}.driver") !== 'sqlite') {
            return false;
        }

        $operationalDatabase = config("database.connections.{$operationalConnection}.database");

        return is_string($operationalDatabase)
            && $this->normalizeDatabasePath($demoDatabase) === $this->normalizeDatabasePath($operationalDatabase);
    }

    private function normalizeDatabasePath(string $database): string
    {
        if ($database === ':memory:') {
            return $database;
        }

        $directory = realpath(dirname($database)) ?: dirname($database);

        return mb_strtolower(str_replace('\\', '/', $directory.'/'.basename($database)));
    }
}
