<?php

new class extends \Livewire\Volt\Component {
    public ?string $selectedMemberId = null;
    public $memberDetails = null;
    public ?string $selectedGuestId = null;
    public string $temporaryPassword = '';
    public string $generatedUsername = '';
    public string $promotedName = '';

    public function promote()
    {
        $this->validate([
            'selectedGuestId' => 'required|exists:guests,id',
        ]);

        $guest = \App\Models\Guest::findOrFail($this->selectedGuestId);

        if ($guest->user_id && \App\Models\User::where('id', $guest->user_id)->exists()) {
             $this->dispatch('notify', title: 'Error', description: 'Este invitado ya tiene un usuario asignado.', variant: 'danger');
             return;
        }

        $this->generatedUsername = \Illuminate\Support\Str::slug($guest->name);
        
        // Ensure unique username
        $baseUsername = $this->generatedUsername;
        $counter = 1;
        while (\App\Models\User::where('username', $this->generatedUsername)->exists()) {
            $this->generatedUsername = $baseUsername . $counter++;
        }

        $this->temporaryPassword = 'Boda' . \Illuminate\Support\Str::random(4) . '!';
        $this->promotedName = $guest->name;

        $user = \App\Models\User::create([
            'name' => $guest->name,
            'username' => $this->generatedUsername,
            'password' => \Illuminate\Support\Facades\Hash::make($this->temporaryPassword),
            'role' => \App\Enums\UserRole::Colaborador,
            'temporary_password' => $this->temporaryPassword,
        ]);

        $guest->update(['user_id' => $user->id]);

        $this->selectedGuestId = null;
        $this->js("Flux.modal('promotion-success').show()");
    }

    public function showMemberDetails($userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        
        if ($user->isAdmin()) {
            return;
        }

        $this->memberDetails = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role->label(),
            'temporary_password' => $user->temporary_password ?? 'Desconocida',
        ];

        $this->js("Flux.modal('member-details').show()");
    }

    public function with()
    {
        return [
            'usersByRole' => \App\Models\User::all()->groupBy('role.value'),
            'eligibleGuests' => \App\Models\Guest::whereNull('user_id')->orderBy('name')->get(),
        ];
    }
};
?>

