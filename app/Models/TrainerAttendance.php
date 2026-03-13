<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainerAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'attendance_date',
        'status',
        'check_in_at',
        'check_out_at',
        'note',
        'daily_rate',
        'salary_amount',
        'is_paid',
        'paid_at',
        'marked_by',
        'paid_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'daily_rate' => 'decimal:2',
        'salary_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function markedByUser()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}