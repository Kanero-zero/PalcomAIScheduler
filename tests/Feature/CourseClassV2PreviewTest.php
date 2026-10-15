<?php

use App\Models\CourseClass;
use App\Models\User;

test('course class v2 preview displays approved navigation and leaves existing classes unchanged', function () {
    CourseClass::factory()->create([
        'name' => 'Kelas Legacy Asli',
        'subject' => 'Microsoft Excel',
        'student_count' => 7,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('course-classes.index'))
        ->assertOk()
        ->assertSee('Daftar Kelas')
        ->assertSee('Kurikulum Program')
        ->assertSee('Tambah Kelas')
        ->assertSee('Tambah Kurikulum')
        ->assertSee('Simpan Kelas')
        ->assertSee('Simpan Draft')
        ->assertSee('Terbitkan Kurikulum')
        ->assertSee('<button type="button" disabled title="Belum ada backend untuk menyimpan kelas" class="cursor-not-allowed rounded-xl bg-[#1565D8] px-5 py-2.5 text-sm font-semibold text-white opacity-40">Simpan Kelas</button>', false)
        ->assertSee('<button type="button" disabled class="cursor-not-allowed rounded-xl border border-blue-200 px-4 py-2.5 text-sm font-semibold text-[#1565D8] opacity-40">Simpan Draft</button>', false)
        ->assertSee('<button type="button" disabled class="cursor-not-allowed rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white opacity-40">Terbitkan Kurikulum</button>', false)
        ->assertSee('Kelas Legacy Asli')
        ->assertSee('Microsoft Excel')
        ->assertSee('Belum tercatat');

    expect(CourseClass::query()->count())->toBe(1);
});
