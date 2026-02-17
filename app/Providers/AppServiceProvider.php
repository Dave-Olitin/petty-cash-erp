<?php

namespace App\Providers;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(!app()->isProduction());

        Transaction::observe(TransactionObserver::class);

        FilamentView::registerRenderHook(
            'panels::auth.login.form.before',
            fn (): string => Blade::render('<div class="flex justify-center mb-4"><div class="text-xl font-bold text-primary-600"></div></div>'),
        );

        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => Blade::render('
                <style>
                    /* Login Page Background */
                    .fi-simple-layout {
                        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
                        min-height: 100vh;
                    }
                    .fi-simple-main {
                        background-color: rgba(255, 255, 255, 0.95);
                        border-radius: 1rem;
                        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                    }
                </style>
                <link rel="manifest" href="/manifest.json">
                <meta name="theme-color" content="#3b82f6">
                <script>
                    if ("serviceWorker" in navigator) {
                        window.addEventListener("load", function() {
                            navigator.serviceWorker.register("/sw.js").then(function(registration) {
                                console.log("ServiceWorker registration successful with scope: ", registration.scope);
                            }, function(err) {
                                console.log("ServiceWorker registration failed: ", err);
                            });
                        });
                    }
                </script>
            '),
        );
    }
}
