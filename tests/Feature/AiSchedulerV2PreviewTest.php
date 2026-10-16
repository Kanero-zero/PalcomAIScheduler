<?php

use App\Models\User;

test('ai scheduler preview requires login and verified email', function () {
    $this->get(route('ai-scheduler.v2-preview'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertRedirect(route('verification.notice'));
});

test('ai scheduler preview exposes only the two approved tabs and exclusive input modes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertOk()
        ->assertSee('Buat Jadwal Kelas')
        ->assertSee('Pergantian Instruktur Izin')
        ->assertSee('AI Assistant')
        ->assertSee('Input Manual')
        ->assertSee('Cari Alternatif Jadwal')
        ->assertSee('UI PREVIEW')
        ->assertSee("inputMode === 'ai'", false)
        ->assertSee("inputMode === 'manual'", false)
        ->assertDontSee('Kelola Perubahan Jadwal');
});

test('instructor leave scheduler is embedded in the second tab without rewriting the component', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertOk()
        ->assertSee('Pilih Pengajuan Izin Instruktur')
        ->assertSee('Jalankan Engine')
        ->assertSee('Analisis dengan Gemini AI')
        ->assertSee('Tahap 1: Deterministik Aktif');
});

test('existing ai scheduler route remains available and functional', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Pilih Pengajuan Izin Instruktur');
});

test('preview offers an editable shared scheduling draft in assistant and manual input', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertOk()
        ->assertSee('Draft Kebutuhan Kelas')
        ->assertSee('Permintaan Anda')
        ->assertSee('Kirim ke AI')
        ->assertSee('Lengkapi melalui Input Manual')
        ->assertSee('Program / Materi / Level')
        ->assertSee('Jumlah Pertemuan')
        ->assertSee('Frekuensi per Minggu')
        ->assertSee('Preferensi Hari')
        ->assertSee('Instruktur (Opsional)')
        ->assertSee('Ruangan (Opsional)')
        ->assertSee('Fasilitas yang Dibutuhkan')
        ->assertSee('Periksa Kelengkapan Draft')
        ->assertSee('x-model="draft.ai_prompt"', false)
        ->assertSee('x-model="draft.title"', false)
        ->assertSee('x-model="draft.department"', false)
        ->assertSee('x-model="draft.preferred_weekdays"', false);
});

test('v2 scheduler preview keeps backend scheduling and approval unavailable', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertOk()
        ->assertSee('Mode Preview — AI belum terhubung')
        ->assertSee('Ini bukan hasil analisis AI atau pemeriksaan bentrok jadwal.')
        ->assertSee('Kirim ke AI')
        ->assertSee('Cari Alternatif Jadwal')
        ->assertSee('disabled aria-disabled="true"', false);
});

test('v2 preview offers illustrative A B C options, detail, and final review without persistence', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertOk()
        ->assertSee('Ringkasan Kebutuhan Kelas')
        ->assertSee('Lihat Contoh Rekomendasi')
        ->assertSee('SIMULASI TAMPILAN — BUKAN REKOMENDASI AI.')
        ->assertSee('Opsi A')
        ->assertSee('Opsi B')
        ->assertSee('Opsi C')
        ->assertSee('Lihat Detail')
        ->assertSee('Kalender Pertemuan')
        ->assertSee('Daftar Seluruh Pertemuan')
        ->assertSee('Pilih Opsi')
        ->assertSee('Tinjauan Akhir Pengajuan')
        ->assertSee('Alur Persetujuan AO')
        ->assertSee('Ajukan ke BAAK')
        ->assertSee('previewExampleOptions()', false)
        ->assertSee('chooseExample(option.id)', false);
});

test('v2 example preview cannot submit official approval or alter existing leave component', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertOk()
        ->assertSee('Ajukan ke BAAK')
        ->assertSee('disabled aria-disabled="true"', false)
        ->assertSee('Tidak ada pengajuan tersimpan')
        ->assertSee('Pilih Pengajuan Izin Instruktur');

    expect(file_get_contents(resource_path('views/pages/ai-scheduler-v2-preview.blade.php')))
        ->toContain('<livewire:ai-scheduler />');
});

test('v2 preview finalizes responsive option grid and clear steps without enabling persistence', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertOk()
        ->assertSee('id="scheduler-example-options"', false)
        ->assertSee('grid-template-columns: repeat(3, minmax(0, 1fr))', false)
        ->assertSee('grid-template-columns: minmax(0, 1fr)', false)
        ->assertSee('01&nbsp; Kebutuhan Kelas', false)
        ->assertSee('02&nbsp; Rekomendasi', false)
        ->assertSee('03&nbsp; Tinjauan Akhir', false)
        ->assertSee('formatPeriod(draft.start_date, draft.end_date)', false)
        ->assertSee('Lihat Detail')
        ->assertSee('Pilih Opsi')
        ->assertSee('disabled aria-disabled="true"', false)
        ->assertSee('Ajukan ke BAAK');
});

test('v2 final polish keeps legacy leave scheduler embedded and official route unchanged', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertOk()
        ->assertSee('Pilih Pengajuan Izin Instruktur')
        ->assertSee('Pergantian Instruktur Izin')
        ->assertSee('Alur Persetujuan AO');

    $this->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Jalankan Engine');
});
