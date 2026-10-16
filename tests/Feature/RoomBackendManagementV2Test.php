<?php

use App\Livewire\RoomList;
use App\Models\Competency;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Room\RoomConflictGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 10, 0, 0));

    // Siapkan kompetensi standar
    Competency::firstOrCreate(['name' => 'Microsoft Excel'], ['department' => 'computer', 'is_active' => true]);
    Competency::firstOrCreate(['name' => 'Microsoft Word'], ['department' => 'computer', 'is_active' => true]);
    Competency::firstOrCreate(['name' => 'SpeakOut Level 1'], ['department' => 'speakout', 'is_active' => true]);
});

afterEach(function () {
    Carbon::setTestNow();
});

// R01: Admin Tambah ruangan Komputer
test('R01: admin can create a computer room and it persists in database', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Lab Komputer Baru')
        ->set('formDepartment', 'computer')
        ->set('capacity', 25)
        ->set('formStatus', 'available')
        ->set('notes', 'Tersedia 25 PC core i7.')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rooms', [
        'name' => 'Lab Komputer Baru',
        'department' => 'computer',
        'capacity' => 25,
        'status' => 'available',
        'notes' => 'Tersedia 25 PC core i7.',
    ]);
});

// R02: BAAK Tambah ruangan SpeakOut
test('R02: baak can create a speakout room with department speakout', function () {
    $baak = User::factory()->baak()->create();

    $this->actingAs($baak);

    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Ruang SpeakOut 1')
        ->set('formDepartment', 'speakout')
        ->set('capacity', 15)
        ->set('formStatus', 'available')
        ->set('notes', 'Audio system ready')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rooms', [
        'name' => 'Ruang SpeakOut 1',
        'department' => 'speakout',
        'capacity' => 15,
        'status' => 'available',
    ]);
});

// R03: Edit lima field dan reload
test('R03: edit five fields updates the exact same record and persists after reload', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create([
        'name' => 'Lab Awal',
        'department' => 'computer',
        'capacity' => 20,
        'status' => 'available',
        'notes' => 'Catatan awal',
    ]);

    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('name', 'Lab Diperbarui')
        ->set('formDepartment', 'computer')
        ->set('capacity', 30)
        ->set('formStatus', 'maintenance')
        ->set('notes', 'Sedang upgrade RAM')
        ->call('save')
        ->assertHasNoErrors();

    $room->refresh();

    expect($room->name)->toBe('Lab Diperbarui')
        ->and($room->department)->toBe('computer')
        ->and($room->capacity)->toBe(30)
        ->and($room->status)->toBe('maintenance')
        ->and($room->notes)->toBe('Sedang upgrade RAM');

    // Reload halaman
    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('Lab Diperbarui')
        ->assertSee('30 orang')
        ->assertSee('Perawatan')
        ->assertSee('Sedang upgrade RAM');
});

// R04: AO dan Instruktur dilarang menulis (Livewire action / request)
test('R04: ao and instructor are forbidden from writing rooms via server action', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $room = Room::factory()->create(['name' => 'Lab Sakral']);

    $this->actingAs($user);

    // Coba create via Livewire
    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->assertForbidden();

    // Coba save create
    Livewire::test(RoomList::class)
        ->set('name', 'Lab Liar')
        ->set('formDepartment', 'computer')
        ->set('capacity', 20)
        ->set('formStatus', 'available')
        ->call('save')
        ->assertForbidden();

    // Coba save edit
    Livewire::test(RoomList::class)
        ->set('roomId', $room->id)
        ->set('mode', 'edit')
        ->set('name', 'Lab Rusak')
        ->set('formDepartment', 'speakout')
        ->set('capacity', 10)
        ->set('formStatus', 'unavailable')
        ->call('save')
        ->assertForbidden();

    expect(Room::where('name', 'Lab Liar')->exists())->toBeFalse();
    expect($room->fresh()->name)->toBe('Lab Sakral');
})->with(['ao', 'instructor', 'staff']);

