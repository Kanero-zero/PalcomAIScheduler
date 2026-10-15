<?php

use App\Models\Instructor;
use App\Models\User;

test('authenticated admin user sees instructor list columns and legacy fields truthfully', function () {
    $this->actingAs(User::factory()->admin()->create());
    Instructor::factory()->create(['name' => 'Instruktur Uji Tampilan', 'status' => 'active']);

    $this->get(route('instructors.index'))
        ->assertOk()
        ->assertSee('Instruktur Uji Tampilan')
        ->assertSee('Bidang')
        ->assertSee('Ketersediaan')
        ->assertSee('Belum tercatat')
        ->assertSee('Lihat Detail')
        ->assertSee('Tambah Instruktur');
});

test('unauthorized staff user can view instructor list but cannot see create or edit buttons', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']));
    $instructor = Instructor::factory()->create(['name' => 'Instruktur Tampil Staff', 'status' => 'active']);

    $this->get(route('instructors.index'))
        ->assertOk()
        ->assertSee('Instruktur Tampil Staff')
        ->assertDontSee('+ Tambah Instruktur')
        ->assertDontSee(route('instructors.edit', $instructor));
});

test('instructor detail preview links to existing edit form for admin without deleting records', function () {
    $this->actingAs(User::factory()->admin()->create());
    $instructor = Instructor::factory()->create(['name' => 'Instruktur Detail Uji', 'status' => 'inactive']);

    $this->get(route('instructors.index'))
        ->assertOk()
        ->assertSee('Detail Instruktur — Instruktur Detail Uji')
        ->assertSee('Nonaktif')
        ->assertSee(route('instructors.edit', $instructor))
        ->assertDontSee('Hapus Instruktur');
});
