<?php

namespace App\Actions;

use App\Models\{AuditLog, Memo, MemoRoute, MemoSendOutbox, User};
use App\Services\{RoutingGuard, SendOutboxProcessor};
use Illuminate\Support\Facades\DB;

class SendMemo
{
    public function __construct(
        private RoutingGuard $guard,
        private SendOutboxProcessor $outbox,
    ) {}

    public function handle(User $u, Memo $memo, array $recipientIds = []): Memo
    {
        $recipientIds = array_values(array_unique(array_map('intval', $recipientIds)));

        $outboxRow = DB::transaction(function () use ($memo, $u, $recipientIds) {
            $locked = Memo::whereKey($memo->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->created_by === $u->id, 403, 'Only the author can send a draft.');
            abort_if($locked->status !== 'draft', 409, 'This memo has already been sent.');

            if ($locked->routing_type === 'peer' && empty($recipientIds)) {
                abort(422, 'Choose at least one recipient.');
            }

            $this->guard->assertCanSend($u, $locked->routing_type, $recipientIds);

            $route = MemoRoute::where(['memo_id' => $locked->id, 'kind' => 'origin'])->firstOrFail();

            $updated = Memo::where('id', $locked->id)
                ->where('created_by', $u->id)
                ->where('status', 'draft')
                ->update(['status' => 'sent', 'sent_at' => now()]);
            abort_if($updated !== 1, 409, 'This memo has already been sent.');

            AuditLog::record($locked, $u, 'memo.sent', [
                'type' => $locked->routing_type,
                'count' => count($recipientIds),
            ]);

            return MemoSendOutbox::create([
                'memo_id' => $locked->id,
                'route_id' => $route->id,
                'sender_id' => $u->id,
                'routing_type' => $locked->routing_type,
                'recipient_ids' => $recipientIds,
                'status' => 'pending',
            ]);
        });

        // Dispatched only after the draft->sent + audit + outbox transaction commits.
        $this->outbox->dispatch($outboxRow);

        return $memo->refresh();
    }
}
