<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->path('admin-hidden')
            ->login(\App\Filament\Pages\Auth\CustomLogin::class)
            ->favicon(asset('images/icon-192.png'))
            ->passwordReset()   // Enables the "Forgot Password?" flow via email
            ->brandName('Erick Trading Co.')
            ->brandLogoHeight('3rem')
            ->maxContentWidth(\Filament\Support\Enums\MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Light)
            ->colors([
                'primary' => [
                    50 => '250, 250, 250',
                    100 => '244, 244, 245',
                    200 => '228, 228, 231',
                    300 => '212, 212, 216',
                    400 => '161, 161, 170',
                    500 => '24, 24, 27',      // Zinc 900
                    600 => '9, 9, 11',        // Zinc 950
                    700 => '0, 0, 0',         // Pure Black
                    800 => '0, 0, 0',
                    900 => '0, 0, 0',
                    950 => '0, 0, 0',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                // Pages\Dashboard::class, // Replaced by our custom Dashboard
                \App\Filament\Pages\Dashboard::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
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
            ->font('Cairo')
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIDEBAR_NAV_END,
                fn () => view('filament.hooks.admin-sidebar-quick-actions')
            )
            ->renderHook(
                'panels::head.end',
                fn () => view('filament.hooks.custom-styles')
            )
            ->renderHook(
                'panels::user-menu.before',
                fn () => view('filament.hooks.privacy-toggle')
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.hooks.pwa-install-banner')
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
