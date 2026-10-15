<?php

use App\Models\Instructor;
use App\Models\User;

test('guest cannot open instructor edit preview', function () {
    $instructor = Instructor::factory()->create();

    $this->get(route('instructors.edit', $instructor))->assertRedirect(route('login'));
});

test('authenticated user sees actual stored instructor values and disabled edit save', function () {
    $instructor = Instructor::factory()->create([
        'name' => 'Pengajar Excel Aktual',
        'status' => 'active',
    ]);
    $instructor->skills()->create(['skill' => 'Microsoft Excel', 'level' => 'advanced']);

    $this->actingAs(User::factory()->create())
        ->get(route('instructors.edit', $instructor))
        ->assertOk()
        ->assertSee('Edit Instruktur')
        ->assertSee('Pengajar Excel Aktual')
        ->assertSee('Microsoft Excel')
        ->assertSee('Kompetensi yang benar-benar tersimpan saat ini')
        ->assertSee('Simpan Perubahan')
        ->assertSee('disabled aria-disabled="true"', false);
});

test('nonexistent instructor edit route returns not found', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('instructors.edit', 99999999))
        ->assertNotFound();
});
