<?php

use App\Models\Guest;
use App\Models\Sponsor;
use App\Models\User;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sponsors list component renders assigned sponsors', function () {
    $user = User::factory()->create();
    $guest = Guest::create(['name' => 'Raul Silva', 'slug' => 'raul', 'user_id' => $user->id]);
    
    $sponsor = Sponsor::create([
        'guest_id' => $guest->id,
        'role' => 'Padrino de Anillos',
        'details' => ['status' => 'Confirmado', 'notes' => 'Llevará los anillos de oro'],
        'user_id' => $user->id
    ]);

    Volt::test('sponsors.list')
        ->assertSee('Raul Silva')
        ->assertSee('Padrino de Anillos')
        ->assertSee('Confirmado')
        ->assertSee('Llevará los anillos de oro');
});

test('user can assign a guest as a new sponsor', function () {
    $user = User::factory()->create();
    $guest = Guest::create(['name' => 'Ana Torres', 'slug' => 'ana', 'user_id' => $user->id]);

    Volt::test('sponsors.list')
        ->set('newGuestId', $guest->id)
        ->set('newRole', 'Madrina de Lazo')
        ->call('assignSponsor');

    $this->assertDatabaseHas('sponsors', [
        'guest_id' => $guest->id,
        'role' => 'Madrina de Lazo'
    ]);

    $sponsor = Sponsor::where('guest_id', $guest->id)->first();
    expect($sponsor->details['status'])->toBe('Tentativo');
});

test('user can edit an existing sponsor status and notes', function () {
    $user = User::factory()->create();
    $guest = Guest::create(['name' => 'Raul Silva', 'slug' => 'raul', 'user_id' => $user->id]);
    
    $sponsor = Sponsor::create([
        'guest_id' => $guest->id,
        'role' => 'Padrino de Arras',
        'details' => ['status' => 'Tentativo', 'notes' => 'Falta confirmar'],
        'user_id' => $user->id
    ]);

    Volt::test('sponsors.list')
        ->call('editSponsor', $sponsor->id)
        ->assertSet('editRoles', 'Padrino de Arras')
        ->set('editStatus', 'Hecho')
        ->set('editNotes', 'Ya las compró')
        ->call('saveSponsor');

    $sponsor->refresh();
    expect($sponsor->details['status'])->toBe('Hecho');
    expect($sponsor->details['notes'])->toBe('Ya las compró');
});
