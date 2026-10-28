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

                        /* Sembunyikan tombol Toggle Columns di toolbar tabel */
                        .fi-ta-col-toggle-btn,
                        button[wire\:click*="toggleTableColumnForm"],
                        button:has(svg[class*="heroicon-m-view-columns"]),
                        button:has(svg[class*="heroicon-o-view-columns"]),
                        [aria-label*="Toggle columns"],
                        [aria-label*="toggle columns"] {
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
