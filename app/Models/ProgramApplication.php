<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProgramApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'program_title',
        'program_slug',
        'shift_id',
        'shift_name',
        'experience_level',
        'selected_skills',
        'selected_tools',
        'auth_provider',
        'first_name',
        'last_name',
        'email',
        'phone',
        'country',
        'city',
        'date_of_birth',
        'gender',
        'education_level',
        'school_name',
        'field_of_study',
        'agree_terms',
        'agree_communication',
        'status',
        'admin_note',
        'submitted_at',
        'meta',
    ];

    protected $casts = [
        'selected_skills' => 'array',
        'selected_tools' => 'array',
        'agree_terms' => 'boolean',
        'agree_communication' => 'boolean',
        'date_of_birth' => 'date',
        'submitted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}