<?php

namespace App\Models;

use App\Models\Role;
use App\Models\Program;
use App\Models\Wallet;
use App\Models\AgentProfile;
use App\Models\AgentPresence;
use App\Models\ChatConversation;
use App\Models\TrainerAttendance;
use App\Models\AgentStudentReferral;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'is_active',
        'last_login_at',
        'daily_rate',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
            'daily_rate'        => 'decimal:2',
            'password'          => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_user')
            ->withTimestamps();
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function trainerAttendances()
    {
        return $this->hasMany(TrainerAttendance::class, 'trainer_id');
    }

    public function markedTrainerAttendances()
    {
        return $this->hasMany(TrainerAttendance::class, 'marked_by');
    }

    public function paidTrainerAttendances()
    {
        return $this->hasMany(TrainerAttendance::class, 'paid_by');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function agentPresence()
    {
        return $this->hasOne(AgentPresence::class);
    }

    public function assignedConversations()
    {
        return $this->hasMany(ChatConversation::class, 'assigned_agent_id');
    }

    /**
     * Agent profile details
     */
    public function agentProfile()
    {
        return $this->hasOne(AgentProfile::class);
    }

    /**
     * Students/referrals brought by this agent
     */
    public function agentReferrals()
    {
        return $this->hasMany(AgentStudentReferral::class, 'agent_user_id');
    }

    /**
     * If this user is a student referred by an agent
     */
    public function referredByAgent()
    {
        return $this->hasOne(AgentStudentReferral::class, 'student_user_id');
    }
}