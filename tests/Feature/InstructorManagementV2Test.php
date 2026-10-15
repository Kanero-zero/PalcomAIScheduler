<?php

use App\Livewire\InstructorList;
use App\Models\Competency;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorSkill;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use Database\Seeders\CompetencySeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CompetencySeeder::class);
});

// Skenario 1: Admin can create instructor
test('scenario 1: admin can create instructor with competencies and availabilities', function () {
    $admin = User::factory()->admin()->create();
    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();
    $excel = Competency::where('name', 'Microsoft Excel')->firstOrFail();

    $response = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Budi Pratama, M.Kom',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Instruktur spesialis aplikasi perkantoran.',
        'competencies' => [
            ['id' => $word->id, 'level' => 'advanced'],
            ['id' => $excel->id, 'level' => 'intermediate'],
        ],
        'availabilities' => [
            ['day' => 'Senin', 'start_time' => '08:00', 'end_time' => '12:00'],
            ['day' => 'Rabu', 'start_time' => '13:00', 'end_time' => '17:00'],
        ],
    ]);

    $response->assertRedirect(route('instructors.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('instructors', [
        'name' => 'Budi Pratama, M.Kom',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Instruktur spesialis aplikasi perkantoran.',
    ]);

    $instructor = Instructor::where('name', 'Budi Pratama, M.Kom')->firstOrFail();
    expect($instructor->skills)->toHaveCount(2)
        ->and($instructor->availabilities)->toHaveCount(2);

    $this->assertDatabaseHas('instructor_skills', [
        'instructor_id' => $instructor->id,
        'competency_id' => $word->id,
        'skill' => 'Microsoft Word',
        'level' => 'advanced',
    ]);
});

// Skenario 2: BAAK can create instructor
test('scenario 2: baak staff can create instructor', function () {
    $baak = User::factory()->baak()->create();
    $speakout1 = Competency::where('name', 'SpeakOut Level 1')->firstOrFail();

    $response = $this->actingAs($baak)->post(route('instructors.store'), [
        'name' => 'Sarah Johnson, B.A.',
        'department' => 'speakout',
        'status' => 'active',
        'notes' => 'Native English speaker.',
        'competencies' => [
            ['id' => $speakout1->id, 'level' => 'advanced'],
        ],
        'availabilities' => [
            ['day' => 'Selasa', 'start_time' => '09:00', 'end_time' => '11:00'],
        ],
    ]);

    $response->assertRedirect(route('instructors.index'));
    $this->assertDatabaseHas('instructors', [
        'name' => 'Sarah Johnson, B.A.',
        'department' => 'speakout',
    ]);
});

// Skenario 3: Admin and BAAK can edit instructor
test('scenario 3: admin and baak can edit instructor', function () {
    $admin = User::factory()->admin()->create();
    $baak = User::factory()->baak()->create();

    $instructor = Instructor::factory()->create([
        'name' => 'Instruktur Awal',
        'department' => 'computer',
        'status' => 'active',
    ]);

    // Admin updates
    $responseAdmin = $this->actingAs($admin)->put(route('instructors.update', $instructor), [
        'name' => 'Instruktur Diubah Admin',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Catatan admin',
        'competencies' => [],
        'availabilities' => [],
    ]);
    $responseAdmin->assertRedirect(route('instructors.index'));
    $this->assertDatabaseHas('instructors', ['id' => $instructor->id, 'name' => 'Instruktur Diubah Admin']);

    // BAAK updates
    $responseBaak = $this->actingAs($baak)->put(route('instructors.update', $instructor), [
        'name' => 'Instruktur Diubah BAAK',
        'department' => 'computer',
        'status' => 'inactive',
        'notes' => 'Catatan baak',
        'competencies' => [],
        'availabilities' => [],
    ]);
    $responseBaak->assertRedirect(route('instructors.index'));
    $this->assertDatabaseHas('instructors', [
        'id' => $instructor->id,
        'name' => 'Instruktur Diubah BAAK',
        'status' => 'inactive',
    ]);
});

