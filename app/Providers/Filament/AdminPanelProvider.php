<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::hex('#7FB3E6'),
                'gray' => Color::Slate,
                'success' => Color::hex('#1F6B4A'),
                'warning' => Color::hex('#8A5A1E'),
                'danger' => Color::hex('#9B2C35'),
            ])
            ->font('Plus Jakarta Sans')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->darkMode(false)
            ->navigationGroups([
                'Laporan',
                'Manajemen Aset',
                'Dokumen',
                'Organisasi',
                'Pengadaan',
                'Referensi',
                'Pengaturan',
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // Dimuat lewat STYLES_AFTER supaya berada sesudah stylesheet Filament,
            // sehingga penyesuaian padding tidak kalah oleh aturan bawaan.
            // Versi memakai waktu modifikasi file agar tidak tertahan cache browser.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                    '<link rel="stylesheet" href="' . asset('css/admin-theme.css')
                    . '?v=' . (is_file(public_path('css/admin-theme.css')) ? filemtime(public_path('css/admin-theme.css')) : '1')
                    . '">',
                ),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
