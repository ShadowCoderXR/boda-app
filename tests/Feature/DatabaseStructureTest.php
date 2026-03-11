<?php

use App\Models\Category;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InspirationItem;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('guest uses uuid v7 for primary key', function () {
    $guest = Guest::create([
        'name' => 'Juan Perez',
        'slug' => 'juan-perez',
    ]);

    expect(Str::isUuid($guest->id))->toBeTrue();
    // Verify it is UUID version 7
    // A version 7 UUID has its 13th character as '7'
    expect($guest->id[14])->toBe('7');
});

test('group hierarchy works correctly', function () {
    $parentGroup = Group::create(['name' => 'Familia Silva']);
    $childGroup = Group::create(['name' => 'Silva Fernandez', 'parent_id' => $parentGroup->id]);

    expect($childGroup->parent->id)->toBe($parentGroup->id)
        ->and($parentGroup->children()->count())->toBe(1)
        ->and($parentGroup->children->first()->id)->toBe($childGroup->id);
});

test('guest can be linked to a group', function () {
    $group = Group::create(['name' => 'Amigos del Novio']);
    $guest = Guest::create([
        'name' => 'Carlos Lopez',
        'slug' => 'carlos-lopez',
        'group_id' => $group->id,
    ]);

    expect($guest->group->id)->toBe($group->id)
        ->and($group->guests()->count())->toBe(1);
});

test('sponsor must be linked to a guest and details json works', function () {
    $guest = Guest::create([
        'name' => 'Maria Garcia',
        'slug' => 'maria-garcia',
    ]);

    $sponsorDetails = ['task' => 'Comprar anillos', 'time' => '10:00 AM'];

    $sponsor = Sponsor::create([
        'guest_id' => $guest->id,
        'role' => 'Padrino de Anillos',
        'details' => $sponsorDetails,
    ]);

    expect($sponsor->guest->id)->toBe($guest->id)
        ->and($guest->sponsor->id)->toBe($sponsor->id)
        ->and($sponsor->details)->toBeArray()
        ->and($sponsor->details['task'])->toBe('Comprar anillos');
});

test('guest uses rsvp_details as json array', function () {
    $guest = Guest::create([
        'name' => 'Ana Torres',
        'slug' => 'ana-torres',
        'rsvp_details' => ['menu' => 'Vegano', 'allergies' => 'Ninguna'],
    ]);

    $guest->refresh();

    expect($guest->rsvp_details)->toBeArray()
        ->and($guest->rsvp_details['menu'])->toBe('Vegano');
});

test('users can have multiple roles defined by string', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ayudante = User::factory()->create(['role' => 'ayudante']);
    $invitado = User::factory()->create(['role' => 'invitado']);

    expect($admin->isAdmin())->toBeTrue()
        ->and($ayudante->isAyudante())->toBeTrue()
        ->and($invitado->isInvitado())->toBeTrue();
});

test('inspiration items cast is_favorite and have helpers', function () {
    $item = InspirationItem::create([
        'type' => 'image',
        'content' => 'photo.jpg',
        'is_favorite' => true,
    ]);

    expect($item->is_favorite)->toBeTrue()
        ->and($item->isImage())->toBeTrue()
        ->and($item->isLink())->toBeFalse();
});

