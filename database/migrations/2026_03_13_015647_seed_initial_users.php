<?php

use App\Enums\UserRole;
use App\Models\EventConfig;
use App\Models\Group;
use App\Models\Guest;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $password = Hash::make('password123');

        // 1. Create Admin
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador',
                'password' => $password,
                'temporary_password' => 'password123',
                'role' => UserRole::Admin,
            ]
        );

        // 2. Create Novios Group
        $hostsGroup = Group::firstOrCreate(['name' => 'Novios'], ['user_id' => $admin->id]);

        $users = [
            ['name' => 'El Novio', 'username' => 'novio', 'role' => UserRole::Novio],
            ['name' => 'La Novia', 'username' => 'novia', 'role' => UserRole::Novia],
            ['name' => 'Padrino 1', 'username' => 'padrino1', 'role' => UserRole::Padrino1],
            ['name' => 'Padrino 2', 'username' => 'padrino2', 'role' => UserRole::Padrino2],
            ['name' => 'Padrino 3', 'username' => 'padrino3', 'role' => UserRole::Padrino3],
            ['name' => 'Madrina 1', 'username' => 'madrina1', 'role' => UserRole::Madrina1],
            ['name' => 'Madrina 2', 'username' => 'madrina2', 'role' => UserRole::Madrina2],
            ['name' => 'Madrina 3', 'username' => 'madrina3', 'role' => UserRole::Madrina3],
            ['name' => 'Colaborador General', 'username' => 'colaborador', 'role' => UserRole::Colaborador],
        ];

        $sponsorRoles = [
            UserRole::Padrino1->value => 'Padrino de anillo',
            UserRole::Padrino2->value => 'Padrino testigo del civil',
            UserRole::Padrino3->value => 'Padrino católico',
            UserRole::Madrina1->value => 'Madrina de ramo',
            UserRole::Madrina2->value => 'Testigo del civil',
            UserRole::Madrina3->value => 'Madrina católica',
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['username' => $userData['username']],
                [
                    'name' => $userData['name'],
                    'password' => $password,
                    'temporary_password' => 'password123',
                    'role' => $userData['role'],
                ]
            );

            if (! in_array($user->role, [UserRole::Novio, UserRole::Novia])) {
                $guest = Guest::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'name' => $user->name,
                        'is_representative' => true,
                        'rsvp_status' => 'confirmed',
                    ]
                );

                if (isset($sponsorRoles[$user->role->value])) {
                    Sponsor::updateOrCreate(
                        ['guest_id' => $guest->id],
                        [
                            'role' => $sponsorRoles[$user->role->value],
                            'user_id' => $admin->id,
                        ]
                    );
                }
            }
        }

        // 3. Default Event Config
        EventConfig::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'wedding_date' => now()->addMonths(6)->format('Y-m-d'),
                'wedding_time' => '18:00',
                'venue_name' => 'Hacienda del Sagrario',
                'venue_address' => 'Carr. a Chapala 123, Zapopan, Jal.',
                'venue_map_link' => 'https://maps.app.goo.gl/example',
                'reception_details' => 'Cena de gala y brindis a partir de las 20:00 hrs.',
                'dress_code' => 'Formal / Etiqueta',
            ]
        );
    }

    public function down(): void
    {
        // We typically don't delete users in down() for these special seed migrations
        // to avoid accidental data loss if rolled back, but since this is for testing:
        User::whereIn('username', ['admin', 'novio', 'novia', 'padrino1', 'padrino2', 'padrino3', 'madrina1', 'madrina2', 'madrina3', 'colaborador'])->delete();
    }
};
