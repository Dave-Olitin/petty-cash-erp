<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use Illuminate\Database\Eloquent\Model;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Model::preventLazyLoading(false);

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define and insert PRO permissions with descriptions
        $proPermissions = [
            'pro_record.view' => 'View PRO records, employee tracking, and pipeline',
            'pro_record.create' => 'Create new employee PRO records (New Joiner / Renewal)',
            'pro_record.edit' => 'Edit PRO records, advance stages, update fees and fines',
            'pro_record.delete' => 'Delete PRO employee tracking records',
            'pro_record.export' => 'Export PRO tracking records to Excel',
            'pro_category.view' => 'View PRO categories and default government fees',
            'pro_category.manage' => 'Manage PRO categories and default fee structures',
        ];

        foreach ($proPermissions as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        // Also ensure descriptions on common existing permissions if blank
        $otherDescriptions = [
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
            'manage_settings' => 'Manage system settings, roles, and configuration',
            'liquidation.edit_settled' => 'Override settled liquidations',
            'report.view' => 'View financial and audit reports',
            'voucher.edit_own_undisbursed' => 'Edit own undisbursed vouchers',
            'gl_report.view' => 'View General Ledger reports and Trial Balance',
        ];

        foreach ($otherDescriptions as $name => $desc) {
            $perm = Permission::where('name', $name)->first();
            if ($perm && empty($perm->description)) {
                $perm->update(['description' => $desc]);
            }
        }

        // 2. Assign to Admin & Super Admin
        $adminRoles = Role::whereIn('name', ['Admin', 'Super Admin'])->get();
        foreach ($adminRoles as $role) {
            foreach (array_keys($proPermissions) as $permName) {
                $role->givePermissionTo($permName);
            }
        }

        // 3. Assign view & export to Accountant roles
        $accountantRoles = Role::whereIn('name', ['Accountant', 'Accountant 1'])->get();
        foreach ($accountantRoles as $role) {
            $role->givePermissionTo(['pro_record.view', 'pro_record.export', 'pro_category.view']);
        }

        // 4. Create PRO Officer role
        $proOfficerRole = Role::firstOrCreate(
            ['name' => 'PRO Officer', 'guard_name' => 'web'],
            ['description' => 'PRO Officer handling visa applications, labor quotas, Emirates IDs & renewals']
        );

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

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $perms = [
            'pro_record.view',
            'pro_record.create',
            'pro_record.edit',
            'pro_record.delete',
            'pro_record.export',
            'pro_category.view',
            'pro_category.manage',
        ];

        Permission::whereIn('name', $perms)->delete();
        Role::where('name', 'PRO Officer')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
