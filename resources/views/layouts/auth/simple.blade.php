<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stone-50 antialiased dark:bg-stone-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="/" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-12 w-12 mb-2 items-center justify-center rounded-full bg-sage-600 text-white shadow-md">
                        <flux:icon.heart class="size-7 fill-current" />
                    </span>
                    <span class="text-2xl font-serif text-stone-800 dark:text-stone-100">BodaApp</span>
                    <span class="sr-only">BodaApp</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
