<?php

use App\Models\User;

test('guest cannot open the instructor create preview', function () {
    $this->get(route('instructors.create'))->assertRedirect(route('login'));
});

test('authenticated user can review instructor create UI without a save action', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('instructors.create'))
        ->assertOk()
        ->assertSee('Tambah Instruktur')
        ->assertSee('Kompetensi Mengajar')
        ->assertSee('Ketersediaan Mengajar')
        ->assertSee('Pratinjau frontend')
        ->assertSee('disabled aria-disabled="true"', false);
});
