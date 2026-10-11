<?php

use App\Livewire\InstructorList;
use App\Models\Instructor;
use App\Models\User;
use Livewire\Livewire;

it('requires login to view instructors', function () {
    $this->get(route('instructors.index'))->assertRedirect(route('login'));
});

it('requires email verification to view instructors', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('instructors.index'))->assertRedirect(route('verification.notice'));
});

it('shows instructors with skills and competency levels', function () {
    $instructor = Instructor::factory()->create(['name' => 'Wahyu Saputra', 'status' => 'active']);
    $instructor->skills()->create(['skill' => 'Microsoft Excel', 'level' => 'advanced']);
    $this->actingAs(User::factory()->create())
        ->get(route('instructors.index'))
        ->assertOk()
        ->assertSeeLivewire(InstructorList::class)
        ->assertSee('Wahyu Saputra')
        ->assertSee('Microsoft Excel')
        ->assertSee('Mahir')
        ->assertSee('Aktif')
        ->assertSee('Tampilan hanya baca')
        ->assertDontSee('Tampilan daftar instruktur belum tersedia.');
});

it('can search by instructor name and teaching skill', function () {
    $wahyu = Instructor::factory()->create(['name' => 'Wahyu Saputra']);
    $wahyu->skills()->create(['skill' => 'Microsoft Excel', 'level' => 'advanced']);
    $kanero = Instructor::factory()->create(['name' => 'Kanero Juniar']);
    $kanero->skills()->create(['skill' => 'Web Programming', 'level' => 'intermediate']);
    $this->actingAs(User::factory()->create());

    Livewire::test(InstructorList::class)
        ->set('search', 'Saputra')->assertSee('Wahyu Saputra')->assertDontSee('Kanero Juniar')
        ->set('search', 'Programming')->assertSee('Kanero Juniar')->assertDontSee('Wahyu Saputra');
});

it('filters instructors by status from database', function () {
    Instructor::factory()->create(['name' => 'Instruktur Aktif', 'status' => 'active']);
    Instructor::factory()->create(['name' => 'Instruktur Nonaktif', 'status' => 'inactive']);
    $this->actingAs(User::factory()->create());

    Livewire::test(InstructorList::class)
        ->assertSee('Nonaktif')
        ->set('status', 'inactive')
        ->assertSee('Instruktur Nonaktif')
        ->assertDontSee('Instruktur Aktif');
});

it('combines instructor search and status filters then resets them', function () {
    Instructor::factory()->create(['name' => 'Wahyu Aktif', 'status' => 'active']);
    Instructor::factory()->create(['name' => 'Wahyu Nonaktif', 'status' => 'inactive']);
    $this->actingAs(User::factory()->create());

    Livewire::test(InstructorList::class)
        ->set('search', 'Wahyu')
        ->set('status', 'inactive')
        ->assertSee('Wahyu Nonaktif')->assertDontSee('Wahyu Aktif')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('status', '')
        ->assertSee('Wahyu Aktif');
});

it('paginates instructor results and returns to page one after a search', function () {
    foreach (range(1, 11) as $number) {
        Instructor::factory()->create(['name' => sprintf('Instruktur %02d', $number)]);
    }
    $this->actingAs(User::factory()->create());

    Livewire::test(InstructorList::class)
        ->assertSee('Instruktur 01')->assertDontSee('Instruktur 11')
        ->call('nextPage')->assertSee('Instruktur 11')->assertDontSee('Instruktur 01')
        ->set('search', 'Instruktur 01')
        ->assertSee('Instruktur 01')->assertDontSee('Instruktur 11');
});

it('shows empty states for instructors with no data or no matching results', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(InstructorList::class)->assertSee('Belum ada data instruktur.');

    Instructor::factory()->create(['name' => 'Wahyu']);
    Livewire::test(InstructorList::class)
        ->set('search', 'Tidak Ditemukan')
        ->assertSee('Tidak ada instruktur yang sesuai dengan filter.');
});

it('shows an instructor without a recorded competency', function () {
    Instructor::factory()->create(['name' => 'Instruktur Baru']);
    $this->actingAs(User::factory()->create());

    Livewire::test(InstructorList::class)
        ->assertSee('Instruktur Baru')
        ->assertSee('Kompetensi belum tercatat');
});

it('reflects a competency added after the page is loaded', function () {
    $instructor = Instructor::factory()->create(['name' => 'Instruktur Evaluasi']);
    $this->actingAs(User::factory()->create());

    Livewire::test(InstructorList::class)->assertDontSee('Desain Grafis');
    $instructor->skills()->create(['skill' => 'Desain Grafis', 'level' => 'beginner']);
    Livewire::test(InstructorList::class)->assertSee('Desain Grafis')->assertSee('Pemula');
});

it('does not expose instructor mutation controls', function () {
    Instructor::factory()->create();
    $this->actingAs(User::factory()->create())
        ->get(route('instructors.index'))
        ->assertOk()
        ->assertDontSee('Tambah Instruktur')
        ->assertDontSee('Hapus Instruktur');
});
