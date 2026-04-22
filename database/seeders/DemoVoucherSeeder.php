<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Category;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class DemoVoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure Roles exist
        $accountantRole = Role::firstOrCreate(['name' => 'Accountant']);
        $approverRole = Role::firstOrCreate(['name' => 'Approver']);
        $requesterRole = Role::firstOrCreate(['name' => 'Requester']);

        // 2. Create Dummy Users
        $accountant = User::firstOrCreate(['email' => 'accountant@ericktrading.demo'], [
            'name' => 'Alice Accountant',
            'password' => bcrypt('password'),
        ]);
        $accountant->assignRole($accountantRole);
        $accountant->givePermissionTo('access_vouchers_panel');

        $approver = User::firstOrCreate(['email' => 'approver@ericktrading.demo'], [
            'name' => 'Bob Approver',
            'password' => bcrypt('password'),
        ]);
        $approver->assignRole($approverRole);
        $approver->givePermissionTo('access_vouchers_panel');

        $requesters = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = User::firstOrCreate(['email' => "requester{$i}@ericktrading.demo"], [
                'name' => "Staff {$i} Requester",
                'password' => bcrypt('password'),
            ]);
            $user->assignRole($requesterRole);
            $user->givePermissionTo('access_vouchers_panel');
            $requesters[] = $user;
        }

        // 3. Ensure some Categories exist
        $categories = Category::limit(5)->get();
        if ($categories->isEmpty()) {
            Category::create(['name' => 'Office Supplies', 'type' => 'expense']);
            Category::create(['name' => 'Travel & Transport', 'type' => 'expense']);
            Category::create(['name' => 'Meals & Entertainment', 'type' => 'expense']);
            Category::create(['name' => 'Software Licenses', 'type' => 'expense']);
            Category::create(['name' => 'Miscellaneous', 'type' => 'expense']);
            $categories = Category::all();
        }

        // 4. Generate Dummy Vouchers for Dashboard Metrics
        $statuses = ['draft', 'pending_checker', 'pending_approver', 'approved', 'rejected', 'paid'];
        $vendors = ['Amazon', 'Uber', 'Local Restaurant', 'Staples', 'Microsoft', 'Noon', 'Etisalat'];

        foreach (range(1, 50) as $index) {
            $status = $statuses[array_rand($statuses)];
            // Skew towards paid/approved for better charts, or pending for active lists
            if ($index > 35) $status = 'paid';
            if ($index > 25 && $index <= 35) $status = 'pending_checker';

            $requester = $requesters[array_rand($requesters)];
            
            $voucher = Voucher::create([
                'user_id' => $requester->id,
                'category_id' => $categories->random()->id,
                'type' => rand(1, 10) > 7 ? 'petty_cash' : 'payment',
                'voucher_number' => 'VCH-' . strtoupper(Str::random(6)),
                'payee' => $vendors[array_rand($vendors)],
                'amount' => rand(50, 5000),
                'description' => 'Demo transaction created via seeder for ' . $status,
                'status' => $status,
                'created_at' => now()->subDays(rand(0, 30)), // Spread over last month for charts
                'updated_at' => now()->subDays(rand(0, 5)),
            ]);

            // Add fake approvals if it advanced past draft
            if (in_array($status, ['pending_approver', 'approved', 'paid', 'rejected'])) {
                $voucher->approvals()->create([
                    'user_id' => $accountant->id,
                    'action' => 'checked',
                    'created_at' => $voucher->created_at->addHours(rand(1, 5)),
                ]);
            }

            if (in_array($status, ['approved', 'paid'])) {
                $voucher->approvals()->create([
                    'user_id' => $approver->id,
                    'action' => 'approved',
                    'created_at' => $voucher->created_at->addHours(rand(6, 24)),
                ]);
            }

            if ($status === 'rejected') {
                $voucher->approvals()->create([
                    'user_id' => $approver->id,
                    'action' => 'rejected',
                    'comments' => 'Not aligned with company policy. Missing receipts.',
                    'created_at' => $voucher->created_at->addHours(rand(6, 24)),
                ]);
            }
        }
    }
}
