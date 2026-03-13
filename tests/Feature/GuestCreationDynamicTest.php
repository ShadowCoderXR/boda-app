<?php

use App\Models\Group;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('can create guest with new group and new category from guest list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('guests.list')
        ->set('repName', 'Invitado Dinamico')
        ->set('newGroupName', 'Nueva Familia Pro')
        ->set('newCategoryName', 'Categoría Flash')
        ->call('saveGroup')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('groups', ['name' => 'Nueva Familia Pro']);
    $this->assertDatabaseHas('categories', ['name' => 'Categoría Flash']);

    $guest = Guest::where('name', 'Invitado Dinamico')->first();
    expect($guest->group->name)->toBe('Nueva Familia Pro');
    expect($guest->categories->first()->name)->toBe('Categoría Flash');
});

test('can create guest with existing group and new category', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $group = Group::create(['name' => 'Grupo Existente', 'user_id' => $user->id]);

    Volt::test('guests.list')
        ->set('repName', 'Invitado Mixto')
        ->set('groupId', $group->id)
        ->set('newCategoryName', 'Etiqueta Nueva')
        ->call('saveGroup')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('categories', ['name' => 'Etiqueta Nueva']);

    $guest = Guest::where('name', 'Invitado Mixto')->first();
    expect($guest->group_id)->toBe($group->id);
    expect($guest->categories->first()->name)->toBe('Etiqueta Nueva');
});
