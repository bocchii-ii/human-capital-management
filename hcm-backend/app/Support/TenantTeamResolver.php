<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class TenantTeamResolver implements \Spatie\Permission\Contracts\PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->teamId ?? auth()->user()?->tenant_id;
    }

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->teamId = $id instanceof Model ? $id->getKey() : $id;
    }
}
