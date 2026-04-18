<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'course_module_id',
        'title',
        'content_type',
        'content',
        'video_url',
        'file_url',
        'duration_minutes',
        'sort_order',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required'      => 'boolean',
            'duration_minutes' => 'integer',
            'sort_order'       => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }
}