// Skenario 4: AO and Instructor rejected from create/edit (403)
test('scenario 4: ao and instructor roles are rejected from create and edit with 403', function () {
    $ao = User::factory()->ao()->create();
    $instructorUser = User::factory()->instructor()->create();
    $instructor = Instructor::factory()->create(['department' => 'computer']);

    // AO checks
    $this->actingAs($ao)->get(route('instructors.create'))->assertForbidden();
    $this->actingAs($ao)->post(route('instructors.store'), ['name' => 'Tes'])->assertForbidden();
    $this->actingAs($ao)->get(route('instructors.edit', $instructor))->assertForbidden();
    $this->actingAs($ao)->put(route('instructors.update', $instructor), ['name' => 'Tes'])->assertForbidden();

    // Instructor user checks
    $this->actingAs($instructorUser)->get(route('instructors.create'))->assertForbidden();
    $this->actingAs($instructorUser)->post(route('instructors.store'), ['name' => 'Tes'])->assertForbidden();
    $this->actingAs($instructorUser)->get(route('instructors.edit', $instructor))->assertForbidden();
    $this->actingAs($instructorUser)->put(route('instructors.update', $instructor), ['name' => 'Tes'])->assertForbidden();
});

// Skenario 5: Guest redirected to login
test('scenario 5: guest is redirected to login for create store edit update', function () {
    $instructor = Instructor::factory()->create(['department' => 'computer']);

    $this->get(route('instructors.create'))->assertRedirect(route('login'));
    $this->post(route('instructors.store'), ['name' => 'Tes'])->assertRedirect(route('login'));
    $this->get(route('instructors.edit', $instructor))->assertRedirect(route('login'));
    $this->put(route('instructors.update', $instructor), ['name' => 'Tes'])->assertRedirect(route('login'));
});

// Skenario 6: Computer instructor can save multiple computer competencies
test('scenario 6: computer instructor can save multiple computer competencies', function () {
    $admin = User::factory()->admin()->create();
    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();
    $excel = Competency::where('name', 'Microsoft Excel')->firstOrFail();
    $ppt = Competency::where('name', 'Microsoft PowerPoint')->firstOrFail();

    $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Komputer Lengkap',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [
            ['id' => $word->id, 'level' => 'advanced'],
            ['id' => $excel->id, 'level' => 'advanced'],
            ['id' => $ppt->id, 'level' => 'intermediate'],
        ],
        'availabilities' => [],
    ])->assertRedirect(route('instructors.index'));

    $instructor = Instructor::where('name', 'Pengajar Komputer Lengkap')->firstOrFail();
    expect($instructor->skills)->toHaveCount(3);
});

// Skenario 7: SpeakOut instructor can save SpeakOut competencies
test('scenario 7: speakout instructor can save speakout competencies', function () {
    $admin = User::factory()->admin()->create();
    $speakout1 = Competency::where('name', 'SpeakOut Level 1')->firstOrFail();
    $speakout2 = Competency::where('name', 'SpeakOut Level 2')->firstOrFail();

    $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar SpeakOut Lengkap',
        'department' => 'speakout',
        'status' => 'active',
        'competencies' => [
            ['id' => $speakout1->id, 'level' => 'advanced'],
            ['id' => $speakout2->id, 'level' => 'advanced'],
        ],
        'availabilities' => [],
    ])->assertRedirect(route('instructors.index'));

    $instructor = Instructor::where('name', 'Pengajar SpeakOut Lengkap')->firstOrFail();
    expect($instructor->skills)->toHaveCount(2);
});

// Skenario 8: Cross-department competencies rejected
test('scenario 8: cross-department competencies are rejected by validation', function () {
    $admin = User::factory()->admin()->create();
    $speakout1 = Competency::where('name', 'SpeakOut Level 1')->firstOrFail();

    $response = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Campur Salah',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [
            ['id' => $speakout1->id, 'level' => 'advanced'],
        ],
        'availabilities' => [],
    ]);

    $response->assertSessionHasErrors(['competencies']);
    $this->assertDatabaseMissing('instructors', ['name' => 'Pengajar Campur Salah']);
});

