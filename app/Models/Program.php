<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'badge',
        'category',
        'duration',
        'level',
        'format',
        'status',
        'instructor',
        'students',
        'start_date',
        'end_date',
        'image',
        'intro',
        'description',
        'overview',
        'icon_key',
        'is_active',
        'objectives',
        'modules',
        'skills',
        'outcomes',
        'tools',
        'experience_levels',
        'shifts',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'objectives' => 'array',
        'modules' => 'array',
        'skills' => 'array',
        'outcomes' => 'array',
        'tools' => 'array',
        'experience_levels' => 'array',
        'shifts' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Renamed relationship methods to avoid conflict with JSON attributes
    public function skillItems()
    {
        return $this->hasMany(TrainingProgramSkill::class, 'program_id')->orderBy('sort_order');
    }

    public function outcomeItems()
    {
        return $this->hasMany(TrainingProgramOutcome::class, 'program_id')->orderBy('sort_order');
    }

    public function toolItems()
    {
        return $this->hasMany(TrainingProgramTool::class, 'program_id')->orderBy('sort_order');
    }

    public function applications()
    {
        return $this->hasMany(ProgramApplication::class, 'program_id')->latest();
    }
}