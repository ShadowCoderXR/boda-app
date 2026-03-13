<?php

use Livewire\Volt\Component;
use App\Models\Category;
use App\Models\Group;
use App\Models\Subgroup;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    public $categories;
    public $selectedCategory = '';
    
    // Edit Modal State (RSVP only)
    public ?Guest $editingGuest = null;
    public string $editRsvpStatus = 'pending';
    public string $editMenu = '';
    public string $editAllergies = '';

    // State for Bulk Actions
    public $manageMode = false;
    public $selectedGuestIds = [];

    // New Family/Group State
    public $repName = '';
    public $repEmail = '';
    public $repPhone = '';
    public $members = []; // Array of names
    public $extraSpots = 0;
    
    public $groupId = '';
    public $newGroupName = '';
    public $parentGroupId = '';
    public $groupSearch = '';
    public $categoryIds = [];
    public $newCategoryName = '';

    public function mount()
    {
        $this->categories = Category::orderBy('name')->get();
    }

    public function toggleManageMode()
    {
        $this->manageMode = !$this->manageMode;
        $this->selectedGuestIds = [];
    }

    public function deleteSelected()
    {
        if (empty($this->selectedGuestIds)) return;

        // Use get()->each->delete() to trigger Eloquent events for cascading
        Guest::whereIn('id', $this->selectedGuestIds)->get()->each->delete();
        
        $this->selectedGuestIds = [];
        $this->manageMode = false;

        $this->js("Flux.modal('confirm-delete').close()");
    }

    public function with()
    {
        $query = Guest::with(['group', 'categories'])
            ->orderBy('name');

        if (!empty($this->selectedCategory)) {
            $query->whereHas('categories', function($q) {
                $q->where('categories.id', $this->selectedCategory);
            });
        }

        $guests = $query->get();

        $groupedGuests = $guests->groupBy(function ($guest) {
            if (!$guest->group) return 'Sin Grupo';
            
            $main = $guest->group->subgroup ? $guest->group->subgroup->name : 'Sin Familia Extendida';
            $sub = $guest->group->name;
            
            return $main . '|' . $sub;
        })->sortKeys();

        return [
            'groupedGuests' => $groupedGuests,
            'groups' => Group::with('subgroup')->orderBy('name')->get(),
            'potentialParents' => Subgroup::where('user_id', auth()->id())
                ->where('name', 'like', '%'.$this->groupSearch.'%')
                ->orderBy('name')
                ->get(),
            'totalSelected' => count($this->selectedGuestIds),
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

        $this->editingGuest->update([
            'rsvp_status' => $this->editRsvpStatus,
            'rsvp_details' => [
                'menu' => $this->editMenu,
                'allergies' => $this->editAllergies,
            ]
        ]);

        // Cascade to members if representative
        if ($this->editingGuest->is_representative) {
            $this->editingGuest->members()->update([
                'rsvp_status' => $this->editRsvpStatus
            ]);
        }

        $this->js("Flux.modal('edit-rsvp').close()");
    }

    public function addMemberField()
    {
        $this->members[] = '';
    }

    public function removeMemberField($index)
    {
        unset($this->members[$index]);
        $this->members = array_values($this->members);
    }

    public function saveGroup()
    {
        $this->validate([
            'repName' => 'required|string|max:255',
            'repEmail' => 'nullable|email|max:255',
            'repPhone' => 'nullable|string|max:20',
            'members.*' => 'nullable|string|max:255',
            'extraSpots' => 'required|integer|min:0',
            'groupId' => 'nullable|exists:groups,id',
            'newGroupName' => 'nullable|string|max:255',
            'parentGroupId' => 'nullable|string|max:255',
            'categoryIds' => 'nullable|array',
            'categoryIds.*' => 'exists:categories,id',
            'newCategoryName' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // 1. Group Logic
            $finalGroupId = $this->groupId ?: null;

            // Handle parent group if provided (could be ID or name)
            $actualParentId = null;
            if (!empty($this->parentGroupId)) {
                if (is_numeric($this->parentGroupId)) {
                    $actualParentId = $this->parentGroupId;
                } else {
                    // Create new parent group
                    $newParent = Subgroup::create([
                        'name' => $this->parentGroupId,
                        'user_id' => auth()->id()
                    ]);
                    $actualParentId = $newParent->id;
                }
            }

            if (!empty($this->newGroupName)) {
                $existingGroup = Group::where('user_id', auth()->id())
                    ->where('name', $this->newGroupName)
                    ->where('subgroup_id', $actualParentId)
                    ->first();

                if ($existingGroup) {
                    $finalGroupId = $existingGroup->id;
                } else {
                    $newGroup = Group::create([
                        'name' => $this->newGroupName,
                        'subgroup_id' => $actualParentId,
                        'user_id' => auth()->id()
                    ]);
                    $finalGroupId = $newGroup->id;
                }
            }

            // 2. Category Logic
            $finalCategoryIds = $this->categoryIds;
            if (!empty($this->newCategoryName)) {
                $newCat = Category::create([
                    'name' => $this->newCategoryName,
                    'color' => 'stone',
                    'user_id' => auth()->id()
                ]);
                $finalCategoryIds[] = $newCat->id;
            }

            // 3. Create Representative
            $representative = Guest::create([
                'name' => $this->repName,
                'email' => $this->repEmail,
                'phone' => $this->repPhone,
                'group_id' => $finalGroupId,
                'is_representative' => true,
                'extra_spots' => $this->extraSpots,
                'rsvp_status' => 'pending',
            ]);

            if (!empty($finalCategoryIds)) {
                $representative->categories()->sync($finalCategoryIds);
            }

            // 4. Create Members
            foreach ($this->members as $memberName) {
                if (empty(trim($memberName))) continue;
                
                $member = Guest::create([
                    'name' => $memberName,
                    'group_id' => $finalGroupId,
                    'representative_id' => $representative->id,
                    'is_representative' => false,
                    'rsvp_status' => 'pending',
                ]);

                if (!empty($finalCategoryIds)) {
                    $member->categories()->sync($finalCategoryIds);
                }
            }

            DB::commit();

            $this->reset(['repName', 'repEmail', 'repPhone', 'members', 'extraSpots', 'groupId', 'newGroupName', 'parentGroupId', 'categoryIds', 'newCategoryName']);
            $this->categories = Category::orderBy('name')->get(); // Refresh categories
            $this->js("Flux.modal('add-group').close()");
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
?>

<div>
    {{-- Header Actions / Filters --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
            <div class="w-full sm:w-64">
                <flux:select wire:model.live="selectedCategory" label="Filtrar por Categoría" placeholder="Todas las categorías">
                    @foreach($categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex gap-2 items-end">
                <flux:button 
                    wire:click="toggleManageMode" 
                    :variant="$manageMode ? 'primary' : 'outline'"
                    icon="trash"
                    class="{{ $manageMode ? 'bg-red-500 hover:bg-red-600 text-white border-0' : '' }}"
                    wire:target="toggleManageMode"
                >
                    {{ $manageMode ? 'Cancelar' : 'Gestionar' }}
                </flux:button>

                @if($manageMode && count($selectedGuestIds) > 0)
                    <flux:modal.trigger name="confirm-delete">
                        <flux:button variant="danger" icon="check">Eliminar ({{ count($selectedGuestIds) }})</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        </div>
        
        <flux:modal.trigger name="add-group">
            <flux:button icon="plus" class="bg-sage-600 hover:bg-sage-700 text-white w-full sm:w-auto">Añadir Familia / Grupo</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Guest List Grouped --}}
    <div class="space-y-8">
        @forelse($groupedGuests as $groupName => $guests)
            <div>
                @php
                    $parts = explode('|', $groupName);
                    $mainGroup = $parts[0];
                    $subGroup = $parts[1] ?? '';
                @endphp
                <div class="mb-4">
                    <flux:heading size="lg" class="text-stone-800 dark:text-stone-100 font-black">
                        <flux:icon.user-group class="inline-block w-5 h-5 mr-1 -mt-1 text-sage-400" />
                        {{ $mainGroup }}
                    </flux:heading>
                    @if($subGroup)
                        <flux:subheading class="ml-7 text-xs text-stone-400 uppercase tracking-widest">{{ $subGroup }}</flux:subheading>
                    @endif
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($guests->whereNull('representative_id') as $rep)
                        <flux:card class="relative flex flex-col pt-4 pb-4 px-5 {{ in_array($rep->id, $selectedGuestIds) ? 'ring-2 ring-red-200 bg-red-50/30' : '' }}">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-start gap-3 w-full">
                                    @if($manageMode)
                                        <flux:checkbox wire:model.live="selectedGuestIds" value="{{ $rep->id }}" class="mt-1" />
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <a href="/invitados/{{ $rep->slug }}" wire:navigate class="group">
                                            <h3 class="font-bold text-lg leading-tight group-hover:text-sage-600 transition-colors truncate">
                                                {{ $rep->name }}
                                                @if($rep->members->count() > 0 || $rep->extra_spots > 0)
                                                    <span class="text-xs font-normal text-stone-400"> ({{ 1 + $rep->members->count() + $rep->extra_spots }} personas)</span>
                                                @endif
                                            </h3>
                                            <p class="text-[10px] text-stone-400 mt-0.5">{{ '@'.$rep->slug }}</p>
                                        </a>
                                        
                                        @if($rep->members->count() > 0)
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                @foreach($rep->members as $member)
                                                    <flux:badge size="sm" variant="ghost" class="text-[9px] uppercase tracking-tighter opacity-70">{{ $member->name }}</flux:badge>
                                                @endforeach
                                            </div>
                                        @endif
                                        
                                        @if($rep->extra_spots > 0)
                                            <div class="mt-1">
                                                <flux:badge size="sm" color="zinc" class="text-[9px] uppercase tracking-tighter">+{{ $rep->extra_spots }} Lugares Adicionales</flux:badge>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @if(!$manageMode)
                                    <flux:button 
                                        wire:click="editRsvp('{{ $rep->id }}')" 
                                        size="sm" 
                                        variant="ghost" 
                                        icon="pencil" 
                                        class="text-stone-300 hover:text-stone-700 -mr-2 shrink-0" 
                                        wire:loading.attr="disabled"
                                        wire:target="editRsvp('{{ $rep->id }}')"
                                    />
                                @endif
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-2 mt-auto pt-3 border-t border-stone-50 dark:border-stone-800">
                                @if($rep->rsvp_status === 'confirmed')
                                    <flux:badge color="zinc" class="!bg-sage-100 !text-sage-700 border-0 text-[10px] font-bold">Confirmado</flux:badge>
                                @elseif($rep->rsvp_status === 'declined')
                                    <flux:badge color="red" class="border-0 text-[10px] font-bold">Declinado</flux:badge>
                                @else
                                    <flux:badge color="zinc" class="border-0 text-[10px] font-bold">Pendiente</flux:badge>
                                @endif

                                @foreach($rep->categories as $cat)
                                    <flux:badge color="zinc" size="sm" icon="tag" class="text-[9px]">{{ $cat->name }}</flux:badge>
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

    {{-- Add Family/Group Modal --}}
    <flux:modal name="add-group" class="min-w-[28rem]">
        <form wire:submit="saveGroup" class="space-y-6">
            <div>
                <flux:heading size="lg">Nueva Familia / Grupo</flux:heading>
                <flux:subheading>Registra a un representante y los miembros que lo acompañan.</flux:subheading>
            </div>
            
            <div class="space-y-6">
                {{-- Representative --}}
                <div class="space-y-4">
                    <flux:heading size="sm" class="uppercase tracking-widest text-stone-400 text-[10px]">Representante (Responsable)</flux:heading>
                    <flux:input wire:model="repName" label="Nombre completo" placeholder="Ej: Juan Silva" />
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="repEmail" type="email" label="Email" placeholder="juan@ejemplo.com" />
                        <flux:input wire:model="repPhone" label="Teléfono" placeholder="5512345678" />
                    </div>
                </div>

                {{-- Nominated Members --}}
                <div class="space-y-4 pt-4 border-t border-stone-50 dark:border-stone-800">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm" class="uppercase tracking-widest text-stone-400 text-[10px]">Miembros Nominados (Opcional)</flux:heading>
                        <flux:button variant="ghost" icon="plus" size="sm" wire:click="addMemberField" />
                    </div>
                    
                    <div class="space-y-2">
                        @foreach($members as $index => $member)
                            <div class="flex gap-2">
                                <flux:input wire:model="members.{{ $index }}" placeholder="Nombre del miembro..." class="flex-1" />
                                <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="removeMemberField({{ $index }})" />
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Extra spots --}}
                <div class="pt-4 border-t border-stone-50 dark:border-stone-800">
                    <flux:input wire:model="extraSpots" type="number" label="Pases Adicionales (Sin Nombre)" placeholder="0" min="0" />
                </div>

                {{-- Hierarchy & Categories --}}
                <div class="space-y-4 pt-4 border-t border-stone-50 dark:border-stone-800">
                    <flux:heading size="sm" class="uppercase tracking-widest text-stone-400 text-[10px]">Organización</flux:heading>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input wire:model="newGroupName" label="Nombre de la Familia / Grupo" placeholder="Ej: Familia Silva López" />
                        
                        <div x-data="{ open: false }" class="relative" @click.away="open = false" wire:ignore.self>
                            <flux:label>Depende de (Sub-grupo)</flux:label>
                            
                            <div class="relative mt-1" @click="open = true">
                                <input 
                                    type="text"
                                    wire:model.live.debounce.300ms="groupSearch" 
                                    @focus="open = true"
                                    placeholder="Buscar o crear..."
                                    autocomplete="off"
                                    class="w-full pl-3 pr-10 py-2 border border-stone-200 dark:border-stone-700 rounded-lg bg-white dark:bg-stone-800 text-sm focus:ring-2 focus:ring-sage-500/20 focus:border-sage-500 transition-all outline-none"
                                />
                                <div @click="open = !open" class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-stone-400 hover:text-stone-600">
                                    <flux:icon.chevron-down class="w-4 h-4" />
                                </div>
                            </div>
                            
                            <div x-show="open" 
                                 x-transition
                                 class="absolute z-[9999] w-full mt-1 bg-white dark:bg-stone-800 border-2 border-sage-500/50 rounded-lg shadow-2xl max-h-60 overflow-y-auto">
                                <div class="p-1">
                                    @if(empty($groupSearch))
                                        <button type="button" wire:click="$set('parentGroupId', ''); groupSearch = ''; open = false" class="w-full text-left p-2 hover:bg-stone-100 dark:hover:bg-stone-700 cursor-pointer rounded-md text-sm border-b border-stone-100 dark:border-stone-700 mb-1">
                                            Ninguno (General)
                                        </button>
                                    @endif

                                    @foreach($potentialParents as $pg)
                                        <button type="button" wire:click="$set('parentGroupId', '{{ $pg->id }}'); $set('groupSearch', '{{ $pg->name }}'); open = false" class="w-full text-left p-2 hover:bg-stone-100 dark:hover:bg-stone-700 cursor-pointer rounded-md text-sm mb-1">
                                            {{ $pg->name }}
                                        </button>
                                    @endforeach
                                    
                                    @if(!empty($groupSearch) && !$potentialParents->where('name', $groupSearch)->count())
                                        <button type="button" wire:click="$set('parentGroupId', '{{ $groupSearch }}'); open = false" class="w-full text-left p-2 bg-sage-50 dark:bg-sage-900/20 text-sage-600 dark:text-sage-400 hover:bg-sage-100 dark:hover:bg-sage-900/40 cursor-pointer rounded-md text-sm font-medium border border-sage-200 dark:border-sage-800 mt-1">
                                            <flux:icon.plus class="inline-block w-3 h-3 mr-1" />
                                            Crear: "{{ $groupSearch }}"
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-2">
                        <flux:checkbox.group wire:model="categoryIds" label="Categorías" description="Para todos los miembros.">
                            @foreach($categories as $category)
                                <flux:checkbox label="{{ $category->name }}" value="{{ $category->id }}" />
                            @endforeach
                        </flux:checkbox.group>
                        <flux:input wire:model="newCategoryName" size="sm" placeholder="Añadir nueva categoría..." />
                    </div>
                </div>
            </div>
            
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0">Guardar Todo el Grupo</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Confirm Delete Modal --}}
    <flux:modal name="confirm-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">¿Eliminar invitados?</flux:heading>
                <flux:subheading>Esta acción no se puede deshacer. Se eliminarán los {{ count($selectedGuestIds) }} invitados seleccionados.</flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button wire:click="deleteSelected" variant="danger" class="flex-1">Confirmar Eliminación</flux:button>
            </div>
        </div>
    </flux:modal>

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