<div class="max-w-4xl mx-auto py-8">
    <div class="mb-8">
        <flux:heading size="xl">Gestión de Equipo</flux:heading>
        <flux:subheading>Administra quiénes tienen acceso para organizar la boda.</flux:subheading>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        {{-- Promotion Tool --}}
        <div class="md:col-span-1 space-y-6">
            <flux:card>
                <flux:heading size="md" class="mb-4">Convertir Invitado en Colaborador</flux:heading>
                <div class="space-y-4">
                    <flux:select wire:model="selectedGuestId" label="Seleccionar Invitado" placeholder="Buscar invitado...">
                        <x-slot name="search"></x-slot>
                        <flux:select.option value="">Elegir...</flux:select.option>
                        @foreach($eligibleGuests as $eg)
                            <flux:select.option value="{{ $eg->id }}">{{ $eg->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button wire:click="promote" wire:loading.attr="disabled" wire:target="promote" variant="primary" icon="user-plus" class="w-full bg-sage-600 hover:bg-sage-700 border-0">
                        <span wire:loading.remove wire:target="promote">Hacer Colaborador</span>
                        <span wire:loading wire:target="promote">Procesando...</span>
                    </flux:button>
                    
                    <p class="text-[10px] text-stone-500 italic mt-2">
                        Esto creará una cuenta de acceso privada.
                    </p>
                </div>
            </flux:card>
        </div>

        {{-- Team List --}}
        <div class="md:col-span-2 space-y-8">
            @php
                $roleOrder = [
                    \App\Enums\UserRole::Admin->value => 'Administradores',
                    \App\Enums\UserRole::Novio->value => 'Pareja (Novios)',
                    \App\Enums\UserRole::Novia->value => 'Pareja (Novios)',
                    \App\Enums\UserRole::Padrino1->value => 'Padrinos y Madrinas',
                    \App\Enums\UserRole::Padrino2->value => 'Padrinos y Madrinas',
                    \App\Enums\UserRole::Padrino3->value => 'Padrinos y Madrinas',
                    \App\Enums\UserRole::Madrina1->value => 'Padrinos y Madrinas',
                    \App\Enums\UserRole::Madrina2->value => 'Padrinos y Madrinas',
                    \App\Enums\UserRole::Madrina3->value => 'Padrinos y Madrinas',
                    \App\Enums\UserRole::Colaborador->value => 'Colaboradores',
                ];
                
                $grouped = [
                    'Administradores' => collect(),
                    'Pareja (Novios)' => collect(),
                    'Padrinos y Madrinas' => collect(),
                    'Colaboradores' => collect(),
                ];

                foreach($usersByRole as $roleVal => $users) {
                    $label = $roleOrder[$roleVal] ?? 'Otros';
                    if (!isset($grouped[$label])) $grouped[$label] = collect();
                    $grouped[$label] = $grouped[$label]->concat($users);
                }
                
                // Remove empty groups if desired, or keep to show sections
                $grouped = array_filter($grouped, fn($group) => $group->isNotEmpty());
            @endphp

            @foreach($grouped as $label => $teamMembers)
                <div class="space-y-3">
                    <flux:heading size="sm" class="uppercase tracking-widest text-stone-400">{{ $label }}</flux:heading>
                    <div class="bg-white dark:bg-stone-800 border border-stone-200 dark:border-stone-700 rounded-xl overflow-hidden divide-y divide-stone-100 dark:divide-stone-700">
                        @foreach($teamMembers as $member)
                            @php $canDetails = !$member->isAdmin(); @endphp
                            <div class="p-4 flex items-center justify-between hover:bg-stone-50/50 dark:hover:bg-stone-900/10 transition-colors group">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-sage-50 dark:bg-sage-900/30 flex items-center justify-center text-sage-600 font-bold uppercase">
                                        {{ substr($member->name, 0, 2) }}
                                    </div>
                                    <div @class(['cursor-pointer' => $canDetails]) @if($canDetails) wire:click="showMemberDetails({{ $member->id }})" @endif>
                                        <div @class(['font-bold text-stone-800 dark:text-stone-100 transition-colors', 'group-hover:text-sage-600' => $canDetails])>{{ $member->name }}</div>
                                        <div class="text-xs text-stone-500 font-mono">{{ $member->username }} • {{ $member->role->label() }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" color="stone" inset="top bottom">Activo</flux:badge>
                                    @if($canDetails)
                                        <flux:button wire:click="showMemberDetails({{ $member->id }})" wire:loading.attr="disabled" wire:target="showMemberDetails({{ $member->id }})" variant="ghost" size="xs" icon="eye" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Success Modal --}}
    <flux:modal name="promotion-success" class="min-w-[24rem]">
        <div class="space-y-6">
            <div class="flex flex-col items-center text-center space-y-2">
                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mb-2">
                    <flux:icon.check class="w-6 h-6 text-green-600" />
                </div>
                <flux:heading size="lg">¡Colaborador Creado!</flux:heading>
                <flux:subheading>Has convertido a <b>{{ $promotedName }}</b> en colaborador exitosamente.</flux:subheading>
            </div>

            <div class="bg-stone-50 dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl p-4 space-y-4">
                <div>
                    <flux:label>Nombre de Usuario</flux:label>
                    <div class="flex items-center justify-between mt-1">
                        <code class="text-sage-600 font-bold">{{ $generatedUsername }}</code>
                        <flux:button icon="clipboard" size="xs" variant="ghost" />
                    </div>
                </div>
                <div>
                    <flux:label>Contraseña Temporal</flux:label>
                    <div class="flex items-center justify-between mt-1">
                        <code class="text-sage-600 font-bold">{{ $temporaryPassword }}</code>
                        <flux:button icon="clipboard" size="xs" variant="ghost" />
                    </div>
                </div>
            </div>

            <p class="text-xs text-stone-500 text-center">
                Por favor, comparte estas credenciales con el nuevo colaborador. Se le pedirá cambiarla en su primer acceso (si habilitas esa opción).
            </p>

            <flux:button wire:click="$refresh" x-on:click="Flux.modal('promotion-success').close()" variant="primary" class="w-full bg-sage-600">
                Entendido
            </flux:button>
        </div>
    </flux:modal>
    {{-- Member Details Modal --}}
    <flux:modal name="member-details" class="min-w-[22rem]">
        @if($memberDetails)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Detalles del Miembro</flux:heading>
                    <flux:subheading>Acceso y credenciales para <b>{{ $memberDetails['name'] }}</b></flux:subheading>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input label="Rol" value="{{ $memberDetails['role'] }}" readonly variant="filled" />
                        <flux:input label="Usuario" value="{{ $memberDetails['username'] }}" readonly variant="filled" />
                    </div>

                    <div x-data="{ show: false }">
                        <flux:label>Contraseña de Acceso</flux:label>
                        <div class="flex items-center gap-2 mt-1">
                            <template x-if="!show">
                                <flux:input type="password" value="{{ $memberDetails['temporary_password'] }}" readonly class="flex-1 font-mono" />
                            </template>
                            <template x-if="show">
                                <flux:input type="text" value="{{ $memberDetails['temporary_password'] }}" readonly class="flex-1 font-mono" />
                            </template>
                            <flux:button x-on:click="show = !show" ::icon="show ? 'eye-slash' : 'eye'" variant="outline" size="sm" />
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-stone-100 dark:border-stone-800">
                    <flux:button x-on:click="Flux.modal('member-details').close()" variant="primary" class="w-full bg-sage-600">Cerrar</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
