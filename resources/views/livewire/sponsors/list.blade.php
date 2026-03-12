<?php

use Livewire\Volt\Component;
use App\Models\Sponsor;
use App\Models\Guest;

new class extends Component {
    // Edit Sponsor State
    public ?Sponsor $editingSponsor = null;
    public string $editRoles = '';
    public string $editStatus = 'Tentativo';
    public string $editNotes = '';

    // Assign Sponsor State
    public $newGuestId = '';
    public $newRole = '';
    public $newNotes = '';

    public function with()
    {
        return [
            'sponsors' => Sponsor::with('guest')->get(),
            'availableGuests' => Guest::whereDoesntHave('sponsor')->orderBy('name')->get()
        ];
    }

    public function editSponsor(Sponsor $sponsor)
    {
        $this->editingSponsor = $sponsor;
        $this->editRoles = $sponsor->role;
        
        $details = $sponsor->details ?? [];
        $this->editStatus = $details['status'] ?? 'Tentativo';
        $this->editNotes = $details['notes'] ?? '';

        $this->js("Flux.modal('edit-sponsor').show()");
    }

    public function saveSponsor()
    {
        if (!$this->editingSponsor) return;

        $this->validate([
            'editRoles' => 'required|string|max:255',
            'editStatus' => 'required|in:Tentativo,Confirmado,Hecho',
            'editNotes' => 'nullable|string|max:500',
        ]);

        $details = $this->editingSponsor->details ?? [];
        $details['status'] = $this->editStatus;
        $details['notes'] = $this->editNotes;

        $this->editingSponsor->update([
            'role' => $this->editRoles,
            'details' => $details
        ]);

        $this->js("Flux.modal('edit-sponsor').close()");
    }

    public function assignSponsor()
    {
        $this->validate([
            'newGuestId' => 'required|exists:guests,id',
            'newRole' => 'required|string|max:255',
            'newNotes' => 'nullable|string|max:500',
        ]);

        Sponsor::create([
            'guest_id' => $this->newGuestId,
            'role' => $this->newRole,
            'details' => [
                'status' => 'Tentativo',
                'notes' => $this->newNotes
            ]
        ]);

        $this->reset(['newGuestId', 'newRole', 'newNotes']);
        $this->js("Flux.modal('assign-sponsor').close()");
    }
}
?>

<div>
    {{-- Header Action --}}
    <div class="flex justify-end mb-6">
        <flux:modal.trigger name="assign-sponsor">
            <flux:button icon="user-plus" class="bg-sage-600 hover:bg-sage-700 text-white shadow-sm border-0">Convertir Invitado en Padrino</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Sponsors Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse($sponsors as $sponsor)
            @php
                // Fetch dynamic status from JSON
                $status = $sponsor->details['status'] ?? 'Tentativo';
                $notes = $sponsor->details['notes'] ?? '';
            @endphp
            
            <flux:card class="relative flex flex-col pt-5 pb-5 px-6 group dark:bg-zinc-900 border-stone-200 dark:border-stone-800">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="font-semibold text-lg leading-tight text-stone-800 dark:text-stone-100 flex items-center gap-2">
                            {{ $sponsor->guest->name }}
                        </h3>
                        <p class="text-sm text-stone-500 mt-1 font-medium">{{ $sponsor->role }}</p>
                    </div>
                    
                    <flux:button wire:click="editSponsor({{ $sponsor->id }})" size="sm" variant="ghost" icon="pencil" class="text-stone-400 hover:text-stone-700 -mr-2" />
                </div>
                
                @if($notes)
                    <div class="mt-2 text-sm text-stone-600 dark:text-stone-400 bg-stone-50 dark:bg-stone-800/50 p-3 rounded-md italic border-l-2 border-sage-300">
                        "{{ $notes }}"
                    </div>
                @endif
                
                <div class="flex flex-wrap items-center gap-2 mt-auto pt-5">
                    @if($status === 'Confirmado')
                        <flux:badge color="zinc" class="!bg-sage-100 !text-sage-700 border-0 text-xs shadow-sm">Confirmado</flux:badge>
                    @elseif($status === 'Hecho')
                        <flux:badge color="zinc" class="bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border-0 text-xs shadow-sm">Hecho</flux:badge>
                    @else
                        <flux:badge color="yellow" class="bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-500 border-0 text-xs shadow-sm">Tentativo</flux:badge>
                    @endif
                </div>
            </flux:card>
        @empty
            <div class="col-span-full py-16 text-center text-stone-500 border-2 border-dashed border-stone-200 dark:border-stone-800 rounded-xl">
                <flux:icon.users class="w-12 h-12 mx-auto mb-3 text-stone-300" />
                <h3 class="text-lg font-medium text-stone-900 dark:text-white mb-1">Aún no hay padrinos asignados</h3>
                <p>Convierte a un invitado en padrino para empezar a organizar responsabilidades.</p>
            </div>
        @endforelse
    </div>

    {{-- Assign Sponsor Modal --}}
    <flux:modal name="assign-sponsor" class="min-w-[24rem]">
        <form wire:submit="assignSponsor" class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo Padrino</flux:heading>
                <flux:subheading>Asigna un rol a un invitado existente.</flux:subheading>
            </div>

            <div class="space-y-4 pt-2 border-t border-stone-100 dark:border-stone-800">
                <flux:select wire:model="newGuestId" label="Seleccionar Invitado" searchable placeholder="Elige un invitado...">
                    @foreach($availableGuests as $guest)
                        <flux:select.option value="{{ $guest->id }}">{{ $guest->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="newRole" label="Rol del Padrino" placeholder="Ej: Anillos, Arras, Lazo..." />

                <flux:textarea wire:model="newNotes" label="Notas o Recordatorios (Opcional)" rows="2" placeholder="Agrega detalles iniciales..." />
            </div>
            
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0">Asignar Padrino</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Sponsor Modal --}}
    <flux:modal name="edit-sponsor" class="min-w-[24rem]">
        <form wire:submit="saveSponsor" class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Detalles</flux:heading>
                <flux:subheading>{{ $editingSponsor ? $editingSponsor->guest->name : '' }}</flux:subheading>
            </div>

            <div class="space-y-4 pt-2 border-t border-stone-100 dark:border-stone-800">
                <flux:input wire:model="editRoles" label="Rol(es)" />

                <flux:radio.group wire:model="editStatus" label="Estado de la Tarea">
                    <flux:radio value="Tentativo" label="Tentativo" />
                    <flux:radio value="Confirmado" label="Confirmado" />
                    <flux:radio value="Hecho" label="Hecho" />
                </flux:radio.group>

                <flux:textarea wire:model="editNotes" label="Notas o Recordatorios" rows="3" placeholder="Agrega detalles para este padrino..." />
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
