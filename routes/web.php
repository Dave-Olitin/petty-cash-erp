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

});

