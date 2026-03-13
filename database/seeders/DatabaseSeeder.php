<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InspirationItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. User Roles & Initial Users
        $this->call(UserRoleSeeder::class);

        $admin = User::where('username', 'admin')->first();

        // 2. Groups (Hierarchical)
        $parentGrupo1 = \App\Models\Subgroup::create(['name' => 'Familia Silva', 'user_id' => $admin->id]);
        $subGrupoSilva = Group::create([
            'name' => 'Familia Silva López',
            'subgroup_id' => $parentGrupo1->id,
            'user_id' => $admin->id,
        ]);

        $grupo2 = Group::create(['name' => 'Amigos Novia', 'user_id' => $admin->id]);
        $grupo3 = Group::create(['name' => 'Trabajo Novio', 'user_id' => $admin->id]);

        // 3. Categories
        $catVIP = Category::create(['name' => 'VIP', 'color' => 'gold', 'user_id' => $admin->id]);
        $catAmigos = Category::create(['name' => 'Amigos', 'color' => 'sage', 'user_id' => $admin->id]);

        // 4. Guests (Group-Centric)
        // Family Silva Representative
        $raul = Guest::create([
            'name' => 'Raul Silva',
            'slug' => 'raul-silva',
            'group_id' => $subGrupoSilva->id,
            'rsvp_status' => 'confirmed',
            'is_representative' => true,
            'extra_spots' => 1,
        ]);
        $raul->categories()->attach($catVIP);

        // Members of Raul's group
        Guest::create([
            'name' => 'Maria Fernandez',
            'slug' => 'maria-fernandez',
            'group_id' => $subGrupoSilva->id,
            'representative_id' => $raul->id,
            'rsvp_status' => 'pending',
        ]);

        // Another Individual Guest
        $ana = Guest::create([
            'name' => 'Ana Torres',
            'slug' => 'ana-torres',
            'group_id' => $grupo2->id,
            'rsvp_status' => 'confirmed',
            'is_representative' => true,
        ]);
        $ana->categories()->attach($catAmigos);

        // 5. Inspiration Items
        InspirationItem::create(['type' => 'color', 'category_id' => null, 'content' => '#A3B18A', 'description' => 'Verde Sage', 'user_id' => $admin->id]);
        InspirationItem::create(['type' => 'color', 'category_id' => null, 'content' => '#D4AF37', 'description' => 'Oro Metálico', 'user_id' => $admin->id]);
        InspirationItem::create(['type' => 'link', 'category_id' => null, 'content' => 'https://pinterest.com/example', 'description' => 'Idea de vestido novia', 'user_id' => $admin->id]);
    }
}
