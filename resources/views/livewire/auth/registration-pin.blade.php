<x-layouts::auth :title="__('Registration PIN')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Employee registration')" :description="__('Enter the registration PIN to continue.')" />

        <form method="POST" action="{{ route('register.pin') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="pin"
                :label="__('PIN')"
                :value="old('pin')"
                type="password"
                required
                autofocus
                inputmode="numeric"
                autocomplete="one-time-code"
                :placeholder="__('Registration PIN')"
            />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Continue') }}
            </flux:button>
        </form>

        <div class="space-x-1 text-center text-sm text-zinc-600 rtl:space-x-reverse dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