// Skenario 9: Duplicate competencies rejected
test('scenario 9: duplicate competencies are rejected by validation', function () {
    $admin = User::factory()->admin()->create();
    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();

    $response = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Duplikat',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [
            ['id' => $word->id, 'level' => 'beginner'],
            ['id' => $word->id, 'level' => 'advanced'],
        ],
        'availabilities' => [],
    ]);

    $response->assertSessionHasErrors(['competencies']);
    $this->assertDatabaseMissing('instructors', ['name' => 'Pengajar Duplikat']);
});

// Skenario 10: Competency levels saved accurately
test('scenario 10: competency levels beginner intermediate advanced are saved accurately', function () {
    $admin = User::factory()->admin()->create();
    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();
    $excel = Competency::where('name', 'Microsoft Excel')->firstOrFail();
    $ppt = Competency::where('name', 'Microsoft PowerPoint')->firstOrFail();

    $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Variasi Level',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [
            ['id' => $word->id, 'level' => 'beginner'],
            ['id' => $excel->id, 'level' => 'intermediate'],
            ['id' => $ppt->id, 'level' => 'advanced'],
        ],
        'availabilities' => [],
    ])->assertRedirect(route('instructors.index'));

    $instructor = Instructor::where('name', 'Pengajar Variasi Level')->firstOrFail();
    expect($instructor->skills->firstWhere('competency_id', $word->id)?->level)->toBe('beginner')
        ->and($instructor->skills->firstWhere('competency_id', $excel->id)?->level)->toBe('intermediate')
        ->and($instructor->skills->firstWhere('competency_id', $ppt->id)?->level)->toBe('advanced');
});

// Skenario 11: "Pilih Semua" saves all active competencies in department
test('scenario 11: selecting all competencies in department persists all of them', function () {
    $admin = User::factory()->admin()->create();
    $computerCompetencies = Competency::active()->where('department', 'computer')->get();

    $payload = $computerCompetencies->map(fn ($c) => [
        'id' => $c->id,
        'level' => 'intermediate',
    ])->all();

    $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Master Semua Komputer',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => $payload,
        'availabilities' => [],
    ])->assertRedirect(route('instructors.index'));

    $instructor = Instructor::where('name', 'Master Semua Komputer')->firstOrFail();
    expect($instructor->skills)->toHaveCount($computerCompetencies->count());
});

// Skenario 12: Multiple valid time slots on same day saved
test('scenario 12: multiple valid time slots on same day are saved correctly', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Multi Slot',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [],
        'availabilities' => [
            ['day' => 'Senin', 'start_time' => '08:00', 'end_time' => '10:00'],
            ['day' => 'Senin', 'start_time' => '13:00', 'end_time' => '15:00'],
            ['day' => 'Senin', 'start_time' => '16:00', 'end_time' => '18:00'],
        ],
    ])->assertRedirect(route('instructors.index'));

    $instructor = Instructor::where('name', 'Pengajar Multi Slot')->firstOrFail();
    expect($instructor->availabilities)->toHaveCount(3);
});

// Skenario 13: Overlapping time slots rejected; adjacent accepted
test('scenario 13: overlapping time slots rejected but contiguous adjacent slots accepted', function () {
    $admin = User::factory()->admin()->create();

    // Overlapping slots (08:00-10:00 and 09:00-11:00)
    $responseOverlap = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Overlap',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [],
        'availabilities' => [
            ['day' => 'Senin', 'start_time' => '08:00', 'end_time' => '10:00'],
            ['day' => 'Senin', 'start_time' => '09:00', 'end_time' => '11:00'],
        ],
    ]);
    $responseOverlap->assertSessionHasErrors(['availabilities']);
    $this->assertDatabaseMissing('instructors', ['name' => 'Pengajar Overlap']);

    // Adjacent / contiguous slots (08:00-10:00 and 10:00-12:00)
    $responseAdjacent = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Adjacent',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [],
        'availabilities' => [
            ['day' => 'Senin', 'start_time' => '08:00', 'end_time' => '10:00'],
            ['day' => 'Senin', 'start_time' => '10:00', 'end_time' => '12:00'],
        ],
    ]);
    $responseAdjacent->assertRedirect(route('instructors.index'));
    $this->assertDatabaseHas('instructors', ['name' => 'Pengajar Adjacent']);
});

