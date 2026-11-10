<?php

namespace App\Actions;

use App\Models\{AuditLog, Memo, MemoRecipient, MemoRoute, User};
use App\Services\{ChainVisibility, RoutingGuard};
use Illuminate\Support\Facades\DB;

class ForwardMemo
{
    public function __construct(private RoutingGuard $guard, private ChainVisibility $vis) {}

    public function handle(User $sender, Memo $memo, array $in): MemoRoute
    {
        return DB::transaction(function () use ($sender, $memo, $in) {
            // 1. Sender must hold the memo. Their latest hop becomes the parent.
            $parent = $this->vis->viewerRoute($memo, $sender, $in['from_route_id'] ?? null);

            // 2. Forwarding is peer-to-peer. Tighten here if governance forbids leaving the unit.
            $this->guard->assertCanSend($sender, 'peer', $in['recipient_ids']);

            // 3. A sender can only reference (include or exclude) what they can see themselves.
            $seen = $this->vis->visibleIds($memo, $sender, $parent->id);
            foreach ($in['rules'] ?? [] as $r) {
                abort_unless(in_array($r['target_id'], $seen[$r['target_type']] ?? [], true), 422, 'Invalid visibility target.');
            }

            // 4. The hop. Its id is the forward_id.
            $route = MemoRoute::create([
                'memo_id' => $memo->id, 'parent_route_id' => $parent->id, 'depth' => $parent->depth + 1,
                'path' => '', 'kind' => 'forward', 'from_user_id' => $sender->id,
                'annotation_mode' => $in['include_annotations'] ? 'all' : 'none',
                'note_mode' => $in['note_mode'] ?? 'all',
                'hidden_classifications' => $in['hidden_classifications'] ?? [],
                'message' => $in['message'] ?? null,
            ]);
            $route->update(['path' => $parent->path . $route->id . '/']);

            // 5. Per-item overrides; the original rows are never touched.
            foreach ($in['rules'] ?? [] as $r) {
                $route->rules()->create(['target_type' => $r['target_type'], 'target_id' => $r['target_id'], 'effect' => $r['effect']]);
            }

            // 6. Recipients and audit trail.
            foreach (array_unique($in['recipient_ids']) as $uid) {
                MemoRecipient::create(['memo_id' => $memo->id, 'route_id' => $route->id, 'user_id' => $uid,
                                       'recipient_type' => 'to', 'delivered_at' => now()]);
            }
            AuditLog::record($memo, $sender, 'memo.forwarded', [
                'route_id' => $route->id, 'to' => $in['recipient_ids'],
                'mask' => ['annotations' => $route->annotation_mode, 'notes' => $route->note_mode,
                           'classes' => $route->hidden_classifications, 'rules' => $in['rules'] ?? []],
            ]);
            return $route;
        });
    }
}
