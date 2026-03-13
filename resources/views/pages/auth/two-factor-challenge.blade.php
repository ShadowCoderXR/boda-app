<x-layouts.auth :title="__('Two-factor challenge')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Two-factor challenge')"
            :description="__('Please confirm access to your account by entering the authentication code provided by your authenticator application.')"
        />

        <div x-data="{ recovery: false }">
            <div x-show="! recovery">
                <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-6">
                    @csrf

                    <flux:input
                        name="code"
                        :label="__('Code')"
                        type="text"
                        inputmode="numeric"
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="000000"
                    />

                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Log in') }}
                    </flux:button>
                </form>
            </div>

            <div x-show="recovery">
                <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-6">
                    @csrf

                    <flux:input
                        name="recovery_code"
                        :label="__('Recovery Code')"
                        type="text"
                        autocomplete="one-time-code"
                        placeholder="abcdef-123456"
                    />

                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Log in') }}
                    </flux:button>
                </form>
            </div>

            <div class="flex items-center justify-end mt-4">
                <flux:button
                    variant="ghost"
                    size="sm"
                    x-show="! recovery"
                    x-on:click="recovery = true"
                >
                    {{ __('Use a recovery code') }}
                </flux:button>

                <flux:button
                    variant="ghost"
                    size="sm"
                    x-show="recovery"
                    x-on:click="recovery = false"
                >
                    {{ __('Use an authentication code') }}
                </flux:button>
            </div>
        </div>
    </div>
</x-layouts.auth>
