<?php

namespace App\Policies;

use App\Models\ItemMaster;
use App\Models\User;

class ItemMasterPolicy
{
    public function viewAny(User $user): bool
    {
        // Allow all users to view item masters (needed for selection in transactions), but restrict modification to HQ.
        return true;
    }

    public function view(User $user, ItemMaster $itemMaster): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isHeadOffice();
    }

    public function update(User $user, ItemMaster $itemMaster): bool
    {
        return $user->isHeadOffice();
    }

    public function delete(User $user, ItemMaster $itemMaster): bool
    {
        return $user->isHeadOffice();
    }

    public function restore(User $user, ItemMaster $itemMaster): bool
    {
        return $user->isHeadOffice();
    }

    public function forceDelete(User $user, ItemMaster $itemMaster): bool
    {
        return $user->isHeadOffice();
    }
}
