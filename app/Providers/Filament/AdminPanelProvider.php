<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('PT. FUJI SEAT INDONESIA')
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->maxContentWidth(MaxWidth::Full)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString('
                    <style>
                        .fi-resource-material-steels .fi-ta-header-cell-label,
                        .fi-resource-stamping-parts .fi-ta-header-cell-label,
                        .fi-resource-fasteners .fi-ta-header-cell-label,
                        .fi-relation-manager .fi-ta-header-cell-label {
                            font-size: 0.875rem !important;
                            font-weight: 700 !important;
                            line-height: 1.25 !important;
                        }

                        /* Sembunyikan ikon panah sort ke atas dan ke bawah di samping kanan nama kolom header */
                        .fi-ta-header-cell-sort-icon {
                            display: none !important;
                        }

                        /* Sembunyikan tombol Toggle Columns (ikon garis 3 di samping kanan filter data pada tabel) */
                        .fi-ta-col-toggle,
                        .fi-ta-col-toggle-btn,
                        [wire\:key*="table.column-toggle"],
                        .fi-ta-header-toolbar div:has(> button > svg[class*="view-columns"]),
                        .fi-ta-header-toolbar button:has(svg[class*="view-columns"]),
                        .fi-ta-header-toolbar button:has(svg[class*="bars"]),
                        .fi-ta-header [aria-label*="Toggle columns" i],
                        .fi-ta-header [aria-label*="toggle columns" i],
                        .fi-ta-header [aria-label*="kolom" i] {
                            display: none !important;
                        }

                        /* Konsistensi tabel full width tanpa horizontal slide */
                        .fi-ta-table {
                            width: 100% !important;
                            min-width: 100% !important;
                        }

                        /* Sembunyikan tulisan informasi kecil helper text pada form create/edit */
                        .fi-fo-field-wrp-helper-text {
                            display: none !important;
                        }

                        /* =========================================================================
                           POSISIKAN TOMBOL KEMBALI DI SEBELAH KIRI (HEADER & FORM FOOTER)
                           ========================================================================= */

                        /* 1. Header Halaman: Pindahkan tombol Kembali ke sebelah kiri judul */
                        .fi-header:has(.fi-btn-back, a:has(svg[class*="arrow-left"]), button:has(svg[class*="arrow-left"])) {
                            display: flex !important;
                            flex-direction: row !important;
                            flex-wrap: wrap !important;
                            align-items: center !important;
                            justify-content: flex-start !important;
                            gap: 0.75rem !important;
                        }

                        .fi-header:has(.fi-btn-back, a:has(svg[class*="arrow-left"]), button:has(svg[class*="arrow-left"])) > div:last-child,
                        .fi-header:has(.fi-btn-back, a:has(svg[class*="arrow-left"]), button:has(svg[class*="arrow-left"])) > div:last-child > .fi-ac {
                            display: contents !important;
                        }

                        /* Tombol Kembali / Back diletakkan di paling kiri (order: -1) */
                        .fi-header .fi-btn-back,
                        .fi-header a:has(svg[class*="arrow-left"]),
                        .fi-header button:has(svg[class*="arrow-left"]) {
                            order: -1 !important;
                            margin-right: 0.5rem !important;
                            align-self: center !important;
                        }

                        /* Judul halaman dan breadcrumbs berada tepat setelah tombol kembali dan mendorong aksi lain ke kanan */
                        .fi-header:has(.fi-btn-back, a:has(svg[class*="arrow-left"]), button:has(svg[class*="arrow-left"])) > div:first-child {
                            order: 0 !important;
                            flex: 1 1 auto !important;
                            margin-right: auto !important;
                        }

                        /* Aksi header lainnya (Hapus, Lembar Kalkulasi, Cetak PDF, dll) tetap berada di sisi kanan */
                        .fi-header:has(.fi-btn-back, a:has(svg[class*="arrow-left"]), button:has(svg[class*="arrow-left"])) .fi-btn:not(.fi-btn-back):not(:has(svg[class*="arrow-left"])) {
                            order: 2 !important;
                            margin-left: 0.25rem !important;
                        }

                        /* 2. Form Footer: Posisikan tombol Batal / Kembali di sebelah kiri tombol Simpan */
                        .fi-form-actions .fi-ac {
                            display: flex !important;
                            flex-wrap: wrap !important;
                            align-items: center !important;
                        }

                        .fi-form-actions .fi-ac button[type="button"],
                        .fi-form-actions .fi-ac a:has(svg[class*="arrow-left"]),
                        .fi-form-actions .fi-ac button:has(svg[class*="arrow-left"]) {
                            order: -1 !important;
                        }

                        .fi-form-actions .fi-ac button[type="submit"] {
                            order: 1 !important;
                        }
                    </style>
                ')
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->navigationGroups([
                'Master Data',
                'Kalkulator & Purchasing',
                'Manajemen Pengguna',
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
