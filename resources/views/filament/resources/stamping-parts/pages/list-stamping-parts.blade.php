<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <style>
        /* Compact & Fit table style agar Stamping Part fit 100% full width */
        .fi-resource-stamping-parts .fi-ta-content {
            overflow-x: auto;
        }
        .fi-resource-stamping-parts .fi-ta-table {
            table-layout: auto !important;
            width: 100% !important;
            min-width: 100% !important;
        }
        .fi-resource-stamping-parts th.fi-ta-header-cell {
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
            padding-top: 0.55rem !important;
            padding-bottom: 0.55rem !important;
            vertical-align: middle !important;
        }
        .fi-resource-stamping-parts td.fi-ta-cell {
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
            padding-top: 0.4rem !important;
            padding-bottom: 0.4rem !important;
        }
        .fi-resource-stamping-parts .fi-ta-header-cell-label {
            font-size: 0.775rem !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            white-space: normal !important;
            letter-spacing: -0.01em !important;
            color: #111827 !important;
        }
        .dark .fi-resource-stamping-parts .fi-ta-header-cell-label {
            color: #f9fafb !important;
        }

        /* Default item teks: jangan dipaksa wrap agar simbol mata uang ($ dan Rp) selalu tepat di samping angka */
        .fi-resource-stamping-parts .fi-ta-text-item {
            font-size: 0.785rem !important;
            line-height: 1.25 !important;
            white-space: nowrap !important;
        }

        /* Seluruh kolom mata uang & angka: pastikan simbol $ dan Rp tidak terputus/terpisah dari angka */
        .fi-resource-stamping-parts td[class*="col-exchange_rate"] .fi-ta-text-item,
        .fi-resource-stamping-parts td[class*="col-coil_price"] .fi-ta-text-item,
        .fi-resource-stamping-parts td[class*="col-sheet_price"] .fi-ta-text-item,
        .fi-resource-stamping-parts td[class*="col-part_cost"] .fi-ta-text-item {
            white-space: nowrap !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.25rem !important;
            font-variant-numeric: tabular-nums !important;
        }

        /* Hanya nama part dan supplier yang boleh dibungkus (wrap) jika teks panjang */
        .fi-resource-stamping-parts td[class*="col-part_name"] .fi-ta-text-item {
            max-width: 155px !important;
            white-space: normal !important;
            word-break: break-word !important;
        }
        .fi-resource-stamping-parts td[class*="col-supplier_name"] .fi-ta-text-item {
            max-width: 110px !important;
            white-space: normal !important;
            word-break: break-word !important;
        }

        .fi-resource-stamping-parts .fi-badge {
            padding-left: 0.3rem !important;
            padding-right: 0.3rem !important;
            padding-top: 0.05rem !important;
            padding-bottom: 0.05rem !important;
            font-size: 0.675rem !important;
        }
        .fi-resource-stamping-parts .fi-ta-actions {
            gap: 0.2rem !important;
            justify-content: flex-end !important;
            flex-wrap: nowrap !important;
        }
        .fi-resource-stamping-parts .fi-ta-actions .fi-icon-btn {
            width: 1.65rem !important;
            height: 1.65rem !important;
            padding: 0.2rem !important;
        }

        /* Sembunyikan ikon panah sort ke atas dan ke bawah di samping kanan nama kolom */
        .fi-resource-stamping-parts .fi-ta-header-cell-sort-icon {
            display: none !important;
        }

        /* Sembunyikan tombol toggle columns */
        .fi-resource-stamping-parts .fi-ta-col-toggle-btn,
        .fi-resource-stamping-parts button[wire\:click*="toggleTableColumnForm"],
        .fi-resource-stamping-parts [aria-label*="Toggle columns"],
        .fi-resource-stamping-parts [aria-label*="toggle columns"] {
            display: none !important;
        }
    </style>

    <div class="flex flex-col gap-y-4">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>
