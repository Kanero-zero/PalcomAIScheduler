<?php

use App\Livewire\RoomList;
use App\Models\Room;
use App\Models\User;
use Livewire\Livewire;

it('requires login to view rooms', function () {
    $this->get(route('rooms.index'))->assertRedirect(route('login'));
});

it('requires email verification to view rooms', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('rooms.index'))->assertRedirect(route('verification.notice'));
});

it('shows room names, capacity and operational status', function () {
    Room::factory()->create(['name' => 'Lab Komputer A', 'capacity' => 24, 'status' => 'available']);
    $this->actingAs(User::factory()->create())
        ->get(route('rooms.index'))
        ->assertOk()
        ->assertSeeLivewire(RoomList::class)
        ->assertSee('Lab Komputer A')
        ->assertSee('24 orang')
        ->assertSee('Operasional')
        ->assertSee('Tampilan hanya baca')
        ->assertSee('Status operasional bukan jaminan ruangan kosong')
        ->assertDontSee('Informasi ketersediaan ruangan belum tersedia.');
});

it('searches rooms by name', function () {
    Room::factory()->create(['name' => 'Lab Komputer']);
    Room::factory()->create(['name' => 'Ruang Bahasa']);
    $this->actingAs(User::factory()->create());

    Livewire::test(RoomList::class)
        ->set('search', 'Komputer')
        ->assertSee('Lab Komputer')->assertDontSee('Ruang Bahasa');
});

it('filters rooms by operational status', function () {
    Room::factory()->create(['name' => 'Lab Siap Pakai', 'status' => 'available']);
    Room::factory()->create(['name' => 'Lab Perawatan', 'status' => 'maintenance']);
    $this->actingAs(User::factory()->create());

    Livewire::test(RoomList::class)
        ->assertSee('Perawatan')
        ->set('status', 'maintenance')
        ->assertSee('Lab Perawatan')
        ->assertDontSee('Lab Siap Pakai');
});

it('combines room search and status filters then resets them', function () {
    Room::factory()->create(['name' => 'Lab Aktif', 'status' => 'available']);
    Room::factory()->create(['name' => 'Lab Servis', 'status' => 'maintenance']);
    $this->actingAs(User::factory()->create());

    Livewire::test(RoomList::class)
        ->set('search', 'Lab')
        ->set('status', 'maintenance')
        ->assertSee('Lab Servis')->assertDontSee('Lab Aktif')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('status', '')
        ->assertSee('Lab Aktif');
});

it('paginates rooms and resets to first page after filtering', function () {
    foreach (range(1, 11) as $number) {
        Room::factory()->create(['name' => sprintf('Lab %02d', $number)]);
    }
    $this->actingAs(User::factory()->create());

    Livewire::test(RoomList::class)
        ->assertSee('Lab 01')->assertDontSee('Lab 11')
        ->call('nextPage')->assertSee('Lab 11')->assertDontSee('Lab 01')
        ->set('search', 'Lab 01')
        ->assertSee('Lab 01')->assertDontSee('Lab 11');
});

it('shows appropriate empty states for rooms', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(RoomList::class)->assertSee('Belum ada data ruangan.');

    Room::factory()->create(['name' => 'Lab Satu']);
    Livewire::test(RoomList::class)
        ->set('search', 'Tidak Ditemukan')
        ->assertSee('Tidak ada ruangan yang sesuai dengan filter.');
});

it('reflects changed room status on reload without editing the room', function () {
    $room = Room::factory()->create(['name' => 'Lab Utama', 'status' => 'available']);
    $this->actingAs(User::factory()->create());

    Livewire::test(RoomList::class)->assertSee('Operasional')->assertDontSee('Perawatan');
    $room->update(['status' => 'maintenance']);
    Livewire::test(RoomList::class)->assertSee('Perawatan')->assertDontSee('Operasional');
});

it('does not expose room mutation controls', function () {
    Room::factory()->create();
    $this->actingAs(User::factory()->create())
        ->get(route('rooms.index'))
        ->assertOk()
        ->assertDontSee('Tambah Ruangan')
        ->assertDontSee('Hapus Ruangan');
});
