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
            fn (): \Illuminate\Contracts\View\View => view('filament.hooks.head-scripts'),
        );

    }
}
