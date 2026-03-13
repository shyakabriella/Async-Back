<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status'); // Present, Absent, Late, Excused, Not Marked
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->text('note')->nullable();

            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->decimal('salary_amount', 12, 2)->default(0);

            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['trainer_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_attendances');
    }
};