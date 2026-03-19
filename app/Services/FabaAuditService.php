<?php

namespace App\Services;

use App\Models\FabaAuditLog;

class FabaAuditService
{
    public function log(
        ?string $actorId,
        string $action,
        string $module,
        ?string $referenceType,
        ?string $referenceId,
        ?int $year,
        ?int $month,
        string $summary,
        array $details = [],
    ): FabaAuditLog {
        return FabaAuditLog::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'module' => $module,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'year' => $year,
            'month' => $month,
            'summary' => $summary,
            'details' => $details,
        ]);
    }
}
