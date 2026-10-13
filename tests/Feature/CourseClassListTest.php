<?php

use App\Livewire\CourseClassList;
use App\Models\CourseClass;
use App\Models\Schedule;
use App\Models\User;
use Livewire\Livewire;

test('the class list route requires login and verified email', function () {
    $this->get(route('course-classes.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('course-classes.index'))
        ->assertRedirect(route('verification.notice'));
});

test('the authenticated page renders the live database-backed class list', function () {
    CourseClass::factory()->create([
        'name' => 'Excel Administrasi Pagi',
        'subject' => 'Microsoft Excel',
        'student_count' => 18,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('course-classes.index'))
        ->assertOk()
        ->assertSeeLivewire(CourseClassList::class)
        ->assertSee('Excel Administrasi Pagi')
        ->assertSee('Microsoft Excel')
        ->assertDontSee('Tampilan daftar kelas belum tersedia.');
});

test('the list displays accurate class, enrollment, and schedule counts', function () {
    $firstClass = CourseClass::factory()->create([
        'name' => 'Kelas Word Dasar',
        'subject' => 'Microsoft Word',
        'student_count' => 12,
    ]);
    CourseClass::factory()->create([
        'name' => 'Kelas Excel Mahir',
        'subject' => 'Microsoft Excel',
        'student_count' => 23,
    ]);
    Schedule::factory()->count(2)->create(['course_class_id' => $firstClass->id]);

    Livewire::test(CourseClassList::class)
        ->assertViewHas('totalClasses', 2)
        ->assertViewHas('totalEnrollments', 35)
        ->assertSee('Kelas Word Dasar')
        ->assertSee('Kelas Excel Mahir')
        ->assertViewHas('classes', function ($classes) use ($firstClass) {
            return $classes->total() === 2
                && $classes->firstWhere('id', $firstClass->id)?->schedules_count === 2;
        });
});

test('users can search class names or subjects and filter by an exact subject', function () {
    CourseClass::factory()->create(['name' => 'Pagi Excel', 'subject' => 'Microsoft Excel']);
    CourseClass::factory()->create(['name' => 'Sore Word', 'subject' => 'Microsoft Word']);
    CourseClass::factory()->create(['name' => 'Kelas Grafis', 'subject' => 'Desain Grafis']);

    Livewire::test(CourseClassList::class)
        ->set('search', 'Pagi')
        ->assertSee('Pagi Excel')
        ->assertDontSee('Sore Word')
        ->set('search', 'Microsoft Word')
        ->assertSee('Sore Word')
        ->assertDontSee('Pagi Excel')
        ->set('search', '')
        ->set('subject', 'Desain Grafis')
        ->assertSee('Kelas Grafis')
        ->assertDontSee('Sore Word')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('subject', '')
        ->assertSee('Sore Word');
});

test('the list paginates ten classes and resets to the first page when searching', function () {
    for ($number = 1; $number <= 11; $number++) {
        CourseClass::factory()->create([
            'name' => sprintf('Kelas %02d', $number),
            'subject' => 'Microsoft Excel',
        ]);
    }

    Livewire::test(CourseClassList::class)
        ->assertSee('Kelas 01')
        ->assertDontSee('Kelas 11')
        ->call('nextPage')
        ->assertSee('Kelas 11')
        ->set('search', 'Kelas 01')
        ->assertSee('Kelas 01')
        ->assertDontSee('Kelas 11');
});

test('empty data and zero-match searches display distinct helpful messages', function () {
    Livewire::test(CourseClassList::class)
        ->assertSee('Belum ada data kelas kursus.');

    CourseClass::factory()->create(['name' => 'Kelas Excel Pagi', 'subject' => 'Microsoft Excel']);

    Livewire::test(CourseClassList::class)
        ->set('search', 'Tidak ditemukan')
        ->assertSee('Tidak ada kelas yang sesuai dengan filter.');
});

test('class and subject names are escaped in the rendered HTML', function () {
    CourseClass::factory()->create([
        'name' => '<script>alert(1)</script>',
        'subject' => '<img src=x onerror=alert(1)>',
    ]);

    Livewire::test(CourseClassList::class)
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
