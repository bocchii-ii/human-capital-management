<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'application_id',
        'salary',
        'currency',
        'start_date',
        'expires_at',
        'status',
        'letter_path',
        'sent_at',
        'signed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'salary'     => 'decimal:2',
            'start_date' => 'date',
            'expires_at' => 'date',
            'sent_at'    => 'datetime',
            'signed_at'  => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
