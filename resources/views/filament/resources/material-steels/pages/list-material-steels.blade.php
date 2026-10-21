<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <style>
        /* Compact & Fit table style agar Material Steel tidak perlu horizontal scroll (slide) */
        .fi-resource-material-steels .fi-ta-content {
            overflow-x: auto;
        }
        .fi-resource-material-steels .fi-ta-table {
            table-layout: auto !important;
            width: 100% !important;
        }
        .fi-resource-material-steels th.fi-ta-header-cell {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
            padding-top: 0.65rem !important;
            padding-bottom: 0.65rem !important;
            vertical-align: middle !important;
        }
        .fi-resource-material-steels td.fi-ta-cell {
            padding-left: 0.45rem !important;
            padding-right: 0.45rem !important;
            padding-top: 0.45rem !important;
            padding-bottom: 0.45rem !important;
        }
        .fi-resource-material-steels .fi-ta-header-cell-label {
            font-size: 0.875rem !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            white-space: normal !important;
            letter-spacing: -0.01em !important;
            color: #111827 !important;
        }
        .dark .fi-resource-material-steels .fi-ta-header-cell-label {
            color: #f9fafb !important;
        }
        .fi-resource-material-steels .fi-ta-text-item {
            font-size: 0.8125rem !important;
            line-height: 1.25 !important;
        }
        .fi-resource-material-steels .fi-ta-text-item-description {
            font-size: 0.675rem !important;
            line-height: 1.1 !important;
        }
        .fi-resource-material-steels .fi-badge {
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
            padding-top: 0.05rem !important;
            padding-bottom: 0.05rem !important;
            font-size: 0.7rem !important;
        }
        .fi-resource-material-steels .fi-ta-actions {
            gap: 0.2rem !important;
            justify-content: flex-end;
        }
        .fi-resource-material-steels .fi-ta-actions .fi-icon-btn {
            width: 1.75rem !important;
            height: 1.75rem !important;
            padding: 0.25rem !important;
        }
    </style>

    <div class="flex flex-col gap-y-4">
        <!-- Control Bar: Pilihan Tahun di Atas Kuartal Q1-Q4 -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <!-- Left: Quick Year Switcher Buttons -->
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <svg class="h-4 w-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Pilih Tahun:
                </span>

                <button
                    type="button"
                    wire:click="$set('selectedYear', '')"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ empty($selectedYear) ? 'bg-primary-600 text-white shadow-sm ring-1 ring-primary-600' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    Semua Tahun
                </button>

                @foreach ($this->getAvailableYears() as $year)
                    <button
                        type="button"
                        wire:click="$set('selectedYear', '{{ $year }}')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedYear == $year ? 'bg-primary-600 text-white shadow-sm ring-1 ring-primary-600' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                    >
                        Tahun {{ $year }}
                    </button>
                @endforeach
            </div>

            <!-- Right: Dropdown Selector & Status Label -->
            <div class="flex items-center gap-2.5">
                <span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">
                    Filter Aktif: <strong class="font-bold text-primary-600 dark:text-primary-400">{{ $selectedYear ? "Tahun {$selectedYear}" : "Semua Tahun" }}</strong>
                </span>

                <select
                    wire:model.live="selectedYear"
                    class="rounded-lg border-gray-300 py-1 pl-2.5 pr-8 text-xs font-semibold text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                    <option value="">Semua Tahun</option>
                    @foreach ($this->getAvailableYears() as $year)
                        <option value="{{ $year }}">Tahun {{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Filament Tabs Kuartal (Q1 - Q4) di Bawah Pilihan Tahun -->
        <x-filament-panels::resources.tabs />

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>

