<?php

namespace App\Actions;

use App\Jobs\FanOutRecipients;
use App\Models\{AuditLog, Memo, MemoRoute, User};
use App\Services\RoutingGuard;
use Illuminate\Support\Facades\DB;

class SendMemo
{
    public function __construct(private RoutingGuard $guard) {}

    public function handle(User $u, Memo $memo, array $recipientIds = []): Memo
    {
        abort_unless($memo->created_by === $u->id && $memo->status === 'draft', 403, 'Only the author can send a draft.');
        $this->guard->assertCanSend($u, $memo->routing_type, $recipientIds);

        DB::transaction(function () use ($memo, $u, $recipientIds) {
            $memo->update(['status' => 'sent', 'sent_at' => now()]);
            AuditLog::record($memo, $u, 'memo.sent', ['type' => $memo->routing_type, 'count' => count($recipientIds)]);
        });

        $route = MemoRoute::where(['memo_id' => $memo->id, 'kind' => 'origin'])->firstOrFail();
        // Peer memos are small: insert now. Unit/global are fanned out by a queued, chunked job.
        $job = new FanOutRecipients($memo->id, $route->id, $memo->routing_type, $recipientIds, $u->id);
        $memo->routing_type === 'peer' ? $job->handle() : dispatch($job);
        return $memo->refresh();
    }
}
