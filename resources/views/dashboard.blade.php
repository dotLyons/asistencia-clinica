<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('My attendance') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Latest attendance records') }}</flux:text>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-left text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Time') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Employee ID') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($attendances as $attendance)
                            <tr>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ str($attendance->type)->title() }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    {{ $attendance->occurred_at->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    {{ $attendance->occurred_at->format('H:i:s') }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    {{ $attendance->user_id }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                    {{ __('No attendance records yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