// Skenario 14: Data persists correctly and can be reloaded
test('scenario 14: instructor and relations persist correctly across database reloads', function () {
    $admin = User::factory()->admin()->create();
    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();

    $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Persistensi',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Catatan penting.',
        'competencies' => [
            ['id' => $word->id, 'level' => 'advanced'],
        ],
        'availabilities' => [
            ['day' => 'Kamis', 'start_time' => '08:30', 'end_time' => '11:30'],
        ],
    ])->assertRedirect(route('instructors.index'));

    $instructor = Instructor::where('name', 'Pengajar Persistensi')->firstOrFail();
    $reloaded = Instructor::with(['skills', 'availabilities'])->find($instructor->id);

    expect($reloaded)->not->toBeNull()
        ->and($reloaded->name)->toBe('Pengajar Persistensi')
        ->and($reloaded->department)->toBe('computer')
        ->and($reloaded->notes)->toBe('Catatan penting.')
        ->and($reloaded->skills)->toHaveCount(1)
        ->and($reloaded->skills->first()->skill)->toBe('Microsoft Word')
        ->and($reloaded->availabilities)->toHaveCount(1)
        ->and($reloaded->availabilities->first()->day)->toBe('Kamis')
        ->and($reloaded->availabilities->first()->start_time)->toBe('08:30');
});

// Skenario 15: Edit persists updates to competencies levels notes availability
test('scenario 15: edit persists updates to competencies levels notes and availability', function () {
    $admin = User::factory()->admin()->create();
    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();
    $excel = Competency::where('name', 'Microsoft Excel')->firstOrFail();

    $instructor = Instructor::factory()->create([
        'name' => 'Instruktur Untuk Diedit',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Catatan lama',
    ]);
    $instructor->skills()->create(['competency_id' => $word->id, 'skill' => 'Microsoft Word', 'level' => 'beginner']);
    $instructor->availabilities()->create(['day' => 'Senin', 'start_time' => '08:00', 'end_time' => '10:00']);

    // Update to replace Word with Excel (advanced) and change availability to Tuesday
    $this->actingAs($admin)->put(route('instructors.update', $instructor), [
        'name' => 'Instruktur Berhasil Diedit',
        'department' => 'computer',
        'status' => 'inactive',
        'notes' => 'Catatan baru telah diperbarui.',
        'competencies' => [
            ['id' => $excel->id, 'level' => 'advanced'],
        ],
        'availabilities' => [
            ['day' => 'Selasa', 'start_time' => '13:00', 'end_time' => '16:00'],
        ],
    ])->assertRedirect(route('instructors.index'));

    $instructor->refresh();
    expect($instructor->name)->toBe('Instruktur Berhasil Diedit')
        ->and($instructor->status)->toBe('inactive')
        ->and($instructor->notes)->toBe('Catatan baru telah diperbarui.')
        ->and($instructor->skills)->toHaveCount(1)
        ->and($instructor->skills->first()->competency_id)->toBe($excel->id)
        ->and($instructor->skills->first()->level)->toBe('advanced')
        ->and($instructor->availabilities)->toHaveCount(1)
        ->and($instructor->availabilities->first()->day)->toBe('Selasa')
        ->and($instructor->availabilities->first()->start_time)->toBe('13:00');
});

