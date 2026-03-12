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
        'price',
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
        'curriculum',
        'skills',
        'outcomes',
        'tools',
        'experience_levels',
        'shifts',
    ];

    protected $casts = [
        'students' => 'integer',
        'price' => 'decimal:2',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'objectives' => 'array',
        'modules' => 'array',
        'curriculum' => 'array',
        'skills' => 'array',
        'outcomes' => 'array',
        'tools' => 'array',
        'experience_levels' => 'array',
        'shifts' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function setShiftsAttribute($value): void
    {
        $this->attributes['shifts'] = $this->normalizeJsonArray($value);
    }

    public function setObjectivesAttribute($value): void
    {
        $this->attributes['objectives'] = $this->normalizeJsonArray($value);
    }

    public function setModulesAttribute($value): void
    {
        $this->attributes['modules'] = $this->normalizeJsonArray($value);
    }

    public function setCurriculumAttribute($value): void
    {
        $this->attributes['curriculum'] = $this->normalizeJsonArray($value);
    }

    public function setSkillsAttribute($value): void
    {
        $this->attributes['skills'] = $this->normalizeJsonArray($value);
    }

    public function setOutcomesAttribute($value): void
    {
        $this->attributes['outcomes'] = $this->normalizeJsonArray($value);
    }

    public function setToolsAttribute($value): void
    {
        $this->attributes['tools'] = $this->normalizeJsonArray($value);
    }

    public function setExperienceLevelsAttribute($value): void
    {
        $this->attributes['experience_levels'] = $this->normalizeJsonArray($value);
    }

    public function setStartDateAttribute($value): void
    {
        $this->attributes['start_date'] = $value ?: null;
    }

    public function setEndDateAttribute($value): void
    {
        $this->attributes['end_date'] = $value ?: null;
    }

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

    public function users()
    {
        return $this->belongsToMany(User::class, 'program_user')
            ->withTimestamps();
    }

    private function normalizeJsonArray($value): string
    {
        if ($value === null || $value === '') {
            return json_encode([]);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode(is_array($decoded) ? array_values($decoded) : []);
            }

            return json_encode([$value]);
        }

        if (is_array($value)) {
            return json_encode(array_values($value));
        }

        return json_encode([]);
    }
}