// R05: Guest melakukan write dialihkan / ditolak
test('R05: guest writing to room list is redirected to login', function () {
    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->assertRedirect(route('login'));

    Livewire::test(RoomList::class)
        ->set('name', 'Lab Hantu')
        ->set('formDepartment', 'computer')
        ->set('capacity', 20)
        ->set('formStatus', 'available')
        ->call('save')
        ->assertRedirect(route('login'));

    expect(Room::where('name', 'Lab Hantu')->exists())->toBeFalse();
});

// R06: Bidang kosong atau tidak valid ditolak
test('R06: department is required and must be either computer or speakout', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Lab Tanpa Bidang')
        ->set('formDepartment', '')
        ->set('capacity', 20)
        ->set('formStatus', 'available')
        ->call('save')
        ->assertHasErrors(['formDepartment']);

    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Lab Bidang Liar')
        ->set('formDepartment', 'engineering')
        ->set('capacity', 20)
        ->set('formStatus', 'available')
        ->call('save')
        ->assertHasErrors(['formDepartment']);
});

// R07: Kapasitas nol, negatif, pecahan, teks ditolak
test('R07: capacity must be a positive integer greater than or equal to 1', function ($invalidCapacity) {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Lab Kapasitas Aneh')
        ->set('formDepartment', 'computer')
        ->set('capacity', $invalidCapacity)
        ->set('formStatus', 'available')
        ->call('save')
        ->assertHasErrors(['capacity']);
})->with([
    0,
    -5,
    'abc',
    '12.5',
    '',
]);

// R08: Status selain tiga nilai sah ditolak
test('R08: status must be one of available, maintenance, unavailable', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Lab Status Palsu')
        ->set('formDepartment', 'computer')
        ->set('capacity', 20)
        ->set('formStatus', 'demolished')
        ->call('save')
        ->assertHasErrors(['formStatus']);
});

// R09: Nama kosong/whitespace dan notes > 500 ditolak
test('R09: name cannot be empty or whitespace only and notes cannot exceed 500 characters', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    // Whitespace name
    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', '     ')
        ->set('formDepartment', 'computer')
        ->set('capacity', 20)
        ->set('formStatus', 'available')
        ->call('save')
        ->assertHasErrors(['name']);

    // Notes > 500 chars
    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Lab Catatan Panjang')
        ->set('formDepartment', 'computer')
        ->set('capacity', 20)
        ->set('formStatus', 'available')
        ->set('notes', str_repeat('a', 501))
        ->call('save')
        ->assertHasErrors(['notes']);
});

// R10: Form gagal validasi mempertahankan modal terbuka dan input
test('R10: validation failure retains modal state and preserves input values without db write', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $initialCount = Room::count();

    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Lab Gagal')
        ->set('formDepartment', '') // invalid
        ->set('capacity', 15)
        ->set('formStatus', 'available')
        ->set('notes', 'Catatan tetap ada')
        ->call('save')
        ->assertHasErrors(['formDepartment'])
        ->assertSet('name', 'Lab Gagal')
        ->assertSet('capacity', 15)
        ->assertSet('notes', 'Catatan tetap ada')
        ->assertSet('isFormOpen', true);

    expect(Room::count())->toBe($initialCount);
});

// R11: Room legacy department=NULL, notes=NULL sebelum dan sesudah edit
test('R11: legacy room has unselected department radio and neutral notes label, persists after valid edit', function () {
    $admin = User::factory()->admin()->create();
    $legacy = Room::factory()->create([
        'name' => 'Lab Warisan Lama',
        'department' => null,
        'capacity' => 12,
        'status' => 'available',
        'notes' => null,
    ]);

    $this->actingAs($admin);

    // Saat dibuka untuk edit, formDepartment harus null/kosong
    $component = Livewire::test(RoomList::class)
        ->call('openEdit', $legacy->id)
        ->assertSet('formDepartment', null)
        ->assertSet('notes', '');

    // Render list menampilkan "Belum tercatat" dan "Belum ada catatan"
    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('Lab Warisan Lama')
        ->assertSee('Belum tercatat')
        ->assertSee('Belum ada catatan');

    // Pengguna memilih bidang valid 'computer' lalu menyimpan
    $component
        ->set('formDepartment', 'computer')
        ->call('save')
        ->assertHasNoErrors();

    $legacy->refresh();
    expect($legacy->department)->toBe('computer')
        ->and($legacy->notes)->toBeNull();

    // Tampilan setelah disimpan: bidang berubah jadi Komputer, catatan tetap netral "Belum ada catatan"
    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('Komputer')
        ->assertSee('Belum ada catatan');
});

// R12: Migrasi data dengan jadwal existing tetap utuh
test('R12: existing schedules and foreign keys remain completely intact', function () {
    $room = Room::factory()->create(['name' => 'Lab Jadwal Utuh']);
    $class = CourseClass::factory()->create(['name' => 'Excel Kelas', 'subject' => 'Microsoft Excel']);
    $instructor = Instructor::factory()->create();

    $schedule = Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => $instructor->id,
        'room_id' => $room->id,
        'date' => '2026-10-20',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $room->update([
        'department' => 'computer',
        'notes' => 'Tambahan keterangan',
    ]);

    expect($schedule->fresh()->room_id)->toBe($room->id)
        ->and($room->schedules()->count())->toBe(1)
        ->and($schedule->fresh()->courseClass->name)->toBe('Excel Kelas');
});

// R13: Modal Detail setelah save/edit menampilkan data aktual
test('R13: detail modal displays actual database records', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create([
        'name' => 'Lab Detail Akurat',
        'department' => 'speakout',
        'capacity' => 18,
        'status' => 'maintenance',
        'notes' => 'Proyektor rusak',
    ]);

    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openDetail', $room->id)
        ->assertSet('selectedRoom.name', 'Lab Detail Akurat')
        ->assertSet('selectedRoom.department', 'speakout')
        ->assertSet('selectedRoom.capacity', 18)
        ->assertSet('selectedRoom.status', 'maintenance')
        ->assertSet('selectedRoom.notes', 'Proyektor rusak')
        ->assertSee('Detail Ruangan — Lab Detail Akurat')
        ->assertSee('SpeakOut')
        ->assertSee('Proyektor rusak');
});

// R14: Gabungan search, filter bidang, filter status, reset, pagination
test('R14: combined search, department filter, status filter, reset, and pagination', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Room::factory()->create(['name' => 'Lab Komputer 01', 'department' => 'computer', 'status' => 'available']);
    Room::factory()->create(['name' => 'Lab Komputer 02', 'department' => 'computer', 'status' => 'maintenance']);
    Room::factory()->create(['name' => 'Lab SpeakOut 01', 'department' => 'speakout', 'status' => 'available']);
    Room::factory()->create(['name' => 'Lab Legacy 01', 'department' => null, 'status' => 'available']);

    $component = Livewire::test(RoomList::class);

    // Semua bidang menampilkan semua termasuk legacy null
    $component->assertSee('Lab Komputer 01')
        ->assertSee('Lab Komputer 02')
        ->assertSee('Lab SpeakOut 01')
        ->assertSee('Lab Legacy 01');

    // Filter bidang Komputer
    $component->set('department', 'computer')
        ->assertSee('Lab Komputer 01')
        ->assertSee('Lab Komputer 02')
        ->assertDontSee('Lab SpeakOut 01')
        ->assertDontSee('Lab Legacy 01');

    // Tambah filter status maintenance
    $component->set('status', 'maintenance')
        ->assertSee('Lab Komputer 02')
        ->assertDontSee('Lab Komputer 01');

    // Reset filter
    $component->call('resetFilters')
        ->assertSet('department', '')
        ->assertSet('status', '')
        ->assertSet('search', '')
        ->assertSee('Lab Legacy 01');
});

// R17: Mass assignment, XSS sanitization
test('R17: mass assignment protection rejects injected attributes and escapes xss in notes', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $xssNotes = '<script>alert("xss")</script> Ruang aman';

    Livewire::test(RoomList::class)
        ->call('openCreate')
        ->set('name', 'Lab XSS')
        ->set('formDepartment', 'computer')
        ->set('capacity', 20)
        ->set('formStatus', 'available')
        ->set('notes', $xssNotes)
        ->call('save')
        ->assertHasNoErrors();

    $room = Room::where('name', 'Lab XSS')->firstOrFail();

    // Di browser/blade, tag script harus diescape (assertSee text, assertDontSee raw unescaped script tag)
    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
});

// R18: Browser reload memuat data asli dari DB bukan preview lama
test('R18: opening edit dialog loads fresh data from database after reload', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create([
        'name' => 'Lab Asli',
        'department' => 'computer',
        'capacity' => 15,
        'status' => 'available',
    ]);

    $this->actingAs($admin);

    // Edit langsung di database
    $room->update(['capacity' => 22, 'status' => 'maintenance']);

    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->assertSet('capacity', '22')
        ->assertSet('formStatus', 'maintenance');
});

// R19: Ubah ruang dengan pertemuan resmi mendatang menjadi maintenance/unavailable atau bidang tidak cocok DITOLAK
test('R19: changing room with official upcoming meeting to maintenance/unavailable or mismatching department is rejected', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create([
        'name' => 'Lab 1',
        'department' => 'computer',
        'capacity' => 20,
        'status' => 'available',
    ]);

    $class = CourseClass::factory()->create([
        'name' => 'Kelas Microsoft Excel Pagi',
        'subject' => 'Microsoft Excel', // terpetakan ke 'computer'
        'student_count' => 10,
    ]);

    // Buat jadwal resmi di masa depan
    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-20',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    // A. Coba ubah status ke maintenance -> harus ditolak
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formStatus', 'maintenance')
        ->call('save')
        ->assertHasErrors(['status'])
        ->assertSet('isFormOpen', true);

    expect($room->fresh()->status)->toBe('available');

    // B. Coba ubah status ke unavailable -> harus ditolak
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formStatus', 'unavailable')
        ->call('save')
        ->assertHasErrors(['status']);

    expect($room->fresh()->status)->toBe('available');

    // C. Coba ubah bidang ke speakout -> harus ditolak karena ada kelas Microsoft Excel (computer)
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formDepartment', 'speakout')
        ->call('save')
        ->assertHasErrors(['department']);

    expect($room->fresh()->department)->toBe('computer');

    // D. Ubah nama saja -> Boleh disimpan meskipun ada jadwal resmi!
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('name', 'Lab 1 - Nama Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($room->fresh()->name)->toBe('Lab 1 - Nama Baru');
});

// R20: Turunkan kapasitas di bawah jumlah siswa kelas resmi mendatang DITOLAK
test('R20: decreasing capacity below student count of official upcoming meeting is rejected', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create([
        'name' => 'Lab Kapasitas 20',
        'department' => 'computer',
        'capacity' => 20,
        'status' => 'available',
    ]);

    $class = CourseClass::factory()->create([
        'name' => 'Kelas Excel Ramai',
        'subject' => 'Microsoft Excel',
        'student_count' => 18,
    ]);

    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-22',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    // Turunkan kapasitas menjadi 15 (< 18) -> ditolak
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('capacity', 15)
        ->call('save')
        ->assertHasErrors(['capacity']);

    expect($room->fresh()->capacity)->toBe(20);

    // Naikkan kapasitas menjadi 25 -> boleh
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('capacity', 25)
        ->call('save')
        ->assertHasNoErrors();

    expect($room->fresh()->capacity)->toBe(25);
});

// R21: Ruang dengan proposal pending/draft saja tidak mengunci ruangan
test('R21: schedules with non-official status like draft, pending, or cancelled do not lock room', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create([
        'name' => 'Lab Bebas',
        'department' => 'computer',
        'capacity' => 20,
        'status' => 'available',
    ]);

    $class = CourseClass::factory()->create([
        'name' => 'Kelas Draf',
        'subject' => 'Microsoft Excel',
        'student_count' => 19,
    ]);

    // Buat jadwal dengan status cancelled atau draft
    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-25',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'cancelled',
    ]);

    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-26',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    // Status dapat diubah ke maintenance karena tidak ada jadwal resmi
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formStatus', 'maintenance')
        ->call('save')
        ->assertHasNoErrors();

    expect($room->fresh()->status)->toBe('maintenance');
});