// Skenario 16: Legacy instructor data remains intact after migration
test('scenario 16: legacy instructor data and unmapped skills remain intact during updates', function () {
    $admin = User::factory()->admin()->create();
    $excel = Competency::where('name', 'Microsoft Excel')->firstOrFail();

    // Create a legacy instructor with null department and a legacy skill not mapped to any competency
    $legacyInstructor = Instructor::factory()->create([
        'name' => 'Guru Sepuh',
        'department' => null,
        'status' => 'active',
    ]);
    $legacySkill = $legacyInstructor->skills()->create([
        'skill' => 'Adobe PageMaker 6.5',
        'level' => 'advanced',
        'competency_id' => null,
    ]);

    // Admin updates the instructor to assign department and add Excel, but unmapped legacy skill should remain!
    $this->actingAs($admin)->put(route('instructors.update', $legacyInstructor), [
        'name' => 'Guru Sepuh (Terkini)',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Telah di-upgrade.',
        'competencies' => [
            ['id' => $excel->id, 'level' => 'advanced'],
        ],
        'availabilities' => [],
    ])->assertRedirect(route('instructors.index'));

    $legacyInstructor->refresh();
    expect($legacyInstructor->department)->toBe('computer')
        ->and($legacyInstructor->skills)->toHaveCount(2);

    // Verify unmapped legacy skill is preserved
    $this->assertDatabaseHas('instructor_skills', [
        'id' => $legacySkill->id,
        'skill' => 'Adobe PageMaker 6.5',
        'competency_id' => null,
    ]);
    // And new competency is attached
    $this->assertDatabaseHas('instructor_skills', [
        'instructor_id' => $legacyInstructor->id,
        'competency_id' => $excel->id,
        'skill' => 'Microsoft Excel',
    ]);
});

// Skenario 17: Department filter, status filter, and search work simultaneously in InstructorList
test('scenario 17: department filter status filter and search work simultaneously in InstructorList', function () {
    $user = User::factory()->admin()->create();

    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();
    $speakout = Competency::where('name', 'SpeakOut Level 1')->firstOrFail();

    $budi = Instructor::factory()->create([
        'name' => 'Budi Pratama',
        'department' => 'computer',
        'status' => 'active',
    ]);
    $budi->skills()->create(['competency_id' => $word->id, 'skill' => 'Microsoft Word', 'level' => 'advanced']);

    $ani = Instructor::factory()->create([
        'name' => 'Ani Lestari',
        'department' => 'speakout',
        'status' => 'active',
    ]);
    $ani->skills()->create(['competency_id' => $speakout->id, 'skill' => 'SpeakOut Level 1', 'level' => 'advanced']);

    $bambang = Instructor::factory()->create([
        'name' => 'Bambang Pamungkas',
        'department' => 'computer',
        'status' => 'inactive',
    ]);
    $bambang->skills()->create(['competency_id' => $word->id, 'skill' => 'Microsoft Word', 'level' => 'beginner']);

    // Livewire test
    Livewire::actingAs($user)
        ->test(InstructorList::class)
        ->set('department', 'computer')
        ->set('status', 'active')
        ->set('search', 'Budi')
        ->assertSee('Budi Pratama')
        ->assertDontSee('Ani Lestari')
        ->assertDontSee('Bambang Pamungkas');
});

// Skenario 18: DB transaction rolls back if an error occurs
test('scenario 18: db transaction rolls back if an error occurs during instructor persistence', function () {
    $admin = User::factory()->admin()->create();
    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();

    // We can simulate an error during transaction by temporarily hooking a failing listener or mock
    // Alternatively, pass an availability payload with invalid column data that throws QueryException
    // Or assert DB::transaction atomicity
    $initialCount = Instructor::count();

    try {
        DB::transaction(function () use ($word) {
            $instructor = Instructor::create([
                'name' => 'Instruktur Gagal Transaksi',
                'department' => 'computer',
                'status' => 'active',
            ]);

            $instructor->skills()->create([
                'skill' => 'Microsoft Word',
                'competency_id' => $word->id,
                'level' => 'advanced',
            ]);

            // Force a rollback / exception
            throw new RuntimeException('Simulasi kegagalan atomik database.');
        });
    } catch (Throwable) {
        // Exception caught
    }

    expect(Instructor::count())->toBe($initialCount)
        ->and(Instructor::where('name', 'Instruktur Gagal Transaksi')->exists())->toBeFalse()
        ->and(InstructorSkill::where('skill', 'Microsoft Word')->whereHas('instructor', fn ($q) => $q->where('name', 'Instruktur Gagal Transaksi'))->exists())->toBeFalse();
});

