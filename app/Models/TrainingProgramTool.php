<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingProgramTool extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'name',
        'sort_order',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
}