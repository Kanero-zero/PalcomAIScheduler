<?php

use App\Models\User;

test('guest cannot open the instructor create preview', function () {
    $this->get(route('instructors.create'))->assertRedirect(route('login'));
});

test('unauthorized staff cannot open instructor create form', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('instructors.create'))
        ->assertForbidden();
});

test('authorized admin can review and access functional instructor create form', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('instructors.create'))
        ->assertOk()
        ->assertSee('Tambah Instruktur')
        ->assertSee('Kompetensi Mengajar')
        ->assertSee('Ketersediaan Mengajar')
        ->assertSee('Simpan Instruktur')
        ->assertDontSee('disabled aria-disabled="true"', false);
});