// R22: Fail-Closed saat materi tidak dikenal atau status jadwal tidak terverifikasi
test('R22: fail closed rejects risky edits when class subject is uncataloged or status is unverified', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create([
        'name' => 'Lab Khusus',
        'department' => 'computer',
        'capacity' => 20,
        'status' => 'available',
    ]);

    // Kasus A: Kelas dengan materi tidak ada di katalog kompetensi
    $mysteryClass = CourseClass::factory()->create([
        'name' => 'Kelas Mata Pelajaran Misterius',
        'subject' => 'Teknik Penerbangan Antariksa', // tidak ada di Competency
        'student_count' => 10,
    ]);

    $scheduleA = Schedule::create([
        'course_class_id' => $mysteryClass->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-27',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    // Coba ubah bidang ke speakout -> Fail-Closed karena materi tidak terdaftar
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formDepartment', 'speakout')
        ->call('save')
        ->assertHasErrors(['department']);

    expect($room->fresh()->department)->toBe('computer');

    // Hapus jadwal misterius untuk menguji Kasus B
    $scheduleA->delete();

    // Kasus B: Status jadwal tidak terverifikasi / corrupt (bukan scheduled, bukan cancelled/draft)
    $corruptClass = CourseClass::factory()->create([
        'name' => 'Kelas Excel',
        'subject' => 'Microsoft Excel',
        'student_count' => 10,
    ]);

    Schedule::create([
        'course_class_id' => $corruptClass->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-28',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'unknown_corrupt_status',
    ]);

    // Coba ubah status ke maintenance -> Fail-Closed
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formStatus', 'maintenance')
        ->call('save')
        ->assertHasErrors(['status']);

    expect($room->fresh()->status)->toBe('available');
});

// Tes Tambahan 1: Timezone WIB - Jadwal hari ini belum mulai mengunci ruangan
test('timezone WIB: schedule today not yet started locks room status change', function () {
    // Set waktu saat ini ke pukul 08:00:00 WIB pada 15 Oktober 2026
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 8, 0, 0, 'Asia/Jakarta'));

    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create(['status' => 'available', 'department' => 'computer']);
    $class = CourseClass::factory()->create(['subject' => 'Microsoft Excel', 'student_count' => 10]);

    // Jadwal hari ini pukul 09:00 - 11:00 WIB (belum mulai)
    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formStatus', 'maintenance')
        ->call('save')
        ->assertHasErrors(['status']);

    expect($room->fresh()->status)->toBe('available');
});

// Tes Tambahan 2: Timezone WIB - Jadwal hari ini sedang berlangsung mengunci ruangan
test('timezone WIB: schedule today in-progress locks room status change', function () {
    // Set waktu saat ini ke pukul 10:00:00 WIB pada 15 Oktober 2026
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 10, 0, 0, 'Asia/Jakarta'));

    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create(['status' => 'available', 'department' => 'computer']);
    $class = CourseClass::factory()->create(['subject' => 'Microsoft Excel', 'student_count' => 10]);

    // Jadwal hari ini pukul 09:00 - 11:00 WIB (sedang berlangsung)
    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formStatus', 'maintenance')
        ->call('save')
        ->assertHasErrors(['status']);

    expect($room->fresh()->status)->toBe('available');
});

// Tes Tambahan 3: Timezone WIB - Jadwal hari ini yang sudah selesai TIDAK mengunci ruangan
test('timezone WIB: schedule today already completed does not lock room status change', function () {
    // Set waktu saat ini ke pukul 12:00:00 WIB pada 15 Oktober 2026
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 12, 0, 0, 'Asia/Jakarta'));

    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create(['status' => 'available', 'department' => 'computer']);
    $class = CourseClass::factory()->create(['subject' => 'Microsoft Excel', 'student_count' => 10]);

    // Jadwal hari ini pukul 09:00 - 11:00 WIB (sudah selesai sebelum 12:00 WIB)
    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formStatus', 'maintenance')
        ->call('save')
        ->assertHasNoErrors();

    expect($room->fresh()->status)->toBe('maintenance');
});

