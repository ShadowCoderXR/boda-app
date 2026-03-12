<?php

use Livewire\Volt\Component;
use App\Models\Guest;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public int $totalGuests = 0;
    public int $confirmedGuests = 0;
    public string $topCategoryName = '-';
    public int $topCategoryCount = 0;

    public $newGuestName = '';
    public $newGuestGroupId = '';
    public $newGuestCategoryIds = [];

    public function with()
    {
        return [
            'groups' => App\Models\Group::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        $this->totalGuests = Guest::count();
        $this->confirmedGuests = Guest::where('rsvp_status', 'confirmed')->count();
        
        $topCategory = Category::withCount('guests')->orderByDesc('guests_count')->first();
        if ($topCategory) {
            $this->topCategoryName = $topCategory->name;
            $this->topCategoryCount = $topCategory->guests_count;
        }
    }

    public function saveGuest()
    {
        $this->validate([
            'newGuestName' => 'required|string|max:255',
            'newGuestGroupId' => 'nullable|exists:groups,id',
            'newGuestCategoryIds' => 'nullable|array',
            'newGuestCategoryIds.*' => 'exists:categories,id',
        ]);

        try {
            DB::beginTransaction();
            
            $guest = Guest::create([
                'name' => $this->newGuestName,
                'group_id' => $this->newGuestGroupId ?: null,
                'rsvp_status' => 'pending',
                'user_id' => auth()->id(),
                // Slug is handled by Guest model's booted method
            ]);

            if (!empty($this->newGuestCategoryIds)) {
                $guest->categories()->sync($this->newGuestCategoryIds);
            }

            DB::commit();

            $this->loadStats();
            $this->reset(['newGuestName', 'newGuestGroupId', 'newGuestCategoryIds']);
            
            $this->js("Flux.modal('add-guest').close()");
        } catch (\Exception $e) {
            DB::rollBack();
            // We'll show an error if something goes wrong
            throw $e;
        }
    }
}
?>

<div class="space-y-8 mt-6">
    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- RSVP Tracker --}}
        <flux:card class="bg-white dark:bg-stone-800">
            <div class="flex justify-between items-center mb-2">
                <div class="flex items-center gap-2">
                    <flux:icon.users class="w-5 h-5 text-sage-500" />
                    <flux:heading size="lg">RSVP Tracker</flux:heading>
                </div>
                <flux:badge color="zinc" class="!bg-sage-100 !text-sage-700 dark:!bg-sage-500/20 dark:!text-sage-300">{{ $confirmedGuests }} / {{ $totalGuests }} Confirmados</flux:badge>
            </div>
            
            @php
                $percentage = $totalGuests > 0 ? round(($confirmedGuests / $totalGuests) * 100) : 0;
            @endphp
            
            <div class="w-full bg-stone-100 dark:bg-stone-700 rounded-full h-3 mt-4 overflow-hidden">
                <div class="bg-sage-500 h-full rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
            </div>
            <p class="text-xs text-stone-500 mt-2 text-right">{{ $percentage }}% de asistencia confirmada</p>
        </flux:card>

        {{-- Top Category --}}
        <flux:card class="bg-white dark:bg-stone-800">
            <div class="flex justify-between items-center mb-2">
                <div class="flex items-center gap-2">
                    <flux:icon.chart-pie class="w-5 h-5 text-gold-500" />
                    <flux:heading size="lg">Categoría Principal</flux:heading>
                </div>
            </div>
            
            <div class="mt-4 flex flex-col items-center justify-center py-3 rounded-lg bg-stone-50 dark:bg-stone-900 border border-stone-100 dark:border-stone-700">
                <span class="text-2xl font-bold text-stone-800 dark:text-stone-100">{{ $topCategoryName }}</span>
                <span class="text-sm text-stone-500">{{ $topCategoryCount }} invitados en esta categoría</span>
            </div>
        </flux:card>
    </div>

    {{-- Quick Actions --}}
    <div class="flex flex-col gap-4">
        <flux:modal.trigger name="add-guest">
            <flux:button icon="plus" class="w-full bg-sage-600 hover:bg-sage-700 text-white border-0">Añadir Invitado</flux:button>
        </flux:modal.trigger>
        <flux:button variant="outline" icon="light-bulb" href="/inspiracion" class="w-full border-gold-500 text-gold-600 hover:bg-gold-50 dark:hover:bg-gold-500/10">Nueva Idea</flux:button>
    </div>

    {{-- Add Guest Modal --}}
    <flux:modal name="add-guest" class="min-w-[22rem]">
        <form wire:submit="saveGuest" class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo Invitado</flux:heading>
                <flux:subheading>Añade un invitado rápidamente a la lista base.</flux:subheading>
            </div>
            
            <div class="space-y-4">
                <flux:input wire:model="newGuestName" label="Nombre completo" placeholder="Ej: Juan Pérez" />

                <flux:select wire:model="newGuestGroupId" label="Grupo / Familia" placeholder="Opcional...">
                    @foreach($groups as $group)
                        <flux:select.option value="{{ $group->id }}">{{ $group->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="newGuestCategoryIds" label="Categorías" multiple placeholder="Opcional...">
                    @foreach($categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0">Guardar Invitado</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
