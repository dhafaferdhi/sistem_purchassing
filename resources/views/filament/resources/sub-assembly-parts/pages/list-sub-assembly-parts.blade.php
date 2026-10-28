<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <style>
        /* Compact & Fit table style agar Sub-Assembly tidak perlu horizontal scroll berlebih */
        .fi-resource-sub-assembly-parts .fi-ta-content {
            overflow-x: auto;
        }
        .fi-resource-sub-assembly-parts .fi-ta-table {
            table-layout: auto !important;
            width: 100% !important;
        }
        .fi-resource-sub-assembly-parts th.fi-ta-header-cell {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
            padding-top: 0.65rem !important;
            padding-bottom: 0.65rem !important;
            vertical-align: middle !important;
        }
        .fi-resource-sub-assembly-parts td.fi-ta-cell {
            padding-left: 0.45rem !important;
            padding-right: 0.45rem !important;
            padding-top: 0.45rem !important;
            padding-bottom: 0.45rem !important;
        }
        .fi-resource-sub-assembly-parts .fi-ta-header-cell-label {
            font-size: 0.875rem !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            white-space: normal !important;
            letter-spacing: -0.01em !important;
            color: #111827 !important;
        }
        .dark .fi-resource-sub-assembly-parts .fi-ta-header-cell-label {
            color: #f9fafb !important;
        }
        .fi-resource-sub-assembly-parts .fi-ta-text-item {
            font-size: 0.8125rem !important;
            line-height: 1.25 !important;
        }
        .fi-resource-sub-assembly-parts .fi-badge {
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
            padding-top: 0.05rem !important;
            padding-bottom: 0.05rem !important;
            font-size: 0.7rem !important;
        }
        .fi-resource-sub-assembly-parts .fi-ta-actions {
            gap: 0.35rem !important;
            justify-content: flex-start !important;
            flex-wrap: nowrap !important;
        }
        .fi-resource-sub-assembly-parts .fi-ta-actions .fi-icon-btn {
            width: 1.75rem !important;
            height: 1.75rem !important;
            padding: 0.25rem !important;
        }

        /* Sembunyikan ikon panah sort ke atas dan ke bawah di samping kanan nama kolom */
        .fi-resource-sub-assembly-parts .fi-ta-header-cell-sort-icon {
            display: none !important;
        }

        /* Sembunyikan tombol toggle columns */
        .fi-resource-sub-assembly-parts .fi-ta-col-toggle-btn,
        .fi-resource-sub-assembly-parts button[wire\:click*="toggleTableColumnForm"],
        .fi-resource-sub-assembly-parts [aria-label*="Toggle columns"],
        .fi-resource-sub-assembly-parts [aria-label*="toggle columns"] {
            display: none !important;
        }
    </style>

    <div class="flex flex-col gap-y-4">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>

