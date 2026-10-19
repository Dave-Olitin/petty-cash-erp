<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── 1. Define All Permissions ────────────────────────────────────────
        $permissions = [
            // Panel access
            'access_vouchers_panel',
            'access_petty_cash_panel',

            // Voucher CRUD
            'voucher.view',       // Can see the vouchers list and view a voucher
            'voucher.create',     // Can create a new voucher
            'voucher.edit',       // Can edit a draft voucher
            'voucher.delete',     // Can delete/void a voucher

            // Voucher Workflow actions
            'voucher.submit',     // Can submit a draft voucher for checking
            'voucher.check',      // Can verify & forward to approver (Accountant)
            'voucher.approve',    // Can give final approval (Approver/CEO)
            'voucher.reject',     // Can reject/return a voucher
            'voucher.pay',        // Can pay a voucher (any payable status)
            'voucher.void_only',  // Can completely void a paid voucher without cloning
            'voucher.void_and_reissue', // Can void and clone a paid voucher
            'voucher.manage_attachments', // Can manage attachments on anyone's paid voucher

            // Float management
            'voucher.manage_float', // Can view/add head office float replenishments

            // Accounting
            'journal_entry.view', // Can view journal entries
            'journal_entry.create',
            'journal_entry.edit',
            'journal_entry.delete',
            
            'purchase_entry.view', // Can view purchase entries
            'purchase_entry.create',
            'purchase_entry.edit',
            'purchase_entry.delete',

            // PRO & Visa Tracking Module
            'pro_record.view',       // Can view PRO records and pipeline status
            'pro_record.create',     // Can create new PRO employee tracking records
            'pro_record.edit',       // Can edit PRO records and advance pipeline stages
            'pro_record.delete',     // Can delete PRO employee tracking records
            'pro_record.export',     // Can export PRO records and reports to Excel
            'pro_category.view',     // Can view PRO categories and fee matrices
            'pro_category.manage',   // Can create/edit/delete PRO categories and default fees

            // Settings
            'manage_settings', // Can view Roles, Permissions, Categories, etc.

            // Liquidations
            'liquidation.edit_settled', // Can override settled liquidations

            // Reports & Advanced
            'report.view',
            'voucher.edit_own_undisbursed',

            // General Ledger Reports
            'gl_report.view', // Can view Trial Balance, Account Ledger drill-down
        ];

        $descriptions = [
            'access_vouchers_panel' => 'Access to the Accounting & Vouchers panel',
            'access_petty_cash_panel' => 'Access to Petty Cash ERP branch panel',
            'voucher.view' => 'View vouchers list and details',
            'voucher.create' => 'Create new vouchers',
            'voucher.edit' => 'Edit draft or returned vouchers',
            'voucher.delete' => 'Delete or void vouchers',
            'voucher.submit' => 'Submit voucher for verification',
            'voucher.check' => 'Verify and check vouchers (Accountant)',
            'voucher.approve' => 'Approve vouchers (Approver / CEO)',
            'voucher.reject' => 'Reject or return vouchers',
            'voucher.pay' => 'Disburse and mark vouchers as paid',
            'voucher.void_only' => 'Void paid vouchers without cloning',
            'voucher.void_and_reissue' => 'Void and re-issue cloned voucher',
            'voucher.manage_attachments' => 'Manage attachments on paid vouchers',
            'voucher.manage_float' => 'Manage head office branch floats',
            'journal_entry.view' => 'View journal entries',
            'journal_entry.create' => 'Create new journal entries',
            'journal_entry.edit' => 'Edit journal entries',
            'journal_entry.delete' => 'Delete journal entries',
            'purchase_entry.view' => 'View purchase entries',
            'purchase_entry.create' => 'Create purchase entries',
            'purchase_entry.edit' => 'Edit purchase entries',
            'purchase_entry.delete' => 'Delete purchase entries',
            'pro_record.view' => 'View PRO records, employee tracking, and pipeline',
            'pro_record.create' => 'Create new employee PRO records (New Joiner / Renewal)',
            'pro_record.edit' => 'Edit PRO records, advance stages, update fees and fines',
            'pro_record.delete' => 'Delete PRO employee tracking records',
            'pro_record.export' => 'Export PRO tracking records to Excel',
            'pro_category.view' => 'View PRO categories and default government fees',
            'pro_category.manage' => 'Manage PRO categories and default fee structures',
            'manage_settings' => 'Manage system settings, roles, and configuration',
            'liquidation.edit_settled' => 'Override settled liquidations',
            'report.view' => 'View financial and audit reports',
            'voucher.edit_own_undisbursed' => 'Edit own undisbursed vouchers',
            'gl_report.view' => 'View General Ledger reports and Trial Balance',
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm, 'guard_name' => 'web'],
                ['description' => $descriptions[$perm] ?? null]
            );
        }

        // ─── 2. Create Roles & Assign Permissions ─────────────────────────────

        // REQUESTER — can create/view/submit their own vouchers
        $requesterRole = Role::firstOrCreate(['name' => 'Requester', 'guard_name' => 'web']);
        $requesterRole->syncPermissions([
            'access_vouchers_panel',
            'voucher.view',
            'voucher.create',
            'voucher.edit',
            'voucher.submit',
        ]);

        // ACCOUNTANT — can view all vouchers, check them, mark as paid, manage float, view PRO records
        $accountantRole = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountantRole->syncPermissions([
            'access_vouchers_panel',
            'voucher.view',
            'voucher.create',
            'voucher.edit',
            'voucher.submit',
            'voucher.check',
            'voucher.reject',
            'voucher.pay',
            'voucher.void_only',
            'voucher.void_and_reissue',
            'voucher.manage_float',
            'journal_entry.view',
            'journal_entry.create',
            'journal_entry.edit',
            'journal_entry.delete',
            'purchase_entry.view',
            'purchase_entry.create',
            'purchase_entry.edit',
            'purchase_entry.delete',
            'liquidation.edit_settled',
            'voucher.manage_attachments',
            'gl_report.view',
            'pro_record.view',
            'pro_record.export',
            'pro_category.view',
        ]);

        // PRO OFFICER — can manage employee visas, entry permits, emirates IDs, and documents
        $proOfficerRole = Role::firstOrCreate(['name' => 'PRO Officer', 'guard_name' => 'web'], [
            'description' => 'PRO Officer handling visa processing, labor contracts, Emirates IDs & renewals',
        ]);
        $proOfficerRole->syncPermissions([
            'access_vouchers_panel',
            'pro_record.view',
            'pro_record.create',
            'pro_record.edit',
            'pro_record.delete',
            'pro_record.export',
            'pro_category.view',
            'pro_category.manage',
        ]);

        // APPROVER — can view and give final approval/rejection
        $approverRole = Role::firstOrCreate(['name' => 'Approver', 'guard_name' => 'web']);
        $approverRole->syncPermissions([
            'access_vouchers_panel',
            'voucher.view',
            'voucher.approve',
            'voucher.reject',
        ]);

        // ADMIN (Head Office) — full access to everything
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        // ─── 3. Create / Update Test Users ───────────────────────────────────

        $testAccountant = User::withTrashed()->updateOrCreate(
            ['email' => 'accountant@pettycash.com'],
            ['name' => 'Test Accountant', 'password' => Hash::make('password'), 'deleted_at' => null]
        );
        $testAccountant->syncRoles($accountantRole);

        $testApprover = User::withTrashed()->updateOrCreate(
            ['email' => 'approver@pettycash.com'],
            ['name' => 'Test Approver', 'password' => Hash::make('password'), 'deleted_at' => null]
        );
        $testApprover->syncRoles($approverRole);

        $testRequester = User::withTrashed()->updateOrCreate(
            ['email' => 'requester@pettycash.com'],
            ['name' => 'Test Requester', 'password' => Hash::make('password'), 'deleted_at' => null]
        );
        $testRequester->syncRoles($requesterRole);
    }
}
