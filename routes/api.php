<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Secure API routes — requires Sanctum token
Route::middleware('auth:sanctum')->group(function () {

    // 1. Get authenticated user
    Route::get('/user', function (Request $request) {
        return $request->user()->load('branch');
    });

    // 2. Get live float balance
    Route::get('/branches/{id}/balance', function (Request $request, $id) {
        $user = $request->user();
        
        // Security: Branch users can only query their own branch
        if ($user->branch_id && $user->branch_id != $id) {
            abort(403, 'Unauthorized access to branch balance.');
        }

        $branch = \App\Models\Branch::findOrFail($id);

        return response()->json([
            'branch_id' => $branch->id,
            'name' => $branch->name,
            'current_balance' => $branch->current_balance,
            'transaction_limit' => $branch->transaction_limit,
            'max_limit' => $branch->max_limit,
            'is_active' => $branch->is_active,
        ]);
    });

    // 3. Get transactions (paginated, filtered by context)
    Route::get('/transactions', function (Request $request) {
        $user = $request->user();

        $query = \App\Models\Transaction::with(['items.category', 'user:id,name', 'branch:id,name'])
            ->orderBy('created_at', 'desc');

        // Security: Branch users can only see their branch's transactions
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        } else {
            // HQ can optionally filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->paginate(20));
    });
});
