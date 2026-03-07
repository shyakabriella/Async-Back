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
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('duration');
            $table->enum('status', ['Active', 'Draft', 'Archived'])->default('Active');
            $table->string('instructor');
            $table->integer('students')->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->json('objectives')->nullable();
            $table->json('modules')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};