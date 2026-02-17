<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Cleanup
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Transaction::truncate();
        TransactionItem::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Create Head Office Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@pettycash.com'],
            [
                'name' => 'Head Office Admin',
                'password' => Hash::make('password'), // Change in production
                'branch_id' => null,
            ]
        );

        // 2. Create Branches
        $branches = [
            ['name' => 'Dubai Branch', 'current_balance' => 0, 'limit' => 5000],
            ['name' => 'Abu Dhabi Branch', 'current_balance' => 0, 'limit' => 5000],
            ['name' => 'Sharjah Branch', 'current_balance' => 0, 'limit' => 3000],
        ];

        $branchModels = [];
        foreach ($branches as $b) {
            $branch = Branch::firstOrCreate(
                ['name' => $b['name']],
                [
                    'current_balance' => $b['current_balance'], 
                    'transaction_limit' => $b['limit']
                ]
            );
            // Reset balance since we truncated transactions
            $branch->update(['current_balance' => 0]);
            
            $branchModels[] = $branch;

            // Create Branch User
            User::firstOrCreate(
                ['email' => strtolower(str_replace(' ', '.', $b['name'])) . '@pettycash.com'],
                [
                    'name' => $b['name'] . ' Manager',
                    'password' => Hash::make('password'),
                    'branch_id' => $branch->id,
                ]
            );
        }

        // 3. Create Categories
        $expenseCategories = [
            'Office Supplies', 'Transportation', 'Utilities', 'Entertainment', 'Maintenance', 'Pantry'
        ];
        $replenishmentCategories = [
            'Bank Withdrawal', 'Petty Cash Top-up'
        ];

        $catIds = [];
        foreach ($expenseCategories as $cat) {
            $c = Category::firstOrCreate(
                ['name' => $cat],
                ['type' => 'expense', 'is_active' => true]
            );
            $catIds['expense'][] = $c->id;
        }
        $catIds['replenishment'] = [];
        foreach ($replenishmentCategories as $cat) {
            $c = Category::firstOrCreate(
                ['name' => $cat],
                ['type' => 'replenishment', 'is_active' => true]
            );
            $catIds['replenishment'][] = $c->id;
        }

        // 4. Generate Transactions (History)
        $startDate = Carbon::now()->subMonths(6);
        
        foreach ($branchModels as $branch) {
            // Initial Replenishment
            Transaction::create([
                'user_id' => $admin->id,
                'branch_id' => $branch->id,
                'category_id' => $catIds['replenishment'][0],
                'type' => 'REPLENISHMENT',
                'amount' => 15000,
                'payee' => 'Main Bank',
                'supplier' => 'Head Office Bank',
                'description' => 'Initial Float',
                'reference_number' => 'INIT-001',
                'status' => 'approved',
                'receipt_path' => 'receipts/sample_receipt.jpg',
                'created_at' => $startDate->copy()->addHour(),
                'updated_at' => $startDate->copy()->addHour(),
            ]);

            // Random Transactions
            for ($i = 0; $i < 50; $i++) {
                $date = $startDate->copy()->addDays(rand(1, 180))->addHours(rand(8, 18));
                
                // 1 in 10 chance of Replenishment
                if (rand(1, 10) === 1) {
                    Transaction::create([
                        'user_id' => $admin->id,
                        'branch_id' => $branch->id,
                        'category_id' => $catIds['replenishment'][array_rand($catIds['replenishment'])],
                        'type' => 'REPLENISHMENT',
                        'amount' => rand(1000, 5000),
                        'payee' => 'Bank Transfer',
                        'supplier' => 'Bank',
                        'reference_number' => 'TRF-' . rand(10000, 99999),
                        'description' => 'Top up',
                        'status' => 'approved',
                        'receipt_path' => 'receipts/sample_receipt.jpg',
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);
                } else {
                    $amount = rand(50, 500);
                    // Ensure balance
                    if ($branch->fresh()->current_balance < $amount) continue;

                    $txn = Transaction::create([
                        'user_id' => User::where('branch_id', $branch->id)->first()->id,
                        'branch_id' => $branch->id,
                        'category_id' => $catIds['expense'][array_rand($catIds['expense'])],
                        'type' => 'EXPENSE',
                        'amount' => $amount,
                        'payee' => 'Retailer ' . rand(1, 20),
                        'supplier' => 'Supplier ' . rand(1, 20),
                        'trn' => '100' . rand(1000000000, 9999999999),
                        'reference_number' => 'INV-' . rand(10000, 99999),
                        'description' => 'Misc expenses',
                        'status' => 'approved',
                        'receipt_path' => 'receipts/sample_receipt.jpg',
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    // Add Items
                    TransactionItem::create([
                        'transaction_id' => $txn->id,
                        'name' => 'Item 1',
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total_price' => $amount,
                    ]);
                }
            }
        }
    }
}
