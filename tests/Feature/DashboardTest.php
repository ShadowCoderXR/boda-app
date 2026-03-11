<?php

use App\Models\User;
use Livewire\Livewire; // Added Livewire import

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('guest list page requires authentication', function () {
    $this->get('/invitados')->assertRedirect('/login');
});

test('authenticated user can view guest list', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/invitados')->assertOk();
});