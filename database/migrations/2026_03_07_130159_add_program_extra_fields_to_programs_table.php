<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('code');
            $table->string('badge')->nullable()->after('name');
            $table->string('level')->nullable()->after('duration');
            $table->string('format')->nullable()->after('level');
            $table->text('intro')->nullable()->after('image');
            $table->text('overview')->nullable()->after('description');
            $table->string('icon_key')->nullable()->after('overview');
            $table->boolean('is_active')->default(true)->after('icon_key');

            $table->json('skills')->nullable()->after('modules');
            $table->json('outcomes')->nullable()->after('skills');
            $table->json('tools')->nullable()->after('outcomes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'badge',
                'level',
                'format',
                'intro',
                'overview',
                'icon_key',
                'is_active',
                'skills',
                'outcomes',
                'tools',
            ]);
        });
    }
};