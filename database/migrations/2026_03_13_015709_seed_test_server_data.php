<?php

use App\Models\Category;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InspirationItem;
use App\Models\Subgroup;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $admin = User::where('username', 'admin')->first();
        if (! $admin) {
            return;
        }

        // 1. Groups & Subgroups
        $parentGrupo1 = Subgroup::firstOrCreate(
            ['name' => 'Familia Silva'],
            ['user_id' => $admin->id]
        );
        $subGrupoSilva = Group::firstOrCreate(
            ['name' => 'Familia Silva López'],
            [
                'subgroup_id' => $parentGrupo1->id,
                'user_id' => $admin->id,
            ]
        );

        $grupo2 = Group::firstOrCreate(
            ['name' => 'Amigos Novia'],
            ['user_id' => $admin->id]
        );

        // 2. Categories
        $catVIP = Category::firstOrCreate(
            ['name' => 'VIP', 'type' => 'guest'],
            ['color' => 'gold', 'user_id' => $admin->id]
        );
        $catAmigos = Category::firstOrCreate(
            ['name' => 'Amigos', 'type' => 'guest'],
            ['color' => 'sage', 'user_id' => $admin->id]
        );
        $catGeneral = Category::firstOrCreate(
            ['name' => 'General', 'type' => 'task'],
            ['color' => 'zinc', 'user_id' => $admin->id]
        );

        // 3. Guests
        $raul = Guest::updateOrCreate(
            ['slug' => 'raul-silva'],
            [
                'name' => 'Raul Silva',
                'group_id' => $subGrupoSilva->id,
                'rsvp_status' => 'confirmed',
                'is_representative' => true,
                'extra_spots' => 1,
            ]
        );
        $raul->categories()->syncWithoutDetaching([$catVIP->id]);

        $maria = Guest::updateOrCreate(
            ['slug' => 'maria-fernandez'],
            [
                'name' => 'Maria Fernandez',
                'group_id' => $subGrupoSilva->id,
                'representative_id' => $raul->id,
                'rsvp_status' => 'pending',
            ]
        );

        $ana = Guest::updateOrCreate(
            ['slug' => 'ana-torres'],
            [
                'name' => 'Ana Torres',
                'group_id' => $grupo2->id,
                'rsvp_status' => 'confirmed',
                'is_representative' => true,
            ]
        );
        $ana->categories()->syncWithoutDetaching([$catAmigos->id]);

        // 4. Tasks
        Task::updateOrCreate(
            ['title' => 'Reservar el Salón Principal', 'user_id' => $admin->id],
            [
                'description' => 'Contactar a la Hacienda del Sagrario para el depósito inicial.',
                'due_date' => now()->addDays(7),
                'priority' => 'alta',
                'status' => 'pending',
                'category_id' => $catGeneral->id,
            ]
        );

        Task::updateOrCreate(
            ['title' => 'Confirmar Menú Premium', 'user_id' => $admin->id],
            [
                'description' => 'Revisar opciones de platillos con el banquete.',
                'due_date' => now()->addDays(14),
                'priority' => 'media',
                'status' => 'pending',
                'category_id' => $catGeneral->id,
                'external_guest_id' => $ana->id,
            ]
        );

        // 5. Inspiration
        InspirationItem::firstOrCreate(
            ['content' => '#A3B18A', 'type' => 'color'],
            ['description' => 'Verde Sage', 'user_id' => $admin->id]
        );
        InspirationItem::firstOrCreate(
            ['content' => 'https://pinterest.com/example', 'type' => 'link'],
            ['description' => 'Ideas de Centros de Mesa', 'user_id' => $admin->id]
        );
    }

    public function down(): void
    {
        // Delete test data
        $admin = User::where('username', 'admin')->first();
        if ($admin) {
            Task::where('user_id', $admin->id)->delete();
            InspirationItem::where('user_id', $admin->id)->delete();
            Guest::whereIn('slug', ['raul-silva', 'maria-fernandez', 'ana-torres'])->delete();
            Group::where('user_id', $admin->id)->delete();
            Subgroup::where('user_id', $admin->id)->delete();
            Category::where('user_id', $admin->id)->delete();
        }
    }
};
