@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2 text-center">
    <flux:heading size="xl" class="text-slate-950 dark:!text-slate-100">{{ $title }}</flux:heading>
    <flux:subheading class="text-slate-500 dark:!text-slate-400">{{ $description }}</flux:subheading>
</div>
