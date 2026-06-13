<div class="grid gap-6 lg:grid-cols-[240px_1fr]">
    <aside class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
        </flux:navlist>
    </aside>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <flux:heading class="!text-slate-950">{{ $heading ?? '' }}</flux:heading>
        <flux:subheading class="!text-slate-500">{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-6 w-full max-w-2xl">
            {{ $slot }}
        </div>
    </section>
</div>
