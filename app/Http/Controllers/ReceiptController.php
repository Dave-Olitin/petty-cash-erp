<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves private receipt files for transactions.
 *
 * Extracted from the inline route closure in web.php to make the
 * logic testable, reusable, and easier to maintain.
 */
class ReceiptController extends Controller
{
    public function show(Request $request, Transaction $transaction): Response|\Illuminate\Http\RedirectResponse
    {
        // Authorization: Branch users can only view receipts for their own branch.
        if ($request->user()->branch_id && $request->user()->branch_id !== $transaction->branch_id) {
            abort(403, 'You do not have permission to view this receipt.');
        }

        $path = $transaction->receipt_path;

        if (!$path) {
            abort(404, 'No receipt is attached to this transaction.');
        }

        // Security: reject any path that doesn't start with 'receipts/'
        // to prevent path traversal attacks on storage files.
        if (!str_starts_with($path, 'receipts/')) {
            abort(403, 'Invalid receipt path.');
        }

        return $this->serveFile($path);
    }

    /**
     * Try the local disk first (new secure uploads), fall back to public disk (legacy).
     */
    private function serveFile(string $path): Response
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                $fullPath = Storage::disk($disk)->path($path);
                $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

                // Only serve images and PDFs — never executable types.
                if (!str_starts_with($mimeType, 'image/') && $mimeType !== 'application/pdf') {
                    abort(403, 'File type not allowed.');
                }

                return response()->file($fullPath, ['Content-Type' => $mimeType]);
            }
        }

        abort(404, 'Receipt file not found.');
    }
}
