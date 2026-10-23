<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <style>
        /* Compact & Fit table style agar Stamping Part tidak perlu horizontal scroll */
        .fi-resource-stamping-parts .fi-ta-content {
            overflow-x: auto;
        }
        .fi-resource-stamping-parts .fi-ta-table {
            table-layout: auto !important;
            width: 100% !important;
        }
        .fi-resource-stamping-parts th.fi-ta-header-cell {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
            padding-top: 0.65rem !important;
            padding-bottom: 0.65rem !important;
            vertical-align: middle !important;
        }
        .fi-resource-stamping-parts td.fi-ta-cell {
            padding-left: 0.45rem !important;
            padding-right: 0.45rem !important;
            padding-top: 0.45rem !important;
            padding-bottom: 0.45rem !important;
        }
        .fi-resource-stamping-parts .fi-ta-header-cell-label {
            font-size: 0.875rem !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            white-space: normal !important;
            letter-spacing: -0.01em !important;
            color: #111827 !important;
        }
        .dark .fi-resource-stamping-parts .fi-ta-header-cell-label {
            color: #f9fafb !important;
        }
        .fi-resource-stamping-parts .fi-ta-text-item {
            font-size: 0.8125rem !important;
            line-height: 1.25 !important;
        }
        .fi-resource-stamping-parts .fi-badge {
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
            padding-top: 0.05rem !important;
            padding-bottom: 0.05rem !important;
            font-size: 0.7rem !important;
        }
        .fi-resource-stamping-parts .fi-ta-actions {
            gap: 0.2rem !important;
            justify-content: flex-end;
        }
        .fi-resource-stamping-parts .fi-ta-actions .fi-icon-btn {
            width: 1.75rem !important;
            height: 1.75rem !important;
            padding: 0.25rem !important;
        }
    </style>

    <div class="flex flex-col gap-y-4">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>
