<?php

use App\Models\Category;
use App\Models\Group;
use App\Models\Guest;
use App\Models\User;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest list component renders grouped guests with hierarchy', function () {
    // 1. Arrange
    $user = User::factory()->create();
    
    $granpaGroup = Group::create(['name' => 'Familia Silva', 'user_id' => $user->id]);
    $parentGroup = Group::create(['name' => 'Amigos Novio', 'parent_id' => $granpaGroup->id, 'user_id' => $user->id]);
    $independentGroup = Group::create(['name' => 'Compañeros de Trabajo', 'user_id' => $user->id]);

    $guest1 = Guest::create(['name' => 'Raul Silva', 'slug' => 'raul-silva', 'group_id' => $parentGroup->id, 'user_id' => $user->id]);
    $guest2 = Guest::create(['name' => 'Carlos Perez', 'slug' => 'carlos-perez', 'group_id' => $independentGroup->id, 'user_id' => $user->id]);
    
    $category = Category::create(['name' => 'VIP', 'color' => 'gold', 'user_id' => $user->id]);
    $guest1->categories()->attach($category);

    // 2. Act & Assert
    Volt::test('guests.list')
        ->assertSee('Familia Silva > Amigos Novio') // Checks hierarchical name
        ->assertSee('Compañeros de Trabajo') // Checks independent name
        ->assertSee('Raul Silva')
        ->assertSee('Carlos Perez')
        ->assertSee('VIP') // Checks category renders
        ->set('selectedCategory', $category->id) // Test reactivity/filter
        ->assertSee('Raul Silva')
        ->assertDontSee('Carlos Perez'); // Filtered out
});

test('edit rsvp modal updates guest data correctly', function () {
    // 1. Arrange
    $user = User::factory()->create();
    $guest = Guest::create([
        'name' => 'Raul Silva',
        'slug' => 'raul-silva',
        'rsvp_status' => 'pending',
        'user_id' => $user->id
    ]);

    // 2. Act
    Volt::test('guests.list')
        ->call('editRsvp', $guest->id)
        ->assertSet('editRsvpStatus', 'pending')
        ->set('editRsvpStatus', 'confirmed')
        ->set('editMenu', 'Vegetariano')
        ->set('editAllergies', 'Mani')
        ->call('saveRsvp');

    // 3. Assert
    $guest->refresh();
    expect($guest->rsvp_status)->toBe('confirmed');
    expect($guest->rsvp_details['menu'])->toBe('Vegetariano');
    expect($guest->rsvp_details['allergies'])->toBe('Mani');
});
