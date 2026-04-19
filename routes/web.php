<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/vouchers/login');
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

        // Security: reject any path that doesn't start with 'receipts/'
        // to prevent path traversal attacks on storage files.
        if (!str_starts_with($path, 'receipts/')) {
            abort(403, 'Invalid receipt path.');
        }

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
            $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

            // Only serve images and PDFs — never executable types
            if (!str_starts_with($mimeType, 'image/') && $mimeType !== 'application/pdf') {
                abort(403, 'File type not allowed.');
            }

            return response()->file($fullPath, ['Content-Type' => $mimeType]);
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
            $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

            if (!str_starts_with($mimeType, 'image/') && $mimeType !== 'application/pdf') {
                abort(403, 'File type not allowed.');
            }

            return response()->file($fullPath, ['Content-Type' => $mimeType]);
        }

        abort(404);
    })->name('transaction.receipt');

    Route::get('/admin/vouchers/{voucher}/pdf', function (\App\Models\Voucher $voucher) {
        // Must have access to Vouchers Panel and permission to view this exact voucher
        if (!auth()->user()->can('access_vouchers_panel') || !auth()->user()->can('view', $voucher)) {
            abort(403);
        }

        $voucher->load(['user', 'category', 'approvals.user', 'template', 'items.category', 'purchaseEntries.taxRegistration']);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.voucher', ['voucher' => $voucher])->stream($voucher->voucher_number . '.pdf');
    })->name('voucher.pdf');

    Route::get('/admin/float-replenishments/{replenishment}/pdf', function (\App\Models\FloatReplenishment $replenishment) {
        // Head Office or Accountant check
        if (!auth()->user()->isHeadOffice() && !auth()->user()->hasRole('Accountant')) {
            abort(403);
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.replenishment', ['replenishment' => $replenishment])->stream($replenishment->reference . '.pdf');
    })->name('replenishment.pdf');

});

// Push Subscription Routes — accessible from any panel (admin or vouchers).
// Uses auth:web guard explicitly since both panels share the same User model on the web guard.
Route::middleware(['auth:web'])->group(function () {
    Route::post('/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'unsubscribe']);
});



