<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentStudentReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_user_id',
        'student_user_id',
        'program_id',
        'amount_paid',
        'commission_percentage',
        'commission_amount',
        'currency',
        'status',
        'notes',
        'registered_at',
    ];

    protected $casts = [
        'amount_paid' => 'float',
        'commission_percentage' => 'float',
        'commission_amount' => 'float',
        'registered_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}