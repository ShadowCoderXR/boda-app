<?php

use App\Models\Category;
use App\Models\Group;
use App\Models\Guest;
use App\Models\SeatingTable;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component {
    public Collection $tables;
    public Collection $unseatedGuests;
    
    public $filterCategoryId = '';
    public $filterGroupId = '';
    public $startTableId = '';

    // Estado para "Swap"
    public $swapGuest1Id = null;
    
    // Modal state for manually adding guest to table
    public $selectedTableIdForAdd = null;
    public $selectedGuestIdToAdd = '';

    public function mount()
    {
        $this->ensureDefaultTablesExist();
        $this->loadData();
    }

    protected function ensureDefaultTablesExist()
    {
        if (SeatingTable::count() === 0) {
            $admin = \App\Models\User::where('role', \App\Enums\UserRole::Admin)->first();
            $adminId = $admin ? $admin->id : auth()->id();
            
            for ($i = 1; $i <= 10; $i++) {
                SeatingTable::create([
                    'name' => 'Mesa ' . $i,
                    'capacity' => 10,
                    'user_id' => $adminId,
                ]);
            }
        }
    }

    public function loadData()
    {
        $this->tables = SeatingTable::with(['guests' => function ($query) {
                // List guests ordered by name
                $query->orderBy('name');
            }])
            ->get()
            ->sortBy(function($table) {
                return (int) filter_var($table->name, FILTER_SANITIZE_NUMBER_INT);
            })->values();

        // Get confirmed unseated guests globally
        $query = Guest::where('rsvp_status', 'confirmed')
            ->whereNull('seating_table_id');

        if ($this->filterCategoryId) {
            $query->whereHas('categories', function ($q) {
                $q->where('categories.id', $this->filterCategoryId);
            });
        }
        
        if ($this->filterGroupId) {
            $query->where('group_id', $this->filterGroupId);
        }

        $this->unseatedGuests = $query->orderBy('name')->get();
    }

    public function addTable()
    {
        $count = SeatingTable::count();
        $admin = \App\Models\User::where('role', \App\Enums\UserRole::Admin)->first();
        
        SeatingTable::create([
            'name' => 'Mesa ' . ($count + 1),
            'capacity' => 10,
            'user_id' => $admin ? $admin->id : auth()->id(),
        ]);
        $this->loadData();
    }

    public function deleteTable($tableId)
    {
        SeatingTable::where('id', $tableId)->delete();
        $this->loadData();
    }

    public function clearTable($tableId)
    {
        Guest::where('seating_table_id', $tableId)
            ->update(['seating_table_id' => null]);
            
        $this->loadData();
    }

    public function removeGuestFromTable($guestId)
    {
        Guest::where('id', $guestId)
            ->update(['seating_table_id' => null]);
            
        if ($this->swapGuest1Id == $guestId) {
            $this->swapGuest1Id = null;
        }

        $this->loadData();
    }

    public function updateGlobalCapacity($newCapacity)
    {
        $capacity = (int) $newCapacity;
        if ($capacity > 0) {
            SeatingTable::query()->update(['capacity' => $capacity]);
            $this->loadData();
        }
    }
    
    public function setGlobalCapacity()
    {
        // Esto será llamado desde el JS pidiendo input
    }

    public function autoAssign()
    {
        // Re-fetch only the currently filtered unseated guests to be absolutely sure
        $query = Guest::where('rsvp_status', 'confirmed')
            ->whereNull('seating_table_id');

        if ($this->filterCategoryId) {
            $query->whereHas('categories', function ($q) {
                $q->where('categories.id', $this->filterCategoryId);
            });
        }
        
        if ($this->filterGroupId) {
            $query->where('group_id', $this->filterGroupId);
        }

        $guestsToAssign = $query->orderBy('name')->get();

        if ($guestsToAssign->isEmpty()) return;

        $tables = SeatingTable::get()
            ->sortBy(function($table) {
                return (int) filter_var($table->name, FILTER_SANITIZE_NUMBER_INT);
            })->values();
        
        $startIndex = 0;
        if ($this->startTableId) {
            $startIndex = $tables->search(fn($table) => $table->id === $this->startTableId);
            if ($startIndex === false) $startIndex = 0;
        }

        $tablesToUse = $tables->slice($startIndex);
        
        foreach ($guestsToAssign as $guest) {
            // Find first available table
            foreach ($tablesToUse as $table) {
                $currentCount = Guest::where('seating_table_id', $table->id)->count();
                if ($currentCount < $table->capacity) {
                    $guest->update(['seating_table_id' => $table->id]);
                    break; // Move to next guest
                }
            }
        }
        
        $this->loadData();
    }

    public function assignIndividual($guestId)
    {
        if (!$this->startTableId) {
            return; // Can't assign without a target table
        }
        
        $table = SeatingTable::find($this->startTableId);
        if ($table) {
            $currentCount = Guest::where('seating_table_id', $table->id)->count();
            if ($currentCount < $table->capacity) {
               Guest::where('id', $guestId)
                    ->update(['seating_table_id' => $this->startTableId]);
               $this->loadData();
            }
        }
    }

    public function startSwap($guestId)
    {
        if ($this->swapGuest1Id === $guestId) {
            $this->swapGuest1Id = null; // Unselect
            return;
        }
        
        if (!$this->swapGuest1Id) {
            $this->swapGuest1Id = $guestId;
        } else {
            // Do swap
            $guest1 = Guest::find($this->swapGuest1Id);
            $guest2 = Guest::find($guestId);
            
            if ($guest1 && $guest2) {
                $table1Id = $guest1->seating_table_id;
                $table2Id = $guest2->seating_table_id;
                
                $guest1->update(['seating_table_id' => $table2Id]);
                $guest2->update(['seating_table_id' => $table1Id]);
            }
            
            $this->swapGuest1Id = null;
            $this->loadData();
        }
    }

    // Modal Actions
    public function openAddGuestModal($tableId)
    {
        $this->selectedTableIdForAdd = $tableId;
        $this->selectedGuestIdToAdd = '';
    }

    public function addGuestToTable()
    {
        if ($this->selectedTableIdForAdd && $this->selectedGuestIdToAdd) {
            Guest::where('id', $this->selectedGuestIdToAdd)
                ->update(['seating_table_id' => $this->selectedTableIdForAdd]);
                
            $this->loadData();
            $this->selectedGuestIdToAdd = '';
        }
    }
    
    // Obtenemos categorias para filtros
    public function getCategoriesProperty()
    {
        return Category::orderBy('name')->get();
    }
    
    public function getGroupsProperty()
    {
        return Group::with('subgroup')->orderBy('name')->get();
    }

}; ?>

