<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'program_application_id',
        'program_id',
        'shift_ref',
        'shift_name',
        'attendance_date',
        'status',
        'note',
        'marked_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(ProgramApplication::class, 'program_application_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function markedByUser()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}