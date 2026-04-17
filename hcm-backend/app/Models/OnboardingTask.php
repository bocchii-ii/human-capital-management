<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'onboarding_template_id',
        'title',
        'description',
        'assignee_role',
        'due_days_offset',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required'     => 'boolean',
            'due_days_offset' => 'integer',
            'sort_order'      => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(OnboardingTaskCompletion::class);
    }
}
