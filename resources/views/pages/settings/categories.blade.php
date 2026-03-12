<?php
use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Volt;

middleware(['auth', 'verified']);
name('settings.categories');

?>

@volt
<div>
    {{-- Volt anonymous component root --}}
</div>
@endvolt

<x-layouts.app>
    <div class="max-w-4xl mx-auto py-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <flux:heading size="xl">Gestión de Datos</flux:heading>
                <flux:subheading>Administra las categorías y grupos de invitados.</flux:subheading>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            {{-- Categories Section --}}
            <div>
                <livewire:settings.categories-manager />
            </div>

            {{-- Groups Section --}}
            <div>
                <livewire:settings.groups-manager />
            </div>
        </div>
    </div>
</x-layouts.app>
