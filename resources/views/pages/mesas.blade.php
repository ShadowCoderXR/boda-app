<?php
use function Laravel\Folio\name;
name('mesas');
?>
<x-layouts.app title="Mesas">
    <div class="space-y-6">
        <div class="flex items-end justify-between mb-6">
            <div>
                <flux:heading size="xl" level="1">Gestión de Mesas</flux:heading>
                <flux:subheading size="lg">
                    Organiza a tus invitados confirmados en mesas
                </flux:subheading>
            </div>
        </div>
        
        <livewire:seating.seating-manager />
    </div>
</x-layouts.app>