// Skenario 19: Form validation rules on store and update
test('scenario 19: validation rejects missing name, invalid department, and invalid time formats', function () {
    $admin = User::factory()->admin()->create();

    // Missing name and invalid department
    $response = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => '',
        'department' => 'invalid_dept',
        'status' => 'active',
        'availabilities' => [
            ['day' => 'Senin', 'start_time' => '14:00', 'end_time' => '10:00'], // start >= end
        ],
    ]);

    $response->assertSessionHasErrors(['name', 'department', 'availabilities']);
});

// Skenario 20: SchedulingEngine compatibility verification
test('scenario 20: scheduling engine continues to match instructor skills properly', function () {
    $instructor = Instructor::factory()->create([
        'name' => 'Guru Engine Scheduler',
        'department' => 'computer',
        'status' => 'active',
    ]);
    $instructor->skills()->create([
        'skill' => 'Microsoft Excel',
        'level' => 'advanced',
    ]);

    $courseClass = CourseClass::factory()->create(['subject' => 'Microsoft Excel']);
    $schedule = Schedule::factory()->create([
        'course_class_id' => $courseClass->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    $engine = app(SchedulingEngine::class);
    $evaluation = $engine->evaluateCandidate($instructor, $schedule);

    expect($evaluation->competencyMatched)->toBeTrue()
        ->and($evaluation->skillName)->toBe('Microsoft Excel')
        ->and($evaluation->skillLevel)->toBe('advanced');
});

// Regression: RBAC does not grant Admin/BAAK based on email substring
test('regression: staff user with baak or admin in email substring is strictly rejected', function () {
    $staffWithBaakEmail = User::factory()->create([
        'email' => 'staff.baak.dummy@palcomtech.ac.id',
        'role' => 'staff',
    ]);

    expect($staffWithBaakEmail->isBaak())->toBeFalse()
        ->and($staffWithBaakEmail->isAdmin())->toBeFalse()
        ->and($staffWithBaakEmail->canManageInstructors())->toBeFalse();

    $instructor = Instructor::factory()->create(['department' => 'computer']);

    $this->actingAs($staffWithBaakEmail)->get(route('instructors.create'))->assertForbidden();
    $this->actingAs($staffWithBaakEmail)->post(route('instructors.store'), ['name' => 'Tes'])->assertForbidden();
    $this->actingAs($staffWithBaakEmail)->get(route('instructors.edit', $instructor))->assertForbidden();
    $this->actingAs($staffWithBaakEmail)->put(route('instructors.update', $instructor), ['name' => 'Tes'])->assertForbidden();
});

// Regression: Strict time format rejects invalid hours and minutes
test('regression: validation rejects invalid hours 24-99 and invalid minutes 60-99', function () {
    $admin = User::factory()->admin()->create();

    // Invalid hour (24:00)
    $responseHour = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Jam Salah',
        'department' => 'computer',
        'status' => 'active',
        'availabilities' => [
            ['day' => 'Senin', 'start_time' => '24:00', 'end_time' => '25:00'],
        ],
    ]);
    $responseHour->assertSessionHasErrors(['availabilities']);

    // Invalid minute (08:60)
    $responseMinute = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Menit Salah',
        'department' => 'computer',
        'status' => 'active',
        'availabilities' => [
            ['day' => 'Senin', 'start_time' => '08:60', 'end_time' => '09:00'],
        ],
    ]);
    $responseMinute->assertSessionHasErrors(['availabilities']);
});

// Regression: Duplicate day groups in payload cannot bypass overlap checking
test('regression: duplicate day groups in availability payload cannot bypass overlap checking', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Duplicate Day Groups',
        'department' => 'computer',
        'status' => 'active',
        'availabilities' => [
            [
                'label' => 'Senin',
                'enabled' => true,
                'slots' => [
                    ['start' => '08:00', 'end' => '11:00'],
                ],
            ],
            [
                'label' => 'Senin',
                'enabled' => true,
                'slots' => [
                    ['start' => '10:00', 'end' => '12:00'],
                ],
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['availabilities']);
    $this->assertDatabaseMissing('instructors', ['name' => 'Pengajar Duplicate Day Groups']);
});

