<?php

use App\Models\Guest;
use App\Models\SeatingTable;
use App\Models\User;
use Livewire\Volt\Volt;

test('auto-provisions 10 tables on first load', function () {
    $user = User::factory()->create();
    
    expect(SeatingTable::where('user_id', $user->id)->count())->toBe(0);

    Volt::actingAs($user)->test('seating.seating-manager');

    expect(SeatingTable::where('user_id', $user->id)->count())->toBe(10);
});

test('can add and delete tables', function () {
    $user = User::factory()->create();
    
    Volt::actingAs($user)
        ->test('seating.seating-manager')
        ->call('addTable')
        ->assertHasNoErrors();
        
    expect(SeatingTable::where('user_id', $user->id)->count())->toBe(11); // 10 autoprovisioned + 1
    
    $table = SeatingTable::where('user_id', $user->id)->first();
    
    Volt::actingAs($user)
        ->test('seating.seating-manager')
        ->call('deleteTable', $table->id)
        ->assertHasNoErrors();
        
    expect(SeatingTable::where('user_id', $user->id)->count())->toBe(10);
});

test('can assign a guest manually', function () {
    $user = User::factory()->create();
    $guest = Guest::factory()->create([
        'user_id' => $user->id,
        'rsvp_status' => 'confirmed'
    ]);
    
    $component = Volt::actingAs($user)->test('seating.seating-manager');
    $table = SeatingTable::where('user_id', $user->id)->first();
    
    $component->set('selectedTableIdForAdd', $table->id)
        ->set('selectedGuestIdToAdd', $guest->id)
        ->call('addGuestToTable')
        ->assertHasNoErrors();
        
    expect($guest->fresh()->seating_table_id)->toBe($table->id);
});

test('auto-assigns unseated guests to tables based on capacity', function () {
    $user = User::factory()->create();
    Guest::factory(12)->create([
        'user_id' => $user->id,
        'rsvp_status' => 'confirmed'
    ]);
    
    $component = Volt::actingAs($user)->test('seating.seating-manager');
    $tables = SeatingTable::where('user_id', $user->id)->get()->sortBy(function($table) {
        return (int) filter_var($table->name, FILTER_SANITIZE_NUMBER_INT);
    })->values();
    
    foreach($tables as $t) {
        $t->update(['capacity' => 10]);
    }
    
    $component->call('autoAssign')->assertHasNoErrors();
        
    expect($tables[0]->guests()->count())->toBe(10);
    expect($tables[1]->guests()->count())->toBe(2);
});

test('auto-assigns unseated guests starting from specific table', function () {
    $user = User::factory()->create();
    Guest::factory(5)->create([
        'user_id' => $user->id,
        'rsvp_status' => 'confirmed'
    ]);
    
    Volt::actingAs($user)->test('seating.seating-manager');
    $tables = SeatingTable::where('user_id', $user->id)->get()->sortBy(function($table) {
        return (int) filter_var($table->name, FILTER_SANITIZE_NUMBER_INT);
    })->values();
    
    Volt::actingAs($user)->test('seating.seating-manager')
        ->set('startTableId', $tables[3]->id)
        ->call('autoAssign')
        ->assertHasNoErrors();
        
    expect($tables[0]->guests()->count())->toBe(0);
    expect($tables[3]->guests()->count())->toBe(5);
});

test('can assign single guest to target table', function () {
    $user = User::factory()->create();
    $guest = Guest::factory()->create(['user_id' => $user->id, 'rsvp_status' => 'confirmed']);
    Volt::actingAs($user)->test('seating.seating-manager');
    $tables = SeatingTable::where('user_id', $user->id)->get()->sortBy(function($table) {
        return (int) filter_var($table->name, FILTER_SANITIZE_NUMBER_INT);
    })->values();
    
    Volt::actingAs($user)->test('seating.seating-manager')
        ->set('startTableId', $tables[5]->id)
        ->call('assignIndividual', $guest->id)
        ->assertHasNoErrors();
        
    expect($guest->fresh()->seating_table_id)->toBe($tables[5]->id);
});

test('can swap two guests successfully', function () {
    $user = User::factory()->create();
    
    Volt::actingAs($user)->test('seating.seating-manager');
    $tables = SeatingTable::where('user_id', $user->id)->take(2)->get();

    $guest1 = Guest::factory()->create(['user_id' => $user->id, 'rsvp_status' => 'confirmed', 'seating_table_id' => $tables[0]->id]);
    $guest2 = Guest::factory()->create(['user_id' => $user->id, 'rsvp_status' => 'confirmed', 'seating_table_id' => $tables[1]->id]);
    
    Volt::actingAs($user)
        ->test('seating.seating-manager')
        ->call('startSwap', $guest1->id)
        ->call('startSwap', $guest2->id)
        ->assertHasNoErrors();
        
    expect($guest1->fresh()->seating_table_id)->toBe($tables[1]->id);
    expect($guest2->fresh()->seating_table_id)->toBe($tables[0]->id);
});
