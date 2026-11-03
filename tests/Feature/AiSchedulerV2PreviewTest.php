<?php

use App\Models\User;

test('ai scheduler requires login and verified email', function () {
    $this->get(route('ai-scheduler'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('ai-scheduler'))
        ->assertRedirect(route('verification.notice'));
});

test('legacy preview URL preserves access checks and redirects verified users to the main scheduler', function () {
    $this->get(route('ai-scheduler.v2-preview'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler.v2-preview'))
        ->assertRedirect(route('ai-scheduler'));
});

test('main scheduler exposes only the two approved tabs and exclusive input modes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('AI Auto-Scheduler - SCHEDORA AI')
        ->assertSee('Buat Jadwal Kelas')
        ->assertSee('Pergantian Instruktur Izin')
        ->assertSee('AI Assistant')
        ->assertSee('Input Manual')
        ->assertSee('Cari Alternatif Jadwal Nyata')
        ->assertSee('id="scheduler-tab-create"', false)
        ->assertSee('@click="activeTab = \'create\'"', false)
        ->assertSee('id="scheduler-panel-create"', false)
        ->assertSee('id="scheduler-tab-leave"', false)
        ->assertSee('@click="activeTab = \'leave\'"', false)
        ->assertSee('id="scheduler-panel-leave"', false)
        ->assertSee("inputMode === 'ai'", false)
        ->assertSee("inputMode === 'manual'", false)
        ->assertDontSee('AI Auto-Scheduler Preview')
        ->assertDontSee('Kelola Perubahan Jadwal');
});

test('instructor leave scheduler is embedded in the second tab without rewriting the component', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Pilih Pengajuan Izin Instruktur')
        ->assertSee('Jalankan Engine')
        ->assertSee('Analisis dengan Gemini AI')
        ->assertSee('Tahap 1: Deterministik Aktif');
});

test('main scheduler route renders the V2 interface', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Draft Kebutuhan Kelas')
        ->assertSee('Pilih Pengajuan Izin Instruktur');
});

test('scheduler offers an editable shared scheduling draft in assistant and manual input', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Draft Kebutuhan Kelas')
        ->assertSee('Permintaan Anda')
        ->assertSee('Kirim ke Gemini AI')
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

test('v2 scheduler keeps browser-only validation warnings and unavailable selectors', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Pemeriksaan berikut hanya mengecek kelengkapan draft di browser')
        ->assertSee('Ini bukan hasil analisis AI atau pemeriksaan bentrok jadwal.')
        ->assertSee('Dipilih otomatis oleh engine dari data master')
        ->assertSee('Cari Alternatif Jadwal Nyata')
        ->assertSee('disabled aria-disabled="true"', false);
});

test('v2 scheduler provides engine options, detail, and final review before persistence', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Ringkasan Kebutuhan Kelas')
        ->assertSee('Semua opsi berasal dari pemeriksaan engine G3 terhadap database.')
        ->assertSee('Lihat Detail')
        ->assertSee('Kalender Pertemuan')
        ->assertSee('Daftar Seluruh Pertemuan')
        ->assertSee('Pilih Opsi')
        ->assertSee('Tinjauan Akhir Pengajuan')
        ->assertSee('Alur Persetujuan AO')
        ->assertSee('Ajukan sebagai Pending (AO)')
        ->assertSee('chooseExample(option.id)', false);
});

test('v2 scheduler keeps pending proposals separate from official approval and preserves the leave component', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Ajukan sebagai Pending (AO)')
        ->assertSee('Belum membuat jadwal resmi.')
        ->assertSee('Pilih Pengajuan Izin Instruktur');

    expect(file_get_contents(resource_path('views/pages/ai-scheduler-v2-preview.blade.php')))
        ->toContain('<livewire:ai-scheduler />');
});

test('v2 scheduler retains its responsive option grid and clear steps', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
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
        ->assertSee('Ajukan sebagai Pending (AO)');
});

test('v2 main page keeps the legacy leave scheduler embedded', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('Pilih Pengajuan Izin Instruktur')
        ->assertSee('Pergantian Instruktur Izin')
        ->assertSee('Alur Persetujuan AO')
        ->assertSee('Jalankan Engine');
});
