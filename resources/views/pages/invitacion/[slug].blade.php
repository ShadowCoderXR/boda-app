<?php

use function Laravel\Folio\middleware;

// Publicly accessible invitation
?>

<x-layouts.guest>
    @php
        $guest = \App\Models\Guest::where('slug', $slug)->firstOrFail();
    @endphp
    
    <livewire:guests.invitation :guest="$guest" />
</x-layouts.guest>
