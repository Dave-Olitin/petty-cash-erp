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
                        /* Base styling for mobile (stacked) */
                        .fi-simple-layout {
                            background-color: #ffffff !important;
                            position: relative;
                            display: flex;
                            flex-direction: column !important;
                        }
                        
                        /* Left side (Logo container) */
                        .fi-simple-layout::before {
                            content: "";
                            display: block;
                            height: 250px; /* mobile height */
                            width: 100%;
                            background-color: #ffffff;
                            background-image: url("https://etc.ericktrading.ae/assets/etclogo-NCke5EIL.jpeg");
                            background-size: auto 60%;
                            background-position: center;
                            background-repeat: no-repeat;
                        }

                        /* Right side container (Form area) */
                        .fi-simple-main-ctn {
                            position: relative;
                            flex: 1;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            padding: 1.5rem !important;
                            background-image: url("https://images.pexels.com/photos/34577/pexels-photo.jpg") !important;
                            background-size: cover !important;
                            background-position: center !important;
                            width: 100%;
                            background-color: transparent !important;
                        }

                        /* Subtle white wash over the pexels background */
                        .fi-simple-main-ctn::before {
                            content: "";
                            position: absolute;
                            top: 0; left: 0; right: 0; bottom: 0;
                            background: rgba(255, 255, 255, 0.4);
                            backdrop-filter: blur(4px);
                            pointer-events: none;
                            z-index: 0;
                        }

                        /* Glass card effect for the login form */
                        .fi-simple-main-ctn main.fi-simple-main {
                            background: rgba(255, 255, 255, 0.98) !important;
                            backdrop-filter: blur(15px);
                            border-radius: 1.5rem !important;
                            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2) !important;
                            border: 1px solid rgba(255,255,255,0.8) !important;
                            width: 100%;
                            max-width: 28rem !important;
                            position: relative;
                            z-index: 1; /* keep above the background blur */
                            margin: 0 !important;
                        }

                        /* Hide the duplicate brand text in the header */
                        .fi-logo {
                            display: none !important;
                        }

                        /* Desktop layout (Split screen) */
                        @media (min-width: 1024px) {
                            .fi-simple-layout {
                                flex-direction: row !important;
                            }
                            .fi-simple-layout::before {
                                flex: 1;
                                height: 100vh; /* full height */
                                border-right: 1px solid rgba(0,0,0,0.05);
                            }
                            .fi-simple-main-ctn {
                                flex: 1;
                                height: 100vh;
                            }
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
