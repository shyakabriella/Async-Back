<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'badge',
        'intro',
        'overview',
        'duration',
        'level',
        'format',
        'icon_key',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function skills()
    {
        return $this->hasMany(TrainingProgramSkill::class)->orderBy('sort_order');
    }

    public function outcomes()
    {
        return $this->hasMany(TrainingProgramOutcome::class)->orderBy('sort_order');
    }

    public function tools()
    {
        return $this->hasMany(TrainingProgramTool::class)->orderBy('sort_order');
    }
}