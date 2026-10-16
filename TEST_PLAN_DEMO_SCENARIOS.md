# Test Plan Demo Scenarios B2-02

## Ruang Lingkup Integrasi

Integrasi B2-02 menggunakan `PalcomDemoSeeder` sebagai satu-satunya sumber dataset demo SCHEDORA AI V2. `DemoScenarioSeeder` dari branch sumber tidak diintegrasikan karena sembilan skenario lamanya tumpang tindih dengan dua belas skenario V2 dan bergantung pada bentuk data D1 lama.

Command yang tersedia:

```bash
php artisan scheduler:demo-data
```

Command selalu menargetkan koneksi `demo`, yaitu file SQLite `database/demo.sqlite`. Koneksi default aplikasi tidak diubah secara permanen.

## Kontrol Keamanan

- Command menolak seluruh eksekusi pada environment `production`.
- Driver koneksi demo wajib SQLite.
- Target database demo tidak boleh sama dengan database SQLite operasional.
- Migrasi dan seeding hanya dijalankan pada koneksi `demo`.
- Tidak ada opsi pemilihan koneksi arbitrer atau penghapusan data.
- Dataset V2 bersifat idempoten dan menggunakan business key yang sudah diuji.

## Pengujian Terfokus

`tests/Feature/DemoScenarioTest.php` memverifikasi:

1. dataset dibuat pada SQLite demo terisolasi tanpa mengubah database test utama;
2. dua eksekusi menghasilkan jumlah data yang sama;
3. target yang sama dengan database operasional ditolak;
4. production ditolak sebelum file database demo dibuat.

`tests/Feature/PalcomDemoSeederTest.php` tetap menjadi sumber verifikasi detail dua belas skenario, relasi, idempotensi mendalam, dan larangan seeding pada production.
