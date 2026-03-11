<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InspirationItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin User
        $admin = User::firstOrCreate([
            'email' => 'admin@boda.com'
        ], [
            'name' => 'Manager',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        // 2. Groups
        $grupo1 = Group::create(['name' => 'Familia Silva', 'user_id' => $admin->id]);
        $grupo2 = Group::create(['name' => 'Amigos Novia', 'user_id' => $admin->id]);
        $grupo3 = Group::create(['name' => 'Trabajo Novio', 'user_id' => $admin->id]);

        // 3. Categories
        $catVIP = Category::create(['name' => 'VIP', 'color' => 'gold', 'user_id' => $admin->id]);
        $catAmigos = Category::create(['name' => 'Amigos', 'color' => 'sage', 'user_id' => $admin->id]);

        // 4. Guests
        $guestsData = [
            ['name' => 'Raul Silva', 'slug' => 'raul-silva', 'group_id' => $grupo1->id, 'rsvp_status' => 'confirmed'],
            ['name' => 'Maria Fernandez', 'slug' => 'maria-fernandez', 'group_id' => $grupo1->id, 'rsvp_status' => 'pending'],
            ['name' => 'Juan Silva', 'slug' => 'juan-silva', 'group_id' => $grupo1->id, 'rsvp_status' => 'declined'],
            ['name' => 'Ana Torres', 'slug' => 'ana-torres', 'group_id' => $grupo2->id, 'rsvp_status' => 'confirmed'],
            ['name' => 'Pedro Jimenez', 'slug' => 'pedro-jimenez', 'group_id' => $grupo2->id, 'rsvp_status' => 'confirmed'],
            ['name' => 'Laura Pausini', 'slug' => 'laura-pausini', 'group_id' => $grupo2->id, 'rsvp_status' => 'pending'],
            ['name' => 'Carlos Vives', 'slug' => 'carlos-vives', 'group_id' => $grupo3->id, 'rsvp_status' => 'pending'],
            ['name' => 'Shakira Mebarak', 'slug' => 'shakira', 'group_id' => $grupo3->id, 'rsvp_status' => 'declined'],
            ['name' => 'Juanes', 'slug' => 'juanes', 'group_id' => $grupo3->id, 'rsvp_status' => 'confirmed'],
            ['name' => 'Bizarrap', 'slug' => 'bizarrap', 'group_id' => $grupo3->id, 'rsvp_status' => 'confirmed'],
        ];

        foreach ($guestsData as $index => $g) {
            $guest = Guest::create($g + ['user_id' => $admin->id]);
            if ($index < 3) {
                $guest->categories()->attach($catVIP);
            } else {
                $guest->categories()->attach($catAmigos);
            }
        }

        // 5. Inspiration Items
        InspirationItem::create(['type' => 'color', 'category' => 'Paleta', 'content' => '#A3B18A', 'description' => 'Verde Sage', 'user_id' => $admin->id]);
        InspirationItem::create(['type' => 'color', 'category' => 'Paleta', 'content' => '#D4AF37', 'description' => 'Oro Metálico', 'user_id' => $admin->id]);
        InspirationItem::create(['type' => 'link', 'category' => 'Vestido', 'content' => 'https://pinterest.com/example', 'description' => 'Idea de vestido novia', 'user_id' => $admin->id]);
    }
}
