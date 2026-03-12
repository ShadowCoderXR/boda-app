<?php

use App\Models\InspirationItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('gallery renders color and link items correctly', function () {
    $user = User::factory()->create();

    InspirationItem::create([
        'type' => 'color',
        'category' => 'Paletas',
        'content' => json_encode(['#A3B18A']),
        'description' => 'Verde Sage',
        'user_id' => $user->id
    ]);

    InspirationItem::create([
        'type' => 'link',
        'category' => 'Vestidos',
        'content' => 'https://example.com/dress',
        'description' => 'Mi vestido favorito',
        'user_id' => $user->id
    ]);

    Volt::test('inspiration.gallery')
        ->assertSee('Verde Sage')
        ->assertSee('Mi vestido favorito')
        ->assertSee('example.com/dress');
});

test('user can upload a color idea to the gallery', function () {
    $user = User::factory()->create();

    Volt::test('inspiration.gallery')
        ->set('newType', 'color')
        ->set('newCategory', 'Decoración')
        ->set('newDescription', 'Dorado metálizado')
        ->set('newColors', ['#D4AF37'])
        ->call('saveIdea');

    $this->assertDatabaseHas('inspiration_items', [
        'type' => 'color',
        'category' => 'Decoración',
        'content' => json_encode(['#D4AF37']),
        'description' => 'Dorado metálizado'
    ]);
});

test('user can upload an image idea to the gallery', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('vestido.jpg');

    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('inspiration.gallery')
        ->set('newType', 'image')
        ->set('newCategory', 'Vestido')
        ->set('newDescription', 'Foto de vestido')
        ->set('newImage', $file)
        ->call('saveIdea')
        ->assertHasNoErrors();

    $item = InspirationItem::where('type', 'image')->first();
    expect($item)->not->toBeNull();
    expect($item->category)->toBe('Vestido');
    expect($item->content)->toContain('/storage/inspiration/');
    
    // Check if file actually exists on fake disk
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $item->content));
});

test('user can toggle favorite status', function () {
    $item = InspirationItem::create([
        'type' => 'color',
        'category' => 'Paletas',
        'content' => '#A3B18A',
        'is_favorite' => false,
    ]);

    Volt::test('inspiration.gallery')
        ->call('toggleFavorite', $item->id);

    expect($item->fresh()->is_favorite)->toBeTrue();

    Volt::test('inspiration.gallery')
        ->call('toggleFavorite', $item->id);

    expect($item->fresh()->is_favorite)->toBeFalse();
});
