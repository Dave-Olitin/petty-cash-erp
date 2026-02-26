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

class VouchersPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('vouchers')
            ->path('vouchers')
            ->login(\App\Filament\Vouchers\Pages\Auth\Login::class)
            ->brandName('PAYMENT VOUCHER')
            ->font('Cairo')
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Light)
            ->renderHook(
                \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => \Illuminate\Support\Facades\Blade::render('
                    <style>
                        /* Modern Background for Login */
                        .fi-main {
                            background-color: #ffffff !important;
                            background-image: url("https://images.pexels.com/photos/34577/pexels-photo.jpg") !important;
                            background-size: cover !important;
                            background-position: center !important;
                            background-repeat: no-repeat !important;
                            position: relative;
                            min-height: 100vh;
                        }
                        /* Add a subtle white wash over the background image to make the white theme pop */
                        .fi-main::before {
                            content: "";
                            position: absolute;
                            top: 0; left: 0; right: 0; bottom: 0;
                            background: rgba(255, 255, 255, 0.3);
                            backdrop-filter: blur(2px);
                            pointer-events: none;
                        }
                        /* Mobile responsive container */
                        .fi-simple-main-ctn {
                            padding: 1.5rem !important;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            min-height: 100vh;
                        }
                        /* Glass card effect for the login form */
                        .fi-simple-main .fi-simple-main-ctn .fi-section {
                            background: rgba(255, 255, 255, 0.98);
                            backdrop-filter: blur(15px);
                            border-radius: 1.5rem;
                            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2);
                            border: 1px solid rgba(255,255,255,0.8);
                            width: 100%;
                            margin: 1rem auto;
                        }
                        /* Header Text styling */
                        .fi-simple-header-heading {
                            font-weight: 800;
                            letter-spacing: -0.025em;
                            color: #111827;
                        }
                        .fi-simple-header-subheading {
                            font-size: 0.95rem;
                            color: #475569;
                        }
                    </style>
                ')
            )
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Vouchers/Resources'), for: 'App\\Filament\\Vouchers\\Resources')
            ->discoverPages(in: app_path('Filament/Vouchers/Pages'), for: 'App\\Filament\\Vouchers\\Pages')
            ->pages([
                \App\Filament\Vouchers\Pages\Dashboard::class,
            ])
            ->discoverClusters(in: app_path('Filament/Vouchers/Clusters'), for: 'App\\Filament\\Vouchers\\Clusters')
            ->discoverWidgets(in: app_path('Filament/Vouchers/Widgets'), for: 'App\\Filament\\Vouchers\\Widgets')
            ->widgets([
                //
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
            ])
            ->databaseNotifications();
    }
}
