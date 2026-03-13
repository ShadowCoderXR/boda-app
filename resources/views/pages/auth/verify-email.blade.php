<x-layouts.auth :title="__('Verify email')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Verify email')"
            :description="__('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.')"
        />

        @if (session('status') == 'verification-link-sent')
            <flux:text class="text-center font-medium !text-sage-600">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </flux:text>
        @endif

        <div class="flex items-center justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <flux:button type="submit" variant="primary">
                    {{ __('Resend verification email') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <flux:button type="submit" variant="ghost">
                    {{ __('Log out') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts.auth>
