<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AuditLogPolicy
{
    public function comment(User $user, AuditLog $auditLog): Response
    {
        return $auditLog->user_id === $user->id
            ? Response::allow()
            : Response::deny('You can only comment on your own audit log entries.');
    }
}
