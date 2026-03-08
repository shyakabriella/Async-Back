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
        Schema::create('program_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();

            // snapshots from program at time of application
            $table->string('program_title')->nullable();
            $table->string('program_slug')->nullable();

            // selected program options
            $table->string('shift_id')->nullable();
            $table->string('shift_name')->nullable();
            $table->string('experience_level')->nullable();
            $table->json('selected_skills')->nullable();
            $table->json('selected_tools')->nullable();

            // source
            $table->string('auth_provider')->default('manual');

            // applicant details
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('country');
            $table->string('city')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();

            // background
            $table->string('education_level')->nullable();
            $table->string('school_name')->nullable();
            $table->string('field_of_study')->nullable();

            // consents
            $table->boolean('agree_terms')->default(false);
            $table->boolean('agree_communication')->default(true);

            // admin side
            $table->string('status')->default('Pending');
            $table->text('admin_note')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['program_id', 'status']);
            $table->index('email');
            $table->index('shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_applications');
    }
};