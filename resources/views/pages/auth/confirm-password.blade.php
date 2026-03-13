<x-layouts.auth :title="__('Confirm password')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Confirm password')"
            :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
        />

        <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Contraseña')"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Contraseña"
                viewable
                autofocus
            />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Confirm') }}
            </flux:button>
        </form>
    </div>
</x-layouts.auth>