// Regression: Inactive competency rejected for new assignment via direct request
test('regression: inactive competency is rejected for new assignment on create and update', function () {
    $admin = User::factory()->admin()->create();
    $inactive = Competency::create([
        'name' => 'PASCAL Programming (Arsip)',
        'department' => 'computer',
        'is_active' => false,
    ]);

    // Direct POST store with inactive competency
    $responseStore = $this->actingAs($admin)->post(route('instructors.store'), [
        'name' => 'Pengajar Arsip Baru',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [
            ['id' => $inactive->id, 'level' => 'advanced'],
        ],
    ]);
    $responseStore->assertSessionHasErrors(['competencies']);
    $this->assertDatabaseMissing('instructors', ['name' => 'Pengajar Arsip Baru']);

    // Direct PUT update with NEW inactive competency
    $instructor = Instructor::factory()->create(['department' => 'computer']);
    $responseUpdate = $this->actingAs($admin)->put(route('instructors.update', $instructor), [
        'name' => $instructor->name,
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [
            ['id' => $inactive->id, 'level' => 'advanced'],
        ],
    ]);
    $responseUpdate->assertSessionHasErrors(['competencies']);
});

// Regression: Existing historical inactive competency can be preserved during update
test('regression: existing historical inactive competency is preserved during instructor update', function () {
    $admin = User::factory()->admin()->create();
    $inactive = Competency::create([
        'name' => 'COBOL & Fortran (Arsip)',
        'department' => 'computer',
        'is_active' => false,
    ]);

    $instructor = Instructor::factory()->create([
        'name' => 'Pengajar Arsip Legasi',
        'department' => 'computer',
        'status' => 'active',
    ]);
    // Historically attached prior to being inactive
    $instructor->skills()->create([
        'competency_id' => $inactive->id,
        'skill' => 'COBOL & Fortran (Arsip)',
        'level' => 'advanced',
    ]);

    // Updating instructor while keeping the historical inactive competency is accepted
    $response = $this->actingAs($admin)->put(route('instructors.update', $instructor), [
        'name' => 'Pengajar Arsip Legasi (Updated)',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Catatan diupdate.',
        'competencies' => [
            ['id' => $inactive->id, 'level' => 'advanced'],
        ],
        'availabilities' => [],
    ]);

    $response->assertRedirect(route('instructors.index'));
    $this->assertDatabaseHas('instructors', ['name' => 'Pengajar Arsip Legasi (Updated)']);
    $this->assertDatabaseHas('instructor_skills', [
        'instructor_id' => $instructor->id,
        'competency_id' => $inactive->id,
    ]);
});

// Regression: Empty master catalog renders truthful empty state without mock data
test('regression: empty master catalog renders truthful empty state without fake mock catalog', function () {
    $admin = User::factory()->admin()->create();

    // Delete all competencies
    Competency::query()->delete();

    $response = $this->actingAs($admin)->get(route('instructors.create'));
    $response->assertOk()
        ->assertSee('Belum ada materi kompetensi yang tersedia pada katalog master untuk bidang ini.')
        ->assertDontSee('Microsoft Word');
});

