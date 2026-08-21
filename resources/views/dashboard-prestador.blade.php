<x-layouts::app :title="__('Dashboard Prestador')">
    <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-6 sm:px-8 lg:px-10">
        <header class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91] dark:text-blue-400">{{ __('Panel de Prestador') }}</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ __('Inicio') }}</h1>
                    <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('Administra tus periodos de facturación y sube tus comprobantes.') }}</p>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-[#0f2f5f]/10 bg-[#0f2f5f]/5 px-4 py-2.5 shadow-xs dark:border-blue-900/40 dark:bg-blue-950/40">
                    <flux:avatar
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        class="bg-[#0f2f5f] text-white shadow-xs dark:bg-blue-600"
                    />
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[#1e4f91] dark:text-blue-300">{{ __('Prestador') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100 leading-tight">{{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Access cards -->
        <div class="grid gap-6 sm:grid-cols-2">
            <!-- Rondas de Facturación Card -->
            <a href="{{ route('billing-periods') }}" class="group block rounded-xl border border-slate-200 bg-white p-6 shadow-sm hover:border-[#1e4f91] dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-500 transition-colors">
                <div class="flex items-center gap-4">
                    <div class="flex size-12 items-center justify-center rounded-xl bg-blue-50 text-[#0f2f5f] group-hover:bg-[#0f2f5f] group-hover:text-white dark:bg-blue-950/60 dark:text-blue-400 dark:group-hover:bg-blue-600 dark:group-hover:text-white transition-colors">
                        <flux:icon.calendar class="size-6" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-slate-100 group-hover:text-[#1e4f91] dark:group-hover:text-blue-400 transition-colors">{{ __('Rondas de Facturación') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Crea periodos y selecciona los prestadores autorizados.') }}</p>
                    </div>
                </div>
            </a>

            <!-- Mis Facturas Card -->
            <a href="{{ route('prestador.invoices') }}" class="group block rounded-xl border border-slate-200 bg-white p-6 shadow-sm hover:border-[#1e4f91] dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-500 transition-colors">
                <div class="flex items-center gap-4">
                    <div class="flex size-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white dark:bg-emerald-950/60 dark:text-emerald-400 dark:group-hover:bg-emerald-500 dark:group-hover:text-white transition-colors">
                        <flux:icon.document-text class="size-6" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-slate-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">{{ __('Mis Facturas') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Sube comprobantes para tus periodos de facturación activos.') }}</p>
                    </div>
                </div>
            </a>
        </div>
    </main>
</x-layouts::app>
