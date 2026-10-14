<?php

use App\Models\Instructor;
use App\Models\User;

test('authenticated user sees instructor list columns and unavailable backend fields truthfully', function () {
    $this->actingAs(User::factory()->create());
    Instructor::factory()->create(['name' => 'Instruktur Uji Tampilan', 'status' => 'active']);

    $this->get(route('instructors.index'))
        ->assertOk()
        ->assertSee('Instruktur Uji Tampilan')
        ->assertSee('Bidang')
        ->assertSee('Ketersediaan')
        ->assertSee('Belum tercatat')
        ->assertSee('Filter aktif setelah bidang tersimpan di backend.')
        ->assertSee('Lihat Detail')
        ->assertSee('Tambah Instruktur');
});

test('instructor detail preview links to existing edit form without deleting records', function () {
    $this->actingAs(User::factory()->create());
    $instructor = Instructor::factory()->create(['name' => 'Instruktur Detail Uji', 'status' => 'inactive']);

    $this->get(route('instructors.index'))
        ->assertOk()
        ->assertSee('Detail Instruktur — Instruktur Detail Uji')
        ->assertSee('Nonaktif')
        ->assertSee(route('instructors.edit', $instructor))
        ->assertDontSee('Hapus Instruktur');
});
