<?php

use App\Models\User;

test('approval review v2 preview requires login and verified email', function () {
    $this->get(route('approval-review.v2-preview'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('approval-review.v2-preview'))
        ->assertRedirect(route('verification.notice'));
});

test('approval review v2 preview exposes exactly two approved tabs', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('approval-review.v2-preview'))
        ->assertOk()
        ->assertSee('Pengajuan Jadwal Baru')
        ->assertSee('Pengajuan Izin Instruktur')
        ->assertSee('UI PREVIEW')
        ->assertSee("activeTab: 'new'", false)
        ->assertSee("activeTab = 'leave'", false)
        ->assertDontSee('>Semua Pengajuan<', false);

    $source = file_get_contents(resource_path('views/pages/approval-review-v2-preview.blade.php'));
    expect(substr_count($source, 'role="tab"'))->toBe(2)
        ->and(substr_count($source, 'role="tabpanel"'))->toBe(2);
});

test('new class proposal approval tab is an honest non-persistent preview', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('approval-review.v2-preview'))
        ->assertOk()
        ->assertSee('Belum terhubung ke backend V2')
        ->assertSee('DATA SIMULASI — BUKAN PENGAJUAN ASLI.')
        ->assertSee('Filter dan pencarian hanya menyaring lima kartu simulasi')
        ->assertSee('Tidak ada persetujuan, penolakan, perubahan status, atau reservasi jadwal');
});

test('legacy instructor leave approval is embedded and original route remains available', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('approval-review.v2-preview'))
        ->assertOk()
        ->assertSee('Pilih Pengajuan Izin')
        ->assertSee('Pilih Instruktur Pengganti');

    $this->get(route('approval-review'))
        ->assertOk()
        ->assertSee('Pilih Pengajuan Izin');

    expect(file_get_contents(resource_path('views/pages/approval-review-v2-preview.blade.php')))
        ->toContain('<livewire:approval-review />');
});

test('approval v2 example queue has local filters and no database bindings for new proposals', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('approval-review.v2-preview'))
        ->assertOk()
        ->assertSee('Semua status')
        ->assertSee('Semua bidang')
        ->assertSee('Lihat Contoh Detail')
        ->assertSee('Reset Filter');

    $source = file_get_contents(resource_path('views/pages/approval-review-v2-preview.blade.php'));
    expect($source)->toContain('SIM-001', 'SIM-005', 'get filteredProposals()', "filterStatus: 'all'", "filterDepartment: 'all'")
        ->and($source)->not->toContain('ScheduleProposal::', 'wire:click="approve');
});

test('new proposal detail previews all simulated meetings and leaves BAAK writes disabled', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('approval-review.v2-preview'))
        ->assertOk()
        ->assertSee('Daftar Seluruh Pertemuan')
        ->assertSee('Riwayat Keputusan (Simulasi)')
        ->assertSee('Belum diperiksa oleh backend')
        ->assertSee('Pratinjau Setujui')
        ->assertSee('Pratinjau Tolak')
        ->assertSee('Pratinjau Minta Revisi')
        ->assertSee('Konfirmasi Keputusan — Belum Aktif');

    $source = file_get_contents(resource_path('views/pages/approval-review-v2-preview.blade.php'));
    expect($source)->toContain('exampleMeetings(proposal)', 'exampleHistory(proposal)', 'disabled aria-disabled="true"')
        ->and($source)->not->toContain('wire:click="approveProposal', 'wire:click="rejectProposal', 'ScheduleProposal::');
});

test('approval v2 final UI remains responsive and keeps the legacy route untouched', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('approval-review.v2-preview'))
        ->assertOk()
        ->assertSee('Alur setelah backend siap:')
        ->assertSee('Hak akses BAAK akan diperiksa backend saat integrasi.')
        ->assertSee('status proposal', false)
        ->assertSee('approval-v2-detail', false)
        ->assertSee('min-w-[640px]', false)
        ->assertSee('x-cloak', false);

    $source = file_get_contents(resource_path('views/pages/approval-review-v2-preview.blade.php'));
    expect(substr_count($source, 'role="tab"'))->toBe(2)
        ->and(substr_count($source, 'role="tabpanel"'))->toBe(2)
        ->and(substr_count($source, '<livewire:approval-review />'))->toBe(1)
        ->and($source)->toContain('Konfirmasi Keputusan — Belum Aktif', 'disabled aria-disabled="true"');

    $routes = file_get_contents(base_path('routes/web.php'));
    expect($routes)->toContain("Route::view('approval-review', 'pages.approval-review')->name('approval-review');")
        ->and($routes)->toContain("Route::view('approval-review/v2-preview', 'pages.approval-review-v2-preview')->name('approval-review.v2-preview');");
});
