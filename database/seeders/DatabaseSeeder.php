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
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \App\Models\Voucher::truncate();
        \App\Models\VoucherApproval::truncate();
        \App\Models\ApprovalWorkflow::truncate();
        \Illuminate\Support\Facades\DB::table('model_has_roles')->truncate();
        \Illuminate\Support\Facades\DB::table('model_has_permissions')->truncate();
        \Illuminate\Support\Facades\DB::table('role_has_permissions')->truncate();
        \Illuminate\Support\Facades\DB::table('permissions')->truncate();
        \Illuminate\Support\Facades\DB::table('roles')->truncate();
        User::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $this->call(RolesAndPermissionsSeeder::class);

        // 1. Create 5 Required Users
        
        // 1.1 Super Admin (Full Access)
        $admin = User::firstOrCreate(
            ['email' => 'admin@pettycash.com'],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('password'),
            ]
        );
        $admin->assignRole('Admin');

        // 1.2 General Manager (Approver)
        $gm = User::firstOrCreate(
            ['email' => 'gm@pettycash.com'],
            [
                'name'      => 'General Manager',
                'password'  => Hash::make('password'),
            ]
        );
        $gm->assignRole('Approver');

        // 1.3 Accountant (Checker)
        $accountant = User::firstOrCreate(
            ['email' => 'accountant@pettycash.com'],
            [
                'name'      => 'Finance Accountant',
                'password'  => Hash::make('password'),
            ]
        );
        $accountant->assignRole('Accountant');

        // 1.4 & 1.5 Two Requesters
        $requester1 = User::firstOrCreate(
            ['email' => 'requester1@pettycash.com'],
            [
                'name'      => 'Staff Requester One',
                'password'  => Hash::make('password'),
            ]
        );
        $requester1->assignRole('Requester');

        $requester2 = User::firstOrCreate(
            ['email' => 'requester2@pettycash.com'],
            [
                'name'      => 'Staff Requester Two',
                'password'  => Hash::make('password'),
            ]
        );
        $requester2->assignRole('Requester');


        // 2. Configure the Custom Approval Workflow Chain
        // Step 1: Accountant Verifies
        \App\Models\ApprovalWorkflow::create([
            'step_order' => 1,
            'user_id'    => $accountant->id,
            'label'      => 'Accountant Audit',
        ]);
        // Step 2: GM Approves
        \App\Models\ApprovalWorkflow::create([
            'step_order' => 2,
            'user_id'    => $gm->id,
            'label'      => 'GM Approval',
        ]);


        // 3. Setup Basic Categories
        $categories = ['Office Supplies', 'Transportation', 'Hardware', 'Software Licenses', 'Client Entertainment'];
        $catIds = [];
        foreach ($categories as $cat) {
            $catIds[] = Category::firstOrCreate(['name' => $cat, 'type' => 'petty_cash'])->id;
        }
        // 3.5. Setup Ledger Branches
        $ledgerBranches = ['ETC','TG','SB','ICOOK','NICE DESIGN','SB MAIN','SB BR1 (AQ)','SB BR2 (AN2)','SB BR3 (MM)','SB BR4 (AN3)','SB BR5 (KF)','SB BR6 (JAMAL)','SB BR7 (TAAWUN)','SB BR8 (ABU SHAGARA)','SB BR9 (TAAWUN-2)','SB AJMAN','SB AL KHAN (JAMAL BASEET MAIN)','SB AN4 (JAMAL BASEET BR1)','SB DUBAI (SIMPLY GORGEOUS MAIN)','SB AJMAN-2 (JURF) (AL JAMAL ALBASEET LADIES BEAUTY CENTER)','SB DUBAI-BR.1 (SOBHA)','SB DUBAI-BR.2 (NAAD AL HAMAR)','SB DUBAI-BR.3 (WASL VILLAGE)','SB DUBAI-BR.4 (API BLDG)','SB DUBAI-BR.5 (MIDTOWN)','SB DUBAI-BR.6 (CVR)-ALJAMAL ALBURTEQALI LADIES SALON L.L.C','SB DUBAI-BR.7 (CREST)-ALJAMAL ALBURTEQALI LADIES SALON L.L.C','TG MAIN (ABU SHAGARA)','TG BR1 (AN1)','TG BR2 (AQ)','TG BR3 (AN2)','TG BR4 (KF)','TG BR5 (MM)','TG BR6 (AN3)','TG BR7 (JAMAL)','TG BR8 (TAAWUN)','TG BR9 (QASIMIA)','ICUT (AL QASA JAMEELA)','TG AJMAN','TG Tashteeb Main (TG AL TAAWUN-2)','TG AL KHAN (TASHTHEEB BR.)','TG AN4 (TASHTHEEB BR.2)','TG DUBAI MAIN (ELITE TRIMMERS MAIN)','TG DUBAI BR1 (ELITE TRIMMERS BR1)','TG JURF-AJMAN','TG DUBAI BR. 2 (API BLDG) (ELITE TRIMMERS BR2)'];
        foreach ($ledgerBranches as $branchName) {
            \App\Models\LedgerBranch::firstOrCreate(['name' => $branchName]);
        }

        // 4. Generate Exactly 10 Vouchers
        $requesters = [$requester1, $requester2];
        $totalVouchersCreated = 0;

        for ($i = 1; $i <= 10; $i++) {
            $date = Carbon::now()->subDays(rand(1, 30));
            $user = $requesters[array_rand($requesters)];
            
            // Randomly pick a status state
            $statusOptions = ['draft', 'pending_checker', 'pending_approver', 'approved', 'paid', 'rejected'];
            $status = $statusOptions[array_rand($statusOptions)];

            $amount = rand(100, 2000);
            $voucher = \App\Models\Voucher::create([
                'user_id' => $user->id,
                'category_id' => $catIds[array_rand($catIds)],
                'type' => 'petty_cash',
                'status' => $status,
                'amount' => $amount,
                'payee' => 'Vendor ' . rand(1, 20),
                'description' => 'Dummy voucher seed item ' . $i,
                'created_at' => $date,
                'updated_at' => $date,
                // Assign workflow step based on status
                'current_approval_step' => match($status) {
                    'pending_checker' => 1,
                    'pending_approver' => 2,
                    default => null
                }
            ]);

            \Illuminate\Support\Facades\Log::info("Created Voucher {$voucher->id}: status={$voucher->status}, step={$voucher->current_approval_step}");

            // Create Approval History
            if (in_array($status, ['pending_approver', 'approved', 'paid'])) {
                $voucher->approvals()->create([
                    'user_id' => $accountant->id,
                    'action' => 'checked',
                    'created_at' => $date->copy()->addHours(1),
                ]);
            }

            \Illuminate\Support\Facades\Log::info("After Approvals Voucher {$voucher->id}: step=" . $voucher->fresh()->current_approval_step);
            if (in_array($status, ['approved', 'paid'])) {
                $voucher->approvals()->create([
                    'user_id' => $gm->id,
                    'action' => 'approved',
                    'comments' => 'Approved as GM',
                    'created_at' => $date->copy()->addHours(3),
                ]);
            }
            if ($status === 'paid') {
                $voucher->approvals()->create([
                    'user_id' => $admin->id,
                    'action' => 'paid',
                    'created_at' => $date->copy()->addHours(10),
                ]);
            }
            if ($status === 'rejected') {
                $actor = (rand(0,1) === 0) ? $accountant : $gm;
                $voucher->approvals()->create([
                    'user_id' => $actor->id,
                    'action' => 'rejected',
                    'comments' => 'Missing receipt.',
                    'created_at' => $date->copy()->addHours(2),
                ]);
            }
        }
    }
}
