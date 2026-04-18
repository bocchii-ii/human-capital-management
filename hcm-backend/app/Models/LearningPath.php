<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningPath extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'target_role',
        'target_department_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function pathCourses(): HasMany
    {
        return $this->hasMany(LearningPathCourse::class)->orderBy('sort_order');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_courses')
            ->withPivot('sort_order', 'is_required')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
