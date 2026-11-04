<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalSeparation
{
    public function canApprove(User $user, Model $record): bool
    {
        $owners = $record instanceof \App\Models\FabaMonthlyApproval
            ? [$record->submitted_by]
            : [$record->created_by, $record->submitted_by];

        return ! in_array((string) $user->id, array_map('strval', array_filter($owners)), true);
    }
}
