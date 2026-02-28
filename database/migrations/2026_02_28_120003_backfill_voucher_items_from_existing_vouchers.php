<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For each existing voucher that has no items yet, create two ledger rows:
        // 1. A debit entry matching the voucher's amount and category
        // 2. A credit "Cash In Hand / Bank" entry with the same amount
        $vouchers = DB::table('vouchers')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('voucher_items')
                      ->whereColumn('voucher_items.voucher_id', 'vouchers.id');
            })
            ->get();

        $now = now();

        foreach ($vouchers as $voucher) {
            DB::table('voucher_items')->insert([
                [
                    'voucher_id'  => $voucher->id,
                    'entry_type'  => 'debit',
                    'account_code' => null,
                    'description' => $voucher->description ?? 'Expense',
                    'category_id' => $voucher->category_id,
                    'branch_code' => null,
                    'amount'      => $voucher->amount,
                    'sort_order'  => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'voucher_id'  => $voucher->id,
                    'entry_type'  => 'credit',
                    'account_code' => '1010-02',
                    'description' => 'Cash In Hand / Bank',
                    'category_id' => null,
                    'branch_code' => null,
                    'amount'      => $voucher->amount,
                    'sort_order'  => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // No rollback — items are the canonical data source going forward
    }
};
