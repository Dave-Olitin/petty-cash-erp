<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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

    Route::get('/admin/transactions/{transaction}/receipt', function (\App\Models\Transaction $transaction) {
        // Branch users can only view receipts for their own branch
        if (auth()->user()->branch_id && auth()->user()->branch_id !== $transaction->branch_id) {
            abort(403);
        }

        $path = $transaction->receipt_path;

        if (!$path) {
            abort(404);
        }

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($path));
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::disk('public')->path($path));
        }

        abort(404);
    })->name('transaction.receipt');

});

