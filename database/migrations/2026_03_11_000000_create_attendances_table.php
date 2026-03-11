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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_application_id')
                ->constrained('program_applications')
                ->cascadeOnDelete();

            $table->foreignId('program_id')
                ->nullable()
                ->constrained('programs')
                ->nullOnDelete();

            $table->string('shift_ref')->default('');
            $table->string('shift_name')->nullable();

            $table->date('attendance_date');

            $table->enum('status', [
                'Present',
                'Absent',
                'Late',
                'Excused',
                'Not Marked',
            ])->default('Not Marked');

            $table->text('note')->nullable();

            $table->foreignId('marked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'program_application_id',
                'attendance_date',
                'shift_ref',
            ], 'attendance_unique_per_day_shift');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};