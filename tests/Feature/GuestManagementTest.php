<?php

use App\Models\Group;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('can delete multiple guests from list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $guests = Guest::factory()->count(3)->create(['user_id' => $user->id]);

    Volt::test('guests.list')
        ->set('selectedGuestIds', $guests->pluck('id')->toArray())
        ->call('deleteSelected')
        ->assertHasNoErrors();

    foreach ($guests as $guest) {
        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
    }
});

test('can edit guest full information from detail view', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $group = Group::create(['name' => 'Grupo A', 'user_id' => $user->id]);
    $guest = Guest::factory()->create(['user_id' => $user->id, 'group_id' => null, 'name' => 'Original Name']);

    Volt::test('guests.detail', ['guest' => $guest])
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->set('phone', '999999999')
        ->set('groupName', 'Nueva Familia')
        ->set('rsvp_status', 'confirmed')
        ->call('save')
        ->assertHasNoErrors();

    $guest->refresh();
    expect($guest->name)->toBe('Updated Name');
    expect($guest->email)->toBe('updated@example.com');
    expect($guest->group->name)->toBe('Nueva Familia');
    expect($guest->rsvp_status)->toBe('confirmed');
});
