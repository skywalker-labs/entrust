<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Traits;

use Skywalker\Entrust\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Log an entrust audit event.
     */
    protected function logAudit(string $event, array $data): void
    {
        // Only log if the audit_logs table exists or feature is enabled (implied by model creation)
        try {
            AuditLog::create([
                'user_id'        => Auth::id(),
                'event'          => $event,
                'target_user_id' => $data['target_user_id'],
                'role_id'        => $data['role_id'] ?? null,
                'permission_id'  => $data['permission_id'] ?? null,
                'team_id'        => $data['team_id'] ?? null,
                'metadata'       => $data['metadata'] ?? [],
            ]);
        } catch (\Exception $e) {
            // Silently fail if table doesn't exist yet to avoid breaking app before migration
        }
    }
}
