<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTaskCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'onboarding_assignment_id',
        'onboarding_task_id',
        'completed_by',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(OnboardingAssignment::class, 'onboarding_assignment_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(OnboardingTask::class, 'onboarding_task_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
