<?php

use App\Models\Room;
use App\Models\User;

test('unauthenticated visitors cannot open rooms preview', function () {
    $this->get(route('rooms.index'))->assertRedirect(route('login'));
});

test('admin rooms preview shows management actions with save intentionally disabled', function () {
    $this->actingAs(User::factory()->admin()->create());
    Room::factory()->create(['name' => 'Lab Uji Nyata', 'capacity' => 18, 'status' => 'available']);

    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('Data Ruangan &amp; Lab', false)
        ->assertSee('Lab Uji Nyata')
        ->assertSee('18 orang')
        ->assertSee('Belum tercatat')
        ->assertSee('Lihat Detail')
        ->assertSee('Tambah Ruangan')
        ->assertSee('roomFormDialog')
        ->assertSee('roomDetailDialog')
        ->assertSee('disabled aria-disabled="true"', false);
});

test('baak rooms preview shows management actions with save intentionally disabled', function () {
    $this->actingAs(User::factory()->baak()->create());
    Room::factory()->create(['name' => 'Lab BAAK']);

    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('Lihat Detail')
        ->assertSee('Tambah Ruangan')
        ->assertSee('Edit Ruangan')
        ->assertSee('>Edit</button>', false)
        ->assertSee('roomFormDialog')
        ->assertSee('disabled aria-disabled="true"', false);
});

test('rooms preview hides management actions from users without room management access', function (string $userFactoryState) {
    $userFactory = User::factory();
    $user = $userFactoryState === 'other'
        ? $userFactory->create(['role' => 'staff'])
        : $userFactory->{$userFactoryState}()->create();

    $this->actingAs($user);
    Room::factory()->create(['name' => 'Lab Read Only']);

    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('Lihat Detail')
        ->assertSee('roomDetailDialog')
        ->assertDontSee('Tambah Ruangan')
        ->assertDontSee('Edit Ruangan')
        ->assertDontSee('>Edit</button>', false)
        ->assertDontSee('roomFormDialog');
})->with([
    'AO' => 'ao',
    'instructor' => 'instructor',
    'other authenticated user' => 'other',
]);
