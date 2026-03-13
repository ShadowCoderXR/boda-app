<x-layouts.auth :title="__('Reset password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email')"
                :value="old('email', request()->email)"
                type="email"
                required
                autofocus
                autocomplete="username"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Password"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm Password')"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Confirm password"
                viewable
            />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Reset password') }}
            </flux:button>
        </form>
    </div>
</x-layouts.auth>
