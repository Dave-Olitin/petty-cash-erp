<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'ledger_branch.view'   => 'View company branches, trade licenses, and establishment cards',
            'ledger_branch.manage' => 'Manage company branches, trade licenses, and establishment cards',
        ];

        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        // Grant to Admin & Super Admin
        $adminRoles = Role::whereIn('name', ['Admin', 'Super Admin'])->get();
        foreach ($adminRoles as $role) {
            $role->givePermissionTo(array_keys($permissions));
        }

        // Grant to PRO Officer
        $proOfficer = Role::where('name', 'PRO Officer')->first();
        if ($proOfficer) {
            $proOfficer->givePermissionTo(array_keys($permissions));
        }

        // Grant view-only to Accountants
        $accountantRoles = Role::whereIn('name', ['Accountant', 'Accountant 1'])->get();
        foreach ($accountantRoles as $role) {
            $role->givePermissionTo(['ledger_branch.view']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', ['ledger_branch.view', 'ledger_branch.manage'])->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
