<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <style>
        /* Compact & Fit table style agar Fasteners fit 100% full width */
        .fi-resource-fasteners .fi-ta-content {
            overflow-x: auto;
        }
        .fi-resource-fasteners .fi-ta-table {
            table-layout: auto !important;
            width: 100% !important;
            min-width: 100% !important;
        }
        .fi-resource-fasteners th.fi-ta-header-cell {
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
            padding-top: 0.55rem !important;
            padding-bottom: 0.55rem !important;
            vertical-align: middle !important;
        }
        .fi-resource-fasteners td.fi-ta-cell {
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
            padding-top: 0.4rem !important;
            padding-bottom: 0.4rem !important;
        }
        .fi-resource-fasteners .fi-ta-header-cell-label {
            font-size: 0.775rem !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            white-space: normal !important;
            letter-spacing: -0.01em !important;
            color: #111827 !important;
        }
        .dark .fi-resource-fasteners .fi-ta-header-cell-label {
            color: #f9fafb !important;
        }

        /* Default item teks: jangan dipaksa wrap agar simbol mata uang ($ dan Rp) selalu tepat di samping angka */
        .fi-resource-fasteners .fi-ta-text-item {
            font-size: 0.785rem !important;
            line-height: 1.25 !important;
            white-space: nowrap !important;
        }

        /* Seluruh kolom mata uang & angka: pastikan simbol $ dan Rp tidak terputus/terpisah dari angka */
        .fi-resource-fasteners td[class*="col-exchange_rate"] .fi-ta-text-item,
        .fi-resource-fasteners td[class*="col-wire_price_usd_kg"] .fi-ta-text-item,
        .fi-resource-fasteners td[class*="col-part_cost"] .fi-ta-text-item {
            white-space: nowrap !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.25rem !important;
            font-variant-numeric: tabular-nums !important;
        }

        /* Part name ke samping saja (nowrap) */
        .fi-resource-fasteners td[class*="col-part_name"] .fi-ta-text-item {
            white-space: nowrap !important;
        }
        .fi-resource-fasteners td[class*="col-supplier_name"] .fi-ta-text-item {
            max-width: 130px !important;
            white-space: normal !important;
            word-break: break-word !important;
        }

        .fi-resource-fasteners .fi-badge {
            padding-left: 0.3rem !important;
            padding-right: 0.3rem !important;
            padding-top: 0.05rem !important;
            padding-bottom: 0.05rem !important;
            font-size: 0.675rem !important;
        }
        .fi-resource-fasteners .fi-ta-actions {
            gap: 0.2rem !important;
            justify-content: flex-end !important;
            flex-wrap: nowrap !important;
        }
        .fi-resource-fasteners .fi-ta-actions .fi-icon-btn {
            width: 1.65rem !important;
            height: 1.65rem !important;
            padding: 0.2rem !important;
        }

        /* Sembunyikan ikon panah sort ke atas dan ke bawah di samping kanan nama kolom */
        .fi-resource-fasteners .fi-ta-header-cell-sort-icon {
            display: none !important;
        }

        /* Sembunyikan tombol toggle columns */
        .fi-resource-fasteners .fi-ta-col-toggle-btn,
        .fi-resource-fasteners button[wire\:click*="toggleTableColumnForm"],
        .fi-resource-fasteners [aria-label*="Toggle columns"],
        .fi-resource-fasteners [aria-label*="toggle columns"] {
            display: none !important;
        }
    </style>

    <div class="flex flex-col gap-y-4">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>