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
        Schema::create('pet_cash_requests', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique()->nullable();

            $table->foreignId('program_id')
                ->constrained('programs')
                ->restrictOnDelete();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->text('purpose');
            $table->text('description')->nullable();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('RWF');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->text('approval_note')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->decimal('balance_before', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->index(['program_id', 'status']);
            $table->index(['requested_by', 'status']);
            $table->index(['approved_by']);
            $table->index(['rejected_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_cash_requests');
    }
};