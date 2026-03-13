<?php

use function Laravel\Folio\middleware;

middleware(['auth', 'verified']);
?>

<x-layouts.app>
    @php
        $guest = \App\Models\Guest::where('slug', $slug)->firstOrFail();
    @endphp
    <livewire:guests.detail :guest="$guest" />
</x-layouts.app>
