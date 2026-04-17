<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    const STAGES = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

    protected $fillable = [
        'tenant_id',
        'job_requisition_id',
        'applicant_id',
        'stage',
        'cover_letter',
        'rejection_reason',
        'notes',
        'stage_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'stage_changed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function jobRequisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }
}
