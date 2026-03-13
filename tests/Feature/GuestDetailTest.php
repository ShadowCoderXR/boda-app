<?php

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can view guest details with all information', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $guest = Guest::create([
        'name' => 'Alejandro Garcia',
        'slug' => 'alejandro',
        'email' => 'alejandro@example.com',
        'phone' => '1234567890',
        'rsvp_status' => 'confirmed',
        'rsvp_details' => ['menu' => 'Vegetariano', 'allergies' => 'Gluten'],
        'user_id' => $user->id,
    ]);

    $response = $this->get('/invitados/alejandro');

    $response->assertStatus(200)
        ->assertSee('Alejandro Garcia')
        ->assertSee('alejandro@example.com')
        ->assertSee('1234567890')
        ->assertSee('Vegetariano')
        ->assertSee('Gluten')
        ->assertSee('/invitacion/alejandro');
});
