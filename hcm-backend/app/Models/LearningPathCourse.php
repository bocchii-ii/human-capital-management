<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningPathCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_path_id',
        'course_id',
        'sort_order',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required'  => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
