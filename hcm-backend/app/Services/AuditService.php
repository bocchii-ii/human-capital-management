<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        string $event,
        Model $auditable = null,
        array $oldValues = null,
        array $newValues = null,
        int $tenantId = null,
        int $userId = null,
    ): void {
        $tenantId ??= app()->bound('tenant') ? app('tenant')->id : null;
        $userId   ??= Auth::id();

        if ($tenantId === null) {
            return;
        }

        AuditLog::create([
            'tenant_id'      => $tenantId,
            'user_id'        => $userId,
            'event'          => $event,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id'   => $auditable?->getKey(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'ip_address'     => Request::ip(),
        ]);
    }
}