<div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
    <!-- Main Content: Tables Grid -->
    <div class="xl:col-span-3 space-y-6">
        <div class="flex justify-between items-center bg-zinc-50 dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
            <div class="flex gap-4 items-center">
                <flux:button wire:click="addTable" icon="plus" variant="primary">Añadir Mesa</flux:button>
                <div x-data="{
                    askCapacity: function() {
                        let result = prompt('Introduce la nueva capacidad global para todas las mesas:');
                        if(result && !isNaN(result)) {
                            $wire.updateGlobalCapacity(result);
                        }
                    }
                }">
                    <flux:button x-on:click="askCapacity" icon="users">Capacidad Global</flux:button>
                </div>
            </div>
            
            @if($swapGuest1Id)
                <flux:badge color="amber" icon="arrows-right-left">Selecciona el segundo invitado para intercambiar</flux:badge>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($tables as $table)
                <flux:card>
                    <div class="flex justify-between items-center mb-4">
                        <flux:heading size="lg">{{ $table->name }}</flux:heading>
                        <flux:dropdown>
                            <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                            <flux:menu>
                                <flux:menu.item wire:click="openAddGuestModal('{{ $table->id }}')" icon="user-plus">Añadir Invitado</flux:menu.item>
                                <flux:menu.item wire:click="clearTable('{{ $table->id }}')" icon="user-minus">Limpiar Mesa</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="deleteTable('{{ $table->id }}')" icon="trash" variant="danger">Eliminar Mesa</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    @php
                        $occupancy = $table->guests->count();
                        $percent = $table->capacity > 0 ? min(100, round(($occupancy / $table->capacity) * 100)) : 0;
                        $isFull = $occupancy >= $table->capacity;
                        
                        $progressColor = 'zinc';
                        if ($percent >= 100) $progressColor = 'red';
                        elseif ($percent >= 75) $progressColor = 'amber';
                        elseif ($percent > 0) $progressColor = 'green';
                    @endphp

                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-1 text-zinc-500">
                            <span>Ocupación</span>
                            <span>{{ $occupancy }} / {{ $table->capacity }}</span>
                        </div>
                        <div class="w-full bg-zinc-200 rounded-full h-2.5 dark:bg-zinc-700">
                            <div class="bg-{{ $progressColor }}-500 h-2.5 rounded-full" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>

                    <div class="space-y-2 mt-4 min-h-[100px]">
                        @forelse($table->guests as $guest)
                            <div class="flex items-center justify-between group p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition-colors border {{ $swapGuest1Id == $guest->id ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20' : 'border-transparent' }}">
                                <div class="flex items-center gap-2">
                                    <flux:icon.user class="size-4 text-zinc-400" />
                                    <span class="text-sm font-medium {{ $swapGuest1Id == $guest->id ? 'text-amber-700 dark:text-amber-300' : 'text-zinc-700 dark:text-zinc-300' }}">{{ $guest->name }}</span>
                                </div>
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                                    <flux:button wire:click="startSwap('{{ $guest->id }}')" variant="ghost" size="xs" icon="arrows-right-left" tooltip="Intercambiar" class="{{ $swapGuest1Id == $guest->id ? 'text-amber-600' : 'text-zinc-400 hover:text-blue-600' }}" />
                                    <flux:button wire:click="removeGuestFromTable('{{ $guest->id }}')" variant="ghost" size="xs" icon="x-mark" tooltip="Remover" class="text-zinc-400 hover:text-red-600" />
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-400 italic text-center py-4">Mesa vacía</p>
                        @endforelse
                    </div>
                </flux:card>
            @endforeach
        </div>
    </div>

    <!-- Sidebar: Tools & Unseated Guests -->
    <div class="xl:col-span-1 border-l border-zinc-200 dark:border-zinc-800 pl-0 xl:pl-6 space-y-6">
        <flux:heading size="lg">Invitados por Sentar</flux:heading>
        
        <flux:card>
            <div class="space-y-4">
                <flux:select wire:model.live="filterCategoryId" label="Filtrar por Categoría" placeholder="Todas las categorías">
                    <flux:select.option value="">Todas</flux:select.option>
                    @foreach($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="filterGroupId" label="Filtrar por Familia/Grupo" placeholder="Todas las familias">
                    <flux:select.option value="">Todas</flux:select.option>
                    @foreach($this->groups as $group)
                        <flux:select.option value="{{ $group->id }}">{{ $group->subgroup ? $group->subgroup->name . ' - ' : '' }}{{ $group->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:select wire:model.live="startTableId" label="Mesa Destino (Inicial)" placeholder="Requiere selección">
                    <flux:select.option value="">-- Elige Mesa Destino --</flux:select.option>
                    @foreach($tables as $table)
                        <flux:select.option value="{{ $table->id }}">{{ $table->name }} ({{ $table->guests->count() }}/{{ $table->capacity }})</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button wire:click="autoAssign" class="w-full" variant="primary" icon="sparkles" :disabled="$unseatedGuests->isEmpty() || !$startTableId">
                    Asignar Masivamente ({{ $unseatedGuests->count() }})
                </flux:button>
            </div>
        </flux:card>

        <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-200 dark:border-zinc-800 h-[500px] overflow-y-auto">
            <h3 class="text-sm font-medium text-zinc-500 mb-3 uppercase tracking-wider">Lista de Espera</h3>
            
            <div class="space-y-2">
                @forelse($unseatedGuests as $guest)
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-3 rounded-lg flex justify-between items-center shadow-sm">
                        <div>
                            <p class="text-sm font-medium">{{ $guest->name }}</p>
                            <p class="text-xs text-zinc-500">
                                @if($guest->group) {{ $guest->group->name }} @endif
                            </p>
                        </div>
                        <!-- Opciones Individuales -->
                         <div class="flex gap-1">
                             <flux:button wire:click="assignIndividual('{{ $guest->id }}')" variant="ghost" size="sm" icon="arrow-right-circle" tooltip="Asignar a Mesa Destino" :disabled="!$startTableId" class="text-zinc-600 hover:text-green-600 active:text-green-700 disabled:opacity-50" />
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 text-center py-6">No hay invitados pendientes en este filtro.</p>
                @endforelse
            </div>
        </div>
    </div>
    
    <!-- Modal for Manual Adding -->
    @if($selectedTableIdForAdd)
    <flux:modal wire:model="selectedTableIdForAdd" name="add-guest-modal" class="md:w-96">
        <form wire:submit="addGuestToTable" class="space-y-6">
            <flux:heading>Añadir Invitado a la Mesa</flux:heading>
            
            <flux:select wire:model="selectedGuestIdToAdd" label="Seleccionar Invitado">
                <flux:select.option value="">Elige un invitado...</flux:select.option>
                @foreach($unseatedGuests as $guest)
                    <flux:select.option value="{{ $guest->id }}">{{ $guest->name }}</flux:select.option>
                @endforeach
            </flux:select>
            
            <div class="flex justify-end space-x-2">
                <flux:button wire:click="$set('selectedTableIdForAdd', null)" variant="ghost">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" :disabled="!$selectedGuestIdToAdd">Añadir</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
