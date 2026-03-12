<?php
use function Laravel\Folio\middleware;
middleware(['auth', 'verified']);
?>

<x-layouts.app>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">Lista de Invitados</flux:heading>
            <flux:subheading>Gestiona quiénes están invitados a tu gran día.</flux:subheading>
        </div>
        {{-- Add Guest trigger is already in Sidebar or Dashboard, but we can add one here --}}
    </div>

    <livewire:guests.list />
</x-layouts.app>
