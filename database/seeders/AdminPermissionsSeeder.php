<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Admin-centric Panel permissions
        Permission::firstOrCreate(['name' => 'access_petty_cash_panel'], ['description' => 'Grants access to the main Petty Cash ERP interface']);
        Permission::firstOrCreate(['name' => 'access_vouchers_panel'], ['description' => 'Grants access to the Payment Voucher system interface']);
        Permission::firstOrCreate(['name' => 'manage_settings'], ['description' => 'Ability to view and edit system settings like Categories, Branches, Roles, and Users']);
        
        $super = Role::firstOrCreate(['name' => 'Super Admin'], ['description' => 'Full unhindered access to the entire application']);

        // Assign 'Super Admin' to the first user
        User::with(['roles', 'permissions'])->first()?->assignRole('Super Admin');

        // Migrate existing users to the new permission architecture
        foreach (User::with(['roles', 'permissions'])->get() as $user) {
            if ($user->isHeadOffice() || $user->hasAnyRole(['Accountant', 'Approver', 'Requester'])) {
                $user->givePermissionTo('access_vouchers_panel');
            }
            // By default, everyone had access to the petty cash ERP.
            $user->givePermissionTo('access_petty_cash_panel');
        }
    }
}
