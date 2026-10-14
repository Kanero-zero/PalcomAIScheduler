<?php

use App\Models\Room;
use App\Models\User;

test('unauthenticated visitors cannot open rooms preview', function () {
    $this->get(route('rooms.index'))->assertRedirect(route('login'));
});

test('rooms preview shows approved actions with save intentionally disabled', function () {
    $this->actingAs(User::factory()->create());
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
