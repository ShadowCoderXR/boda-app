<?php

use App\Models\Category;
use App\Models\Group;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('guest list component renders grouped guests with hierarchy', function () {
    // 1. Arrange
    $user = User::factory()->create();

    $granpaGroup = \App\Models\Subgroup::create(['name' => 'Familia Silva', 'user_id' => $user->id]);
    $parentGroup = Group::create(['name' => 'Amigos Novio', 'subgroup_id' => $granpaGroup->id, 'user_id' => $user->id]);
    $independentGroup = Group::create(['name' => 'Compañeros de Trabajo', 'user_id' => $user->id]);

    $guest1 = Guest::create(['name' => 'Raul Silva', 'slug' => 'raul-silva', 'group_id' => $parentGroup->id, 'user_id' => $user->id]);
    $guest2 = Guest::create(['name' => 'Carlos Perez', 'slug' => 'carlos-perez', 'group_id' => $independentGroup->id, 'user_id' => $user->id]);

    $category = Category::create(['name' => 'VIP', 'color' => 'gold', 'user_id' => $user->id]);
    $guest1->categories()->attach($category);

    // 2. Act & Assert
    Volt::test('guests.list')
        ->assertSee('Familia Silva')
        ->assertSee('Amigos Novio')
        ->assertSee('Compañeros de Trabajo')
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
        'user_id' => $user->id,
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

test('can create guest with multiple categories', function () {
    // 1. Arrange
    $user = User::factory()->create();
    $this->actingAs($user);

    $cat1 = Category::create(['name' => 'Amigos', 'color' => 'sage', 'user_id' => $user->id]);
    $cat2 = Category::create(['name' => 'VIP', 'color' => 'gold', 'user_id' => $user->id]);

    // 2. Act
    Volt::test('guests.list')
        ->set('repName', 'John Doe')
        ->set('categoryIds', [$cat1->id, $cat2->id])
        ->call('saveGroup');

    // 3. Assert
    $guest = Guest::where('name', 'John Doe')->first();
    expect($guest)->not->toBeNull();
    expect($guest->categories)->toHaveCount(2);
    expect($guest->categories->pluck('id'))->toContain($cat1->id, $cat2->id);
});
