<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasColumn('branches', 'allow_overdraft')) {
    Schema::table('branches', function (Blueprint $table) {
        $table->boolean('allow_overdraft')->default(false)->after('is_active');
    });
    echo "Column added.\n";
} else {
    echo "Column already exists.\n";
}
