@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="BodaApp" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-sage-600 text-white">
            <x-app-logo-icon class="size-5 fill-current" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="BodaApp" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-sage-600 text-white">
            <x-app-logo-icon class="size-5 fill-current" />
        </x-slot>
    </flux:brand>
@endif
