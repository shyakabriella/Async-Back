<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE agent_student_referrals
            SET status = CASE
                WHEN status = 'pending' THEN 'not_paid'
                WHEN status = 'approved' THEN 'paid'
                WHEN status = 'rejected' THEN 'quit'
                ELSE status
            END
        ");

        DB::statement("
            ALTER TABLE agent_student_referrals
            MODIFY COLUMN status ENUM('not_paid', 'paid', 'quit')
            NOT NULL DEFAULT 'not_paid'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE agent_student_referrals
            SET status = CASE
                WHEN status = 'not_paid' THEN 'pending'
                WHEN status = 'paid' THEN 'approved'
                WHEN status = 'quit' THEN 'rejected'
                ELSE status
            END
        ");

        DB::statement("
            ALTER TABLE agent_student_referrals
            MODIFY COLUMN status ENUM('pending', 'approved', 'rejected')
            NOT NULL DEFAULT 'pending'
        ");
    }
};