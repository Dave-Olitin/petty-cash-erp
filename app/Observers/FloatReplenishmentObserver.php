<?php

namespace App\Observers;

use App\Models\FloatReplenishment;

class FloatReplenishmentObserver
{
    /**
     * When a Float Replenishment is created, automatically create a
     * corresponding Denomination record so it appears in the
     * "Recent Cash Breakdown Logs" widget.
     */
    public function created(FloatReplenishment $replenishment): void
    {
        // Only create if no denomination already exists (avoids double-entry)
        if (! $replenishment->denominations) {
            $replenishment->denominations()->create([
                'total_amount'       => $replenishment->amount,
                'change_given'       => 0,
                'is_change_received' => true,
                'remarks'            => $replenishment->remarks ?? 'Float Replenishment',
                'bill_1000' => 0, 'bill_500' => 0, 'bill_200' => 0, 'bill_100' => 0,
                'bill_50'   => 0, 'bill_20'  => 0, 'bill_10'  => 0, 'bill_5'   => 0,
                'coin_1'    => 0, 'coin_0_50' => 0, 'coin_0_25' => 0,
            ]);
        }
    }

    /**
     * Sync attachments to documents table on saved.
     */
    public function saved(FloatReplenishment $replenishment): void
    {
        $paths = $replenishment->attachment_paths ?? [];
        if (!is_array($paths)) {
            $paths = [];
        }

        // Get existing documents in DB for this float replenishment
        $existingDocs = \App\Models\Document::where('float_replenishment_id', $replenishment->id)->get();
        $existingPaths = $existingDocs->pluck('file_path')->toArray();

        // Find paths to delete (present in DB, but not in current attachment_paths)
        $pathsToDelete = array_diff($existingPaths, $paths);
        if (!empty($pathsToDelete)) {
            \App\Models\Document::where('float_replenishment_id', $replenishment->id)
                ->whereIn('file_path', $pathsToDelete)
                ->delete();
        }

        // Find paths to add (present in current attachment_paths, but not in DB)
        $pathsToAdd = array_diff($paths, $existingPaths);
        foreach ($pathsToAdd as $path) {
            if (empty($path)) continue;

            $fileName = basename($path);
            $fileType = pathinfo($path, PATHINFO_EXTENSION);

            \App\Models\Document::create([
                'float_replenishment_id' => $replenishment->id,
                'file_path' => $path,
                'file_name' => $fileName,
                'file_type' => $fileType,
                'uploaded_by' => auth()->id() ?? $replenishment->created_by,
            ]);
        }
    }
}
