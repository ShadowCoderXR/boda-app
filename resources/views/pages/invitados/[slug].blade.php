<?php
use function Laravel\Folio\middleware;
middleware(['auth', 'verified']);
?>

@php
    $guest = \App\Models\Guest::where('slug', $slug)->firstOrFail();
@endphp

<x-layouts.app>
    <div class="max-w-4xl mx-auto py-8">
        <div class="mb-6 flex items-center gap-4">
            <flux:button href="/invitados" variant="ghost" icon="chevron-left" shadow="false" />
            <div>
                <flux:heading size="xl">{{ $guest->name }}</flux:heading>
                <flux:subheading>Detalles del Invitado</flux:subheading>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:card class="md:col-span-2">
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

                    @if($guest->notes)
                    <div>
                        <flux:label>Notas de Menú / Alergias</flux:label>
                        <div class="mt-2 p-3 bg-stone-100 dark:bg-stone-800 rounded-lg text-sm italic">
                            {{ $guest->notes }}
                        </div>
                    </div>
                    @endif
                </div>
            </flux:card>

            <div class="space-y-6">
                <flux:card>
                    <flux:heading size="sm" class="mb-4">Código de Acceso</flux:heading>
                    <div class="p-3 bg-gold-50 dark:bg-gold-500/10 border border-gold-200 dark:border-gold-500/30 rounded-lg text-center font-mono font-bold text-gold-700 dark:text-gold-300">
                        {{ $guest->slug }}
                    </div>
                    <p class="text-[10px] text-stone-500 mt-2 text-center italic">Usa este slug para el acceso directo del invitado.</p>
                </flux:card>
            </div>
        </div>
    </div>
</x-layouts.app>
