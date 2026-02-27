<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/admin/transactions/{transaction}/print', function (\App\Models\Transaction $transaction) {
        // Branch users can only print their own branch's transactions
        if (auth()->user()->branch_id && auth()->user()->branch_id !== $transaction->branch_id) {
            abort(403);
        }

        $transaction->load(['branch', 'items.category', 'user']);

        return view('filament.pages.transaction-print', ['transaction' => $transaction]);
    })->name('transaction.print');

    Route::get('/admin/transactions/{transaction}/receipt', [\App\Http\Controllers\ReceiptController::class, 'show'])
        ->name('transaction.receipt');

    Route::get('/admin/vouchers/{voucher}/pdf', function (\App\Models\Voucher $voucher) {
        // Must have access to Vouchers Panel
        if (!auth()->user()->can('access_vouchers_panel')) {
            abort(403);
        }

        $voucher->load(['user', 'category', 'approvals.user']);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.voucher', ['voucher' => $voucher])->stream($voucher->voucher_number . '.pdf');
    })->name('voucher.pdf');

});

