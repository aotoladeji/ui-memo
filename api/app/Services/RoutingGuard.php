<?php

namespace App\Services;

use App\Models\User;

class RoutingGuard
{
    /** Throws 403/422 when $from may not route a memo of $type to $toUserIds. */
    public function assertCanSend(User $from, string $type, array $toUserIds = []): void
    {
        match ($type) {
            'global' => abort_unless($from->mayBroadcast(), 403, 'You do not have global broadcast permission.'),
            'internal_unit' => abort_unless(
                $from->unit_id && User::whereIn('id', $toUserIds)->where('unit_id', '!=', $from->unit_id)->doesntExist(),
                403, 'Internal unit memos can only go to members of your unit.'),
            'peer' => abort_unless(
                User::whereIn('id', $toUserIds)->where('is_active', 1)->count() === count(array_unique($toUserIds)),
                422, 'Unknown or inactive recipient.'),
        };
    }
}
