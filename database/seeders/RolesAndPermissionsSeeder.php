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

            // Float management
            'voucher.manage_float', // Can view/add head office float replenishments

            // Settings
            'manage_settings', // Can view Roles, Permissions, Categories, etc.
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ─── 2. Create Roles & Assign Permissions ─────────────────────────────

        // REQUESTER — can create/view/submit their own vouchers
        $requesterRole = Role::firstOrCreate(['name' => 'Requester']);
        $requesterRole->syncPermissions([
            'access_vouchers_panel',
            'voucher.view',
            'voucher.create',
            'voucher.edit',
            'voucher.submit',
        ]);

        // ACCOUNTANT — can view all vouchers, check them, mark as paid, manage float
        $accountantRole = Role::firstOrCreate(['name' => 'Accountant']);
        $accountantRole->syncPermissions([
            'access_vouchers_panel',
            'voucher.view',
            'voucher.create',
            'voucher.edit',
            'voucher.submit',
            'voucher.check',
            'voucher.reject',
            'voucher.pay',
            'voucher.manage_float',
        ]);

        // APPROVER — can view and give final approval/rejection
        $approverRole = Role::firstOrCreate(['name' => 'Approver']);
        $approverRole->syncPermissions([
            'access_vouchers_panel',
            'voucher.view',
            'voucher.approve',
            'voucher.reject',
        ]);

        // ADMIN (Head Office) — full access to everything
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->syncPermissions(Permission::all());

        // ─── 3. Create / Update Test Users ───────────────────────────────────

        $testAccountant = User::firstOrCreate(
            ['email' => 'accountant@pettycash.com'],
            ['name' => 'Test Accountant', 'password' => Hash::make('password')]
        );
        $testAccountant->syncRoles($accountantRole);

        $testApprover = User::firstOrCreate(
            ['email' => 'approver@pettycash.com'],
            ['name' => 'Test Approver', 'password' => Hash::make('password')]
        );
        $testApprover->syncRoles($approverRole);

        $testRequester = User::firstOrCreate(
            ['email' => 'requester@pettycash.com'],
            ['name' => 'Test Requester', 'password' => Hash::make('password')]
        );
        $testRequester->syncRoles($requesterRole);
    }
}
