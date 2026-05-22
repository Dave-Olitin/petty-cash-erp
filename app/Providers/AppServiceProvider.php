<?php

namespace App\Providers;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\FloatReplenishment;
use App\Observers\TransactionObserver;
use App\Observers\VoucherObserver;
use App\Observers\FloatReplenishmentObserver;
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
        Voucher::observe(VoucherObserver::class);
        FloatReplenishment::observe(FloatReplenishmentObserver::class);

        // Rate limit login attempts: max 5 per minute per IP, 10 per 15min per email
        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $throttleKey = \Illuminate\Support\Str::transliterate(\Illuminate\Support\Str::lower($request->input('email')).'|'.$request->ip());
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinutes(1, 5)->by($request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinutes(15, 10)->by($throttleKey),
            ];
        });

        FilamentView::registerRenderHook(
            'panels::auth.login.form.before',
            fn (): string => Blade::render('<div class="flex justify-center mb-4"><div class="text-xl font-bold text-primary-600"></div></div>'),
        );

        // NOTE: head-scripts is also registered in each PanelProvider via ->renderHook().
        // Do NOT add it here again to avoid double-loading scripts on the Vouchers panel.

    }
}
