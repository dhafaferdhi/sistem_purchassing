<div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-6 text-white shadow-lg dark:border-gray-800">
    <!-- Background Watermark Pattern -->
    <div class="pointer-events-none absolute -right-12 -top-12 h-64 w-64 rounded-full bg-blue-500/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-12 -left-12 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>

    <div class="relative z-10 flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
        <!-- Title & Executive Subtitle -->
        <div class="space-y-2 max-w-2xl">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-500/20 px-3 py-1 text-xs font-bold text-blue-300 ring-1 ring-inset ring-blue-500/30">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                    PT. FUJI SEAT INDONESIA
                </span>
                <span class="inline-flex items-center rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-300 ring-1 ring-inset ring-emerald-500/30">
                    Executive Cost Estimation Dashboard
                </span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white sm:text-3xl">
                Purchasing & Cost Planning System
            </h1>
            <p class="text-xs text-slate-300 sm:text-sm leading-relaxed">
                Platform terpusat pemantauan struktur harga pokok otomotif: <span class="font-semibold text-white">Material Steel</span>, <span class="font-semibold text-white">Stamping Parts</span>, <span class="font-semibold text-white">Fasteners</span>, dan eskalasi kurs berkala.
            </p>
        </div>

        <!-- Quick Status Pills -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-3 rounded-xl bg-white/5 px-4 py-2.5 backdrop-blur-md ring-1 ring-white/10">
                <div class="rounded-lg bg-blue-500/20 p-2 text-blue-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase text-slate-400">Kurs Acuan USD</div>
                    <div class="text-base font-black text-white">Rp {{ number_format($usdRate, 0, ',', '.') }}</div>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl bg-white/5 px-4 py-2.5 backdrop-blur-md ring-1 ring-white/10">
                <div class="rounded-lg bg-emerald-500/20 p-2 text-emerald-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase text-slate-400">Periode Terpilih</div>
                    <div class="text-base font-black text-white">{{ $activeQuarter }} {{ $activeYear }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Bar (1-Click Shortcut) -->
    <div class="mt-6 border-t border-white/10 pt-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-semibold text-slate-300">
                Aksi Cepat Purchasing:
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $stampingCreateUrl }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    + Stamping Part
                </a>

                <a href="{{ $fastenerCreateUrl }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-blue-500 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    + Fastener
                </a>

                <a href="{{ $materialSteelUrl }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7h16" />
                    </svg>
                    Material Steel
                </a>

                <a href="{{ $rolloverUrl }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-purple-600/80 px-3 py-1.5 text-xs font-semibold text-white hover:bg-purple-600 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Salin Kuartal (Rollover)
                </a>
            </div>
        </div>
    </div>
</div>

