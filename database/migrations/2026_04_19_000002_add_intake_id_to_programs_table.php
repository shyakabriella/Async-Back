<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programs') && !Schema::hasColumn('programs', 'intake_id')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->foreignId('intake_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('intakes')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('programs') && Schema::hasColumn('programs', 'intake_id')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->dropForeign(['intake_id']);
                $table->dropColumn('intake_id');
            });
        }
    }
};