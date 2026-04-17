<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobRequisition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'department_id',
        'position_id',
        'hiring_manager_id',
        'approved_by',
        'title',
        'description',
        'requirements',
        'employment_type',
        'work_location',
        'is_remote',
        'headcount',
        'salary_min',
        'salary_max',
        'currency',
        'status',
        'approved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_remote'   => 'boolean',
            'salary_min'  => 'decimal:2',
            'salary_max'  => 'decimal:2',
            'approved_at' => 'datetime',
            'closed_at'   => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hiring_manager_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
