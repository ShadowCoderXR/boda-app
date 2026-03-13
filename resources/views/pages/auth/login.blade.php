<x-layouts.auth :title="__('Iniciar sesión')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Bienvenido a BodaApp')" :description="__('Ingresa tu usuario y contraseña para gestionar tu gran día')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Username -->
            <flux:input
                name="username"
                :label="__('Usuario')"
                :value="old('username')"
                type="text"
                required
                autofocus
                placeholder="Nombre de usuario"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Contraseña')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Contraseña')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0 text-sage-600 hover:text-sage-700" :href="route('password.request')" wire:navigate>
                        {{ __('¿Olvidaste tu contraseña?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Recordarme')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full bg-sage-600 hover:bg-sage-700 border-0" data-test="login-button">
                    {{ __('Iniciar sesión') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts.auth>
