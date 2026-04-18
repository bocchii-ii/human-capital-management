<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'title',
        'slug',
        'description',
        'category',
        'status',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseModule::class);
    }
}
