<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'daily_rate')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('daily_rate', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'daily_rate')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('daily_rate');
            });
        }
    }
};