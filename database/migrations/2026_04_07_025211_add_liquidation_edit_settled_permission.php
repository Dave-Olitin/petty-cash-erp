<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('liquidation.edit_settled', 'web');
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::findByName('liquidation.edit_settled', 'web')?->delete();
    }
};
