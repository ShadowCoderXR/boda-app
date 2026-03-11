<?php

use Livewire\Volt\Component;
use App\Models\Guest;
use App\Models\Category;

new class extends Component {
    public $categories;
    public $selectedCategory = '';
    
    // Edit Modal State
    public ?Guest $editingGuest = null;
    public string $editRsvpStatus = 'pending';
    public string $editMenu = '';
    public string $editAllergies = '';

    public function mount()
    {
        $this->categories = Category::orderBy('name')->get();
    }

    public function with()
    {
        // Base Query
        $query = Guest::with(['group.parent', 'categories.user'])
            ->orderBy('name');

        // Filter by Category if selected
        if (!empty($this->selectedCategory)) {
            $query->whereHas('categories', function($q) {
                $q->where('categories.id', $this->selectedCategory);
            });
        }

        $guests = $query->get();

        // Group the guests by their 'group' for display
        // We'll use the group's hierarchy name as the key
        $groupedGuests = $guests->groupBy(function ($guest) {
            if (!$guest->group) return 'Sin Grupo';
            
            $groupName = $guest->group->name;
            if ($guest->group->parent) {
                $groupName = $guest->group->parent->name . ' > ' . $groupName;
            }
            return $groupName;
        })->sortKeys();

        return [
            'groupedGuests' => $groupedGuests
        ];
    }

    public function editRsvp(Guest $guest)
    {
        $this->editingGuest = $guest;
        $this->editRsvpStatus = $guest->rsvp_status;
        
        $details = $guest->rsvp_details ?? [];
        $this->editMenu = $details['menu'] ?? '';
        $this->editAllergies = $details['allergies'] ?? '';

        $this->js("Flux.modal('edit-rsvp').show()");
    }

    public function saveRsvp()
    {
        if (!$this->editingGuest) return;

        $this->validate([
            'editRsvpStatus' => 'required|in:pending,confirmed,declined',
            'editMenu' => 'nullable|string|max:255',
            'editAllergies' => 'nullable|string|max:255',
        ]);

        $details = [
            'menu' => $this->editMenu,
            'allergies' => $this->editAllergies
        ];

        $this->editingGuest->update([
            'rsvp_status' => $this->editRsvpStatus,
            'rsvp_details' => $details
        ]);

        $this->js("Flux.modal('edit-rsvp').close()");
        
        // Flux Toast notification (Assuming we fix the layout missing component!)
        // flux()->toast('RSVP Actualizado')->success();
    }
}
?>

<div>
    {{-- Header Actions / Filters --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4 mb-6">
        <div class="w-full sm:w-64">
            <flux:select wire:model.live="selectedCategory" label="Filtrar por Categoría" placeholder="Todas las categorías">
                @foreach($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        
        <flux:button icon="plus" class="bg-sage-600 hover:bg-sage-700 text-white w-full sm:w-auto">Nuevo Invitado</flux:button>
    </div>

    {{-- Guest List Grouped --}}
    <div class="space-y-8">
        @forelse($groupedGuests as $groupName => $guests)
            <div>
                <flux:heading size="lg" class="mb-3 text-stone-700 dark:text-stone-300 border-b border-stone-200 dark:border-stone-700 pb-2">
                    <flux:icon.user-group class="inline-block w-5 h-5 mr-1 -mt-1 text-stone-400" />
                    {{ $groupName }}
                </flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($guests as $guest)
                        <flux:card class="relative flex flex-col pt-4 pb-4 px-5">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-medium text-lg leading-tight">{{ $guest->name }}</h3>
                                    <p class="text-xs text-stone-500 mt-0.5">{{ '@'.$guest->slug }}</p>
                                </div>
                                <flux:button wire:click="editRsvp('{{ $guest->id }}')" size="sm" variant="ghost" icon="pencil" class="text-stone-400 hover:text-stone-700 -mr-2" />
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-2 mt-auto pt-3">
                                @if($guest->rsvp_status === 'confirmed')
                                    <flux:badge color="zinc" class="!bg-sage-100 !text-sage-700 border-0 text-xs">Confirmado</flux:badge>
                                @elseif($guest->rsvp_status === 'declined')
                                    <flux:badge color="red" class="border-0 text-xs">Declinado</flux:badge>
                                @else
                                    <flux:badge color="zinc" class="border-0 text-xs">Pendiente</flux:badge>
                                @endif

                                @foreach($guest->categories as $cat)
                                    <div class="flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-stone-100 dark:bg-stone-800 border border-stone-200 dark:border-stone-700">
                                        <div class="w-1.5 h-1.5 rounded-full bg-{{ $cat->color }}-500"></div>
                                        <span>{{ $cat->name }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="py-12 text-center text-stone-500 border-2 border-dashed border-stone-200 dark:border-stone-800 rounded-xl">
                <flux:icon.users class="w-12 h-12 mx-auto mb-3 text-stone-300" />
                <p>No se encontraron invitados con los filtros actuales.</p>
            </div>
        @endforelse
    </div>

    {{-- Edit RSVP Modal --}}
    <flux:modal name="edit-rsvp" class="min-w-[22rem]">
        <form wire:submit="saveRsvp" class="space-y-6">
            <div>
                <flux:heading size="lg">Editar RSVP</flux:heading>
                <flux:subheading>{{ $editingGuest ? $editingGuest->name : '' }}</flux:subheading>
            </div>
            
            <flux:radio.group wire:model="editRsvpStatus" label="Estado de Asistencia">
                <flux:radio value="pending" label="Pendiente" />
                <flux:radio value="confirmed" label="Confirmado" />
                <flux:radio value="declined" label="Declinado" />
            </flux:radio.group>

            <div class="space-y-4 pt-2 border-t border-stone-100 dark:border-stone-800">
                <p class="text-sm font-medium text-stone-700 dark:text-stone-300">Detalles Adicionales</p>
                
                <flux:select wire:model="editMenu" label="Preferencia de Menú" placeholder="Opcional">
                    <flux:select.option value="Estándar">Estándar</flux:select.option>
                    <flux:select.option value="Vegetariano">Vegetariano</flux:select.option>
                    <flux:select.option value="Vegano">Vegano</flux:select.option>
                    <flux:select.option value="Infantil">Infantil</flux:select.option>
                </flux:select>

                <flux:input wire:model="editAllergies" label="Alergias o Restricciones" placeholder="Ej: Cacahuates, Gluten..." />
            </div>
            
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0">Guardar Cambios</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
