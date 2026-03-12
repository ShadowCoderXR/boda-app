<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BodaApp</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-stone-50 dark:bg-stone-900 font-sans text-stone-900 dark:text-stone-100">

    {{-- Sidebar for Desktop --}}
    <flux:sidebar sticky stashable class="hidden lg:flex bg-stone-50 dark:bg-stone-900 border-r border-stone-200 dark:border-stone-700">
        <flux:brand href="/dashboard" logo="https://fluxui.com/img/demo/logo.png" name="BodaApp" class="px-2 dark:hidden" />
        <flux:brand href="/dashboard" logo="https://fluxui.com/img/demo/logo-dark.png" name="BodaApp" class="px-2 hidden dark:flex" />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="home" href="/dashboard" :current="request()->is('dashboard')">Dashboard</flux:navlist.item>
            <flux:navlist.item icon="users" href="/invitados" :current="request()->is('invitados')">Invitados</flux:navlist.item>
            <flux:navlist.item icon="star" href="/padrinos" :current="request()->is('padrinos')">Padrinos</flux:navlist.item>
            <flux:navlist.item icon="photo" href="/inspiracion" :current="request()->is('inspiracion')">Inspiración</flux:navlist.item>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="table-cells" href="/settings/categories" :current="request()->is('settings/categories')">Datos</flux:navlist.item>
            <flux:navlist.item icon="cog-6-tooth" href="/settings">Ajustes</flux:navlist.item>
        </flux:navlist>
    </flux:sidebar>

    {{-- Main Content --}}
    <flux:main class="pb-32 lg:pb-12 px-4 md:px-6 lg:px-8 max-w-7xl mx-auto w-full min-h-screen">
        {{ $slot }}
    </flux:main>

    {{-- Bottom Tab Bar for Mobile --}}
    <div class="fixed bottom-0 left-0 z-50 w-full h-20 bg-white/80 backdrop-blur-md border-t border-stone-200 dark:bg-stone-900/80 dark:border-stone-800 lg:hidden safe-area-bottom">
        <div class="grid h-full w-full grid-cols-5 mx-auto font-medium">
            <a href="/dashboard" class="inline-flex flex-col items-center justify-center px-2 hover:bg-stone-50 dark:hover:bg-stone-800 group {{ request()->is('dashboard') ? 'text-sage-600 dark:text-sage-500' : 'text-stone-500 dark:text-stone-400' }}">
                <flux:icon.home class="w-6 h-6 mb-1" />
                <span class="text-[9px] uppercase tracking-tighter sm:tracking-wider">Inicio</span>
            </a>
            <a href="/invitados" class="inline-flex flex-col items-center justify-center px-2 hover:bg-stone-50 dark:hover:bg-stone-800 group {{ request()->is('invitados') ? 'text-sage-600 dark:text-sage-500' : 'text-stone-500 dark:text-stone-400' }}">
                <flux:icon.users class="w-6 h-6 mb-1 group-hover:text-sage-500 transition-colors" />
                <span class="text-[9px] uppercase tracking-tighter sm:tracking-wider">Invitados</span>
            </a>
            <a href="/padrinos" class="inline-flex flex-col items-center justify-center px-2 hover:bg-stone-50 dark:hover:bg-stone-800 group {{ request()->is('padrinos') ? 'text-sage-600 dark:text-sage-500' : 'text-stone-500 dark:text-stone-400' }}">
                <flux:icon.star class="w-6 h-6 mb-1 group-hover:text-gold-500 transition-colors" />
                <span class="text-[9px] uppercase tracking-tighter sm:tracking-wider">Padrinos</span>
            </a>
            <a href="/inspiracion" class="inline-flex flex-col items-center justify-center px-2 hover:bg-stone-50 dark:hover:bg-stone-800 group {{ request()->is('inspiracion') ? 'text-sage-600 dark:text-sage-500' : 'text-stone-500 dark:text-stone-400' }}">
                <flux:icon.photo class="w-6 h-6 mb-1 group-hover:text-gold-500 transition-colors" />
                <span class="text-[9px] uppercase tracking-tighter sm:tracking-wider">Inspir.</span>
            </a>
            <a href="/settings" class="inline-flex flex-col items-center justify-center px-2 hover:bg-stone-50 dark:hover:bg-stone-800 group {{ request()->is('settings*') ? 'text-sage-600 dark:text-sage-500' : 'text-stone-500 dark:text-stone-400' }}">
                <flux:icon.cog-6-tooth class="w-6 h-6 mb-1 group-hover:text-sage-500 transition-colors" />
                <span class="text-[9px] uppercase tracking-tighter sm:tracking-wider">Ajustes</span>
            </a>
        </div>
    </div>

    {{-- <flux:toast /> --}}
    @fluxScripts
</body>
</html>
