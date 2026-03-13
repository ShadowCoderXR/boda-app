<?php

use Livewire\Volt\Component;
use App\Models\Category;
use App\Models\Subgroup;
use App\Models\Group;
use App\Models\Guest;

new class extends Component {
    public Guest $guest;
    
    // Form State
    public bool $editMode = false;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public ?string $group_id = null;
    public array $category_ids = [];
    public string $rsvp_status = 'pending';
    public int $extra_spots = 0;
    
    // Group Management
    public string $groupName = '';
    public ?string $parentGroupId = null;
    
    // Member Management
    public $editingMemberId = null;
    public string $memberName = '';
    public string $memberRsvp = 'pending';
    public string $memberMenu = '';
    public string $memberAllergies = '';

    public function mount(Guest $guest)
    {
        $this->guest = $guest;
        $this->loadData();
    }

    public function loadData()
    {
        $this->name = $this->guest->name;
        $this->email = $this->guest->email ?? '';
        $this->phone = $this->guest->phone ?? '';
        $this->group_id = $this->guest->group_id;
        $this->category_ids = $this->guest->categories->pluck('id')->toArray();
        $this->rsvp_status = $this->guest->rsvp_status;
        $this->extra_spots = (int) ($this->guest->extra_spots ?? 0);

        if ($this->guest->group) {
            $this->groupName = $this->guest->group->name;
            $this->parentGroupId = $this->guest->group->subgroup_id;
        } else {
            $this->groupName = '';
            $this->parentGroupId = null;
        }
    }

    public function toggleEdit()
    {
        $this->editMode = !$this->editMode;
        if (!$this->editMode) {
            $this->loadData();
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'groupName' => 'required|string|max:255',
            'parentGroupId' => 'nullable|exists:groups,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'rsvp_status' => 'required|in:pending,confirmed,declined',
            'extra_spots' => 'required|integer|min:0',
        ]);

        DB::transaction(function() {
            // Handle Group Update
            if ($this->guest->group) {
                $this->guest->group->update([
                    'name' => $this->groupName,
                    'subgroup_id' => $this->parentGroupId ?: null,
                ]);
            } else {
                $newGroup = Group::create([
                    'name' => $this->groupName,
                    'subgroup_id' => $this->parentGroupId ?: null,
                    'user_id' => auth()->id(),
                ]);
                $this->guest->group_id = $newGroup->id;
            }

            $this->guest->update([
                'name' => $this->name,
                'email' => $this->email ?: null,
                'phone' => $this->phone ?: null,
                'rsvp_status' => $this->rsvp_status,
                'extra_spots' => $this->extra_spots,
            ]);

            $this->guest->categories()->sync($this->category_ids);

            // If representative, cascade RSVP
            if ($this->guest->is_representative) {
                $this->guest->members()->update([
                    'rsvp_status' => $this->rsvp_status
                ]);
            }
        });

        $this->editMode = false;
        $this->guest->refresh();
        $this->loadData();
    }

    public function deleteGuest()
    {
        $this->guest->delete();
        return redirect()->to('/invitados');
    }

    public function editMember(Guest $member)
    {
        $this->editingMemberId = $member->id;
        $this->memberName = $member->name;
        $this->memberRsvp = $member->rsvp_status;
        
        $details = $member->rsvp_details ?? [];
        $this->memberMenu = $details['menu'] ?? '';
        $this->memberAllergies = $details['allergies'] ?? '';
        
        $this->js("Flux.modal('edit-member').show()");
    }

    public function saveMember()
    {
        $member = Guest::findOrFail($this->editingMemberId);
        
        $member->update([
            'name' => $this->memberName,
            'rsvp_status' => $this->memberRsvp,
            'rsvp_details' => [
                'menu' => $this->memberMenu,
                'allergies' => $this->memberAllergies,
            ]
        ]);

        $this->js("Flux.modal('edit-member').close()");
        $this->guest->refresh();
    }

    public function nameExtraSpot()
    {
        if ($this->guest->extra_spots > 0) {
            DB::transaction(function() {
                $this->guest->decrement('extra_spots');
                $this->extra_spots = $this->guest->extra_spots; // Update local state
                
                $count = Guest::where('representative_id', $this->guest->id)->count() + 1;
                $defaultName = $this->guest->name . " - Invitado " . $count;

                $newMember = Guest::create([
                    'name' => $defaultName,
                    'group_id' => $this->guest->group_id,
                    'representative_id' => $this->guest->id,
                    'is_representative' => false,
                    'rsvp_status' => $this->guest->rsvp_status,
                ]);
                
                // Copy categories
                $newMember->categories()->sync($this->guest->categories->pluck('id'));
            });

            $this->guest->refresh();
        }
    }

    public function addMember()
    {
        DB::transaction(function() {
            $count = Guest::where('representative_id', $this->guest->id)->count() + 1;
            $newMember = Guest::create([
                'name' => "Invitado " . $count,
                'group_id' => $this->guest->group_id,
                'representative_id' => $this->guest->id,
                'is_representative' => false,
                'rsvp_status' => $this->guest->rsvp_status,
            ]);
            
            // Sync categories from rep
            $newMember->categories()->sync($this->guest->categories->pluck('id'));
        });

        $this->guest->refresh();
    }

    public function deleteMember($id)
    {
        $member = Guest::where('representative_id', $this->guest->id)->findOrFail($id);
        $member->delete();
        $this->guest->refresh();
    }

    public function with()
    {
        return [
            'groups' => Subgroup::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }
}
?>