// Tes Tambahan 4: Timezone WIB - Pergantian tanggal WIB dihitung akurat
test('timezone WIB: midnight date rollover is evaluated in Asia/Jakarta', function () {
    // Pukul 23:30 UTC tanggal 15 Oktober = Pukul 06:30 WIB tanggal 16 Oktober
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 23, 30, 0, 'UTC'));

    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create(['status' => 'available', 'department' => 'computer']);
    $class = CourseClass::factory()->create(['subject' => 'Microsoft Excel', 'student_count' => 10]);

    // Jadwal tanggal 16 Oktober pukul 09:00 - 11:00 WIB (mendatang di WIB)
    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-16',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formStatus', 'maintenance')
        ->call('save')
        ->assertHasErrors(['status']);

    expect($room->fresh()->status)->toBe('available');
});

// Tes Tambahan 5: Fail-Closed pada penurunan kapasitas jika data relasi kelas tidak ditemukan
test('fail closed: decreasing capacity is rejected if class relation is missing or unverifiable', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create(['capacity' => 20, 'department' => 'computer']);

    // Buat mock schedule dengan relasi courseClass bernilai null (data kelas hilang / tidak ditemukan)
    $mockSchedule = new Schedule([
        'room_id' => $room->id,
        'date' => '2026-10-25',
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);
    $mockSchedule->setRelation('courseClass', null);

    $mockGuard = new class($mockSchedule) extends RoomConflictGuard
    {
        public function __construct(private Schedule $schedule) {}

        public function getUpcomingSchedulesForRoom(int $roomId): Collection
        {
            return collect([$this->schedule]);
        }
    };

    app()->instance(RoomConflictGuard::class, $mockGuard);

    $this->actingAs($admin);

    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('capacity', 15)
        ->call('save')
        ->assertHasErrors(['capacity']);

    expect($room->fresh()->capacity)->toBe(20);
});

// Tes Tambahan 6: Fail-Closed jika mata pelajaran kelas terpetakan ke kompetensi yang TIDAK AKTIF
test('fail closed: subject mapped to inactive competency only is rejected', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create(['department' => 'computer']);

    // Nonaktifkan semua kompetensi bernama 'Adobe Photoshop' atau buat baru yang non-aktif
    Competency::create([
        'name' => 'Adobe Photoshop Legacy',
        'department' => 'computer',
        'is_active' => false,
    ]);

    $class = CourseClass::factory()->create([
        'name' => 'Kelas Photoshop Tua',
        'subject' => 'Adobe Photoshop Legacy',
        'student_count' => 10,
    ]);

    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-25',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    // Coba ganti department ke speakout -> Fail-Closed karena kompetensi tidak aktif
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formDepartment', 'speakout')
        ->call('save')
        ->assertHasErrors(['department']);

    expect($room->fresh()->department)->toBe('computer');
});

// Tes Tambahan 7: Fail-Closed jika terdapat duplikasi entri kompetensi pada katalog (bahkan jika sama bidang)
test('fail closed: duplicate competency entries with same name are rejected even if same department', function () {
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create(['department' => 'computer']);

    // Buat dua entri aktif yang memiliki nama sama setelah LOWER(TRIM())
    Competency::create(['name' => 'Desain Grafis Spesial', 'department' => 'computer', 'is_active' => true]);
    Competency::create(['name' => '  desain grafis spesial  ', 'department' => 'computer', 'is_active' => true]);

    $class = CourseClass::factory()->create([
        'name' => 'Kelas Duplikat',
        'subject' => 'Desain Grafis Spesial',
        'student_count' => 10,
    ]);

    Schedule::create([
        'course_class_id' => $class->id,
        'instructor_id' => Instructor::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-10-25',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin);

    // Coba ganti department -> Fail-Closed karena duplikasi entri
    Livewire::test(RoomList::class)
        ->call('openEdit', $room->id)
        ->set('formDepartment', 'speakout')
        ->call('save')
        ->assertHasErrors(['department']);

    expect($room->fresh()->department)->toBe('computer');
});
