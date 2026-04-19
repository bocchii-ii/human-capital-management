<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function create(
        int $tenantId,
        int $userId,
        string $type,
        string $title,
        string $body = null,
        array $data = null,
    ): AppNotification {
        return AppNotification::create([
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
            'type'      => $type,
            'title'     => $title,
            'body'      => $body,
            'data'      => $data,
        ]);
    }

    public function notifyUser(User $user, string $type, string $title, string $body = null, array $data = null): void
    {
        $this->create($user->tenant_id, $user->id, $type, $title, $body, $data);
    }
}