<div class="max-w-4xl mx-auto py-8">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <flux:button href="/invitados" variant="ghost" icon="chevron-left" shadow="false" />
            <div>
                <flux:heading size="xl">{{ $guest->name }}</flux:heading>
                <flux:subheading>Detalles del Invitado</flux:subheading>
            </div>
        </div>

        <div class="flex gap-2">
            @if($editMode)
                <flux:button wire:click="toggleEdit" icon="x-mark" variant="outline">Cancelar</flux:button>
                <flux:button wire:click="save" icon="check-circle" variant="primary" class="bg-sage-600 hover:bg-sage-700">Guardar</flux:button>
            @else
                <flux:modal.trigger name="confirm-delete-main">
                    <flux:button icon="trash" variant="outline" class="text-red-500 hover:bg-red-50">Eliminar</flux:button>
                </flux:modal.trigger>
                <flux:button wire:click="toggleEdit" icon="pencil" variant="outline">Editar Información</flux:button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2">
            @if($editMode)
                <flux:card>
                    <form wire:submit="save" class="space-y-6">
                        <flux:input wire:model="name" label="Nombre completo" />

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="email" type="email" label="Email" />
                            <flux:input wire:model="phone" label="Teléfono" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:input wire:model="groupName" label="Nombre de la Familia / Grupo" placeholder="Ej: Familia Silva López" />

                            <flux:select wire:model="parentGroupId" label="Depende de (Sub-grupo)" placeholder="Elegir...">
                                <flux:select.option value="">Ninguno</flux:select.option>
                                @foreach($groups as $group)
                                    <flux:select.option value="{{ $group->id }}">{{ $group->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:select wire:model="rsvp_status" label="Estado RSVP">
                                <flux:select.option value="pending">Pendiente</flux:select.option>
                                <flux:select.option value="confirmed">Confirmado</flux:select.option>
                                <flux:select.option value="declined">Declinado</flux:select.option>
                            </flux:select>

                            <flux:input type="number" wire:model="extra_spots" label="Invitados extras (sin nombre)" min="0" />
                        </div>

                        <div class="space-y-3">
                            <flux:label>Categorías</flux:label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach($categories as $category)
                                    <flux:checkbox wire:model="category_ids" :label="$category->name" :value="$category->id" />
                                @endforeach
                            </div>
                        </div>




                        <div class="flex justify-end gap-2">
                            <flux:button wire:click="toggleEdit" variant="ghost">Cancelar</flux:button>
                            <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700">Guardar Cambios</flux:button>
                        </div>
                    </form>
                </flux:card>
            @else
                <flux:card>
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:label>Estado RSVP</flux:label>
                                <div class="mt-1">
                                    @php
                                        $color = match($guest->rsvp_status) {
                                            'confirmed' => 'green',
                                            'declined' => 'red',
                                            default => 'stone',
                                        };
                                        $label = match($guest->rsvp_status) {
                                            'confirmed' => 'Confirmado',
                                            'declined' => 'Declinado',
                                            default => 'Pendiente',
                                        };
                                    @endphp
                                    <flux:badge :color="$color">{{ $label }}</flux:badge>
                                </div>
                            </div>

                            <div>
                                <flux:label>Grupo / Familia</flux:label>
                                <div class="mt-1 font-medium">{{ $guest->group?->name ?? 'Sin grupo' }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 border-t border-stone-100 dark:border-stone-800 pt-6">
                            <div>
                                <flux:label>Email</flux:label>
                                <div class="mt-1 font-medium">{{ $guest->email ?? '-' }}</div>
                            </div>
                            <div>
                                <flux:label>Teléfono</flux:label>
                                <div class="mt-1 font-medium">{{ $guest->phone ?? '-' }}</div>
                            </div>
                        </div>

                        @if ($guest->rsvp_status === 'confirmed')
                            <div class="grid grid-cols-2 gap-4 border-t border-stone-100 dark:border-stone-800 pt-6">
                                <div>
                                    <flux:label>Menú Seleccionado</flux:label>
                                    <div class="mt-1 font-medium">{{ $guest->rsvp_details['menu'] ?? '-' }}</div>
                                </div>
                                <div>
                                    <flux:label>Alergias / Notas</flux:label>
                                    <div class="mt-1 font-medium">{{ $guest->rsvp_details['allergies'] ?? '-' }}</div>
                                </div>
                            </div>
                        @endif

                        <div>
                            <flux:label>Categorías</flux:label>
                            <div class="flex flex-wrap gap-2 mt-2">
                                @forelse ($guest->categories as $category)
                                    <flux:badge :color="$category->color ?? 'stone'" size="sm">{{ $category->name }}</flux:badge>
                                @empty
                                    <span class="text-stone-500 text-sm italic">Sin categorías</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- Members & Extras Section --}}
            @if($guest->is_representative)
                <div class="mt-8 space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">Miembros y Acompañantes</flux:heading>
                        <div class="flex gap-2">
                            <flux:button wire:click="addMember" size="sm" variant="outline" icon="plus-circle">Añadir con Nombre</flux:button>
                            @if($guest->extra_spots > 0)
                                <flux:button wire:click="nameExtraSpot" size="sm" variant="outline" icon="user-plus">Nombrar Acompañante</flux:button>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3">
                        {{-- Named Members --}}
                        @foreach($guest->members as $member)
                            <div class="flex items-center justify-between p-4 bg-white dark:bg-stone-800 rounded-xl border border-stone-100 dark:border-stone-700 shadow-sm transition-all hover:border-sage-200">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-stone-50 dark:bg-stone-900 border border-stone-100 dark:border-stone-800 flex items-center justify-center">
                                        <flux:icon.user class="w-4 h-4 text-stone-400" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-stone-800 dark:text-stone-100">{{ $member->name }}</div>
                                        <div class="flex gap-2 items-center mt-1">
                                            @php
                                                $mColor = match($member->rsvp_status) {
                                                    'confirmed' => 'green',
                                                    'declined' => 'red',
                                                    default => 'stone',
                                                };
                                                $mLabel = match($member->rsvp_status) {
                                                    'confirmed' => 'Confirmado',
                                                    'declined' => 'Declinado',
                                                    default => 'Pendiente',
                                                };
                                            @endphp
                                            <flux:badge size="sm" :color="$mColor" class="border-0 text-[9px] uppercase font-black">{{ $mLabel }}</flux:badge>
                                            
                                            @php $det = $member->rsvp_details ?? []; @endphp
                                            @if(!empty($det['menu']))
                                                <span class="text-[10px] text-stone-400 italic">Menu: {{ $det['menu'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-1">
                                    <flux:button wire:click="editMember('{{ $member->id }}')" variant="ghost" size="sm" icon="pencil" />
                                    <flux:button wire:click="deleteMember('{{ $member->id }}')" variant="ghost" size="sm" icon="trash" class="text-red-300 hover:text-red-500" />
                                </div>
                            </div>
                        @endforeach

                        {{-- Anonymous Spots --}}
                        @for($i = 0; $i < $guest->extra_spots; $i++)
                            <div class="flex items-center justify-between p-4 bg-stone-50/50 dark:bg-stone-900/30 rounded-xl border border-dashed border-stone-200 dark:border-stone-800 transition-all hover:bg-stone-100/50">
                                <div class="flex items-center gap-3 opacity-60">
                                    <div class="w-8 h-8 rounded-full border border-stone-200 dark:border-stone-700 flex items-center justify-center">
                                        <flux:icon.user-plus class="w-4 h-4 text-stone-300" />
                                    </div>
                                    <div class="text-sm font-medium text-stone-500">Espacio Libre #{{ $i + 1 }}</div>
                                </div>
                                <flux:button wire:click="nameExtraSpot" variant="ghost" size="sm" class="text-sage-600 text-[10px] uppercase font-bold tracking-widest">Poner Nombre</flux:button>
                            </div>
                        @endfor
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="sm" class="mb-2">Invitación Digital</flux:heading>
                    <flux:subheading size="xs">Enlace público para compartir con el invitado.</flux:subheading>
                </div>

                <div class="p-3 bg-gold-50 dark:bg-gold-500/10 border border-gold-200 dark:border-gold-500/30 rounded-lg break-all font-mono text-[10px] text-gold-700 dark:text-gold-300">
                    {{ url('/invitacion/' . $guest->slug) }}
                </div>

                <flux:button href="{{ url('/invitacion/' . $guest->slug) }}" target="_blank" icon="eye" variant="outline" size="sm" class="w-full">
                    Ver Invitación Digital
                </flux:button>

                <p class="text-[10px] text-stone-500 text-center italic leading-tight">
                    Este es el diseño premium que verán tus invitados.
                </p>
            </flux:card>
        </div>
    {{-- Edit Member Modal --}}
    <flux:modal name="edit-member" class="min-w-[22rem]">
        <form wire:submit="saveMember" class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Miembro</flux:heading>
                <flux:subheading>Gestiona los detalles específicos de este asistente.</flux:subheading>
            </div>
            
            <div class="space-y-4">
                <flux:input wire:model="memberName" label="Nombre completo" />

                <flux:radio.group wire:model="memberRsvp" label="Estado de Asistencia">
                    <flux:radio value="pending" label="Pendiente" />
                    <flux:radio value="confirmed" label="Confirmado" />
                    <flux:radio value="declined" label="Declinado" />
                </flux:radio.group>

                <div class="space-y-4 pt-4 border-t border-stone-100 dark:border-stone-800">
                    <flux:select wire:model="memberMenu" label="Preferencia de Menú">
                        <flux:select.option value="Estándar">Estándar</flux:select.option>
                        <flux:select.option value="Vegetariano">Vegetariano</flux:select.option>
                        <flux:select.option value="Vegano">Vegano</flux:select.option>
                        <flux:select.option value="Infantil">Infantil</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="memberAllergies" label="Alergias o Restricciones" placeholder="Ej: Cacahuates..." />
                </div>
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

    {{-- Confirm Delete Main Guest Modal --}}
    <flux:modal name="confirm-delete-main" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">¿Eliminar invitado?</flux:heading>
                <flux:subheading>Esta acción no se puede deshacer. Se eliminará a <b>{{ $guest->name }}</b> y a todos sus miembros asociados si es representante.</flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button wire:click="deleteGuest" variant="danger" class="flex-1">Confirmar Eliminación</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
</div>