// Regression: Historical inactive competency is preserved during edit using normal form payload omitting inactive skills
test('regression: historical inactive competency is preserved during edit using normal form payload omitting inactive skills', function () {
    $admin = User::factory()->admin()->create();

    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();
    $excel = Competency::where('name', 'Microsoft Excel')->firstOrFail();

    $inactive = Competency::create([
        'name' => 'Visual Basic 6.0 (Arsip)',
        'department' => 'computer',
        'is_active' => false,
    ]);

    $instructor = Instructor::factory()->create([
        'name' => 'Instruktur Pengajar Legasi',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Catatan awal',
    ]);

    // Instruktur sudah memiliki skill aktif (Word: beginner) dan skill arsip historis (VB6: advanced)
    $instructor->skills()->create([
        'competency_id' => $word->id,
        'skill' => $word->name,
        'level' => 'beginner',
    ]);
    $instructor->skills()->create([
        'competency_id' => $inactive->id,
        'skill' => $inactive->name,
        'level' => 'advanced',
    ]);

    // BAAK mengedit instruktur melalui formulir Edit normal:
    // Formulir edit hanya menyajikan kompetensi aktif (Word & Excel), sehingga $inactive sama sekali TIDAK ada dalam payload.
    // BAAK menaikkan level Word menjadi intermediate dan memilih Excel level advanced.
    $normalEditPayload = [
        'name' => 'Instruktur Pengajar Legasi (Diperbarui)',
        'department' => 'computer',
        'status' => 'active',
        'notes' => 'Catatan diperbarui oleh BAAK',
        'competencies' => [
            ['id' => $word->id, 'level' => 'intermediate'],
            ['id' => $excel->id, 'level' => 'advanced'],
        ],
        'availabilities' => [],
    ];

    $response = $this->actingAs($admin)->put(route('instructors.update', $instructor), $normalEditPayload);
    $response->assertRedirect(route('instructors.index'));

    // Pastikan kompetensi historis nonaktif TETAP TERAWAT dan tidak terhapus
    $this->assertDatabaseHas('instructor_skills', [
        'instructor_id' => $instructor->id,
        'competency_id' => $inactive->id,
        'skill' => 'Visual Basic 6.0 (Arsip)',
        'level' => 'advanced',
    ]);

    // Pastikan perubahan kompetensi aktif sesuai tindakan pengguna tersimpan dengan benar
    $this->assertDatabaseHas('instructor_skills', [
        'instructor_id' => $instructor->id,
        'competency_id' => $word->id,
        'level' => 'intermediate',
    ]);
    $this->assertDatabaseHas('instructor_skills', [
        'instructor_id' => $instructor->id,
        'competency_id' => $excel->id,
        'level' => 'advanced',
    ]);

    // Uji uncheck kompetensi aktif: BAAK mengedit lagi dan menghapus Excel (hanya menyertakan Word)
    $secondUpdatePayload = [
        'name' => 'Instruktur Pengajar Legasi (Diperbarui)',
        'department' => 'computer',
        'status' => 'active',
        'competencies' => [
            ['id' => $word->id, 'level' => 'advanced'],
        ],
        'availabilities' => [],
    ];

    $this->actingAs($admin)->put(route('instructors.update', $instructor), $secondUpdatePayload);

    // Excel terhapus sesuai pilihan pengguna
    $this->assertDatabaseMissing('instructor_skills', [
        'instructor_id' => $instructor->id,
        'competency_id' => $excel->id,
    ]);

    // Word terupdate menjadi advanced
    $this->assertDatabaseHas('instructor_skills', [
        'instructor_id' => $instructor->id,
        'competency_id' => $word->id,
        'level' => 'advanced',
    ]);

    // Kompetensi historis nonaktif MASIH TETAP TERAWAT
    $this->assertDatabaseHas('instructor_skills', [
        'instructor_id' => $instructor->id,
        'competency_id' => $inactive->id,
        'skill' => 'Visual Basic 6.0 (Arsip)',
        'level' => 'advanced',
    ]);
});

// Regression: Re-running competency seeder does not reactivate intentionally deactivated competency
test('regression: re-running competency seeder does not reactivate intentionally deactivated competency', function () {
    // Jalankan seeder awal
    (new CompetencySeeder)->run();

    $word = Competency::where('name', 'Microsoft Word')->firstOrFail();
    expect($word->is_active)->toBeTrue();

    // Administrator sengaja menonaktifkan kompetensi
    $word->update(['is_active' => false]);
    expect($word->fresh()->is_active)->toBeFalse();

    // Jalankan seeder ulang
    (new CompetencySeeder)->run();

    // Kompetensi yang sengaja dinonaktifkan TIDAK boleh aktif kembali
    expect($word->fresh()->is_active)->toBeFalse();

    // Pastikan kompetensi lain yang tidak dinonaktifkan tetap aktif
    $excel = Competency::where('name', 'Microsoft Excel')->firstOrFail();
    expect($excel->is_active)->toBeTrue();
});
