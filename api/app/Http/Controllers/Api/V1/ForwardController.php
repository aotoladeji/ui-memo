<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ForwardMemo;
use App\Http\Controllers\Controller;
use App\Models\{Memo, MemoRecipient, User};
use App\Services\ChainVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForwardController extends Controller
{
    private const RULES = [
        'recipient_ids' => 'required|array|min:1|max:200', 'recipient_ids.*' => 'integer|exists:users,id',
        'message' => 'nullable|string|max:2000',
        'include_annotations' => 'required|boolean',
        'note_mode' => 'required|in:all,none',
        'hidden_classifications' => 'array', 'hidden_classifications.*' => 'in:executive,confidential',
        'rules' => 'array|max:500',
        'rules.*.target_type' => 'required|in:sticky_note,annotation,minute',
        'rules.*.target_id' => 'required|integer',
        'rules.*.effect' => 'required|in:include,exclude',
        'from_route_id' => 'nullable|integer|min:1',
    ];

    public function store(Request $r, Memo $memo, ForwardMemo $forward)
    {
        $route = $forward->handle($r->user(), $memo, $r->validate(self::RULES));
        return response(['forward_id' => $route->id], 201);
    }

    /** Dry-run: what would each selected recipient see? Creates the hop inside a transaction, reads, rolls back. */
    public function preview(Request $r, Memo $memo, ForwardMemo $forward, ChainVisibility $vis)
    {
        $d = $r->validate(self::RULES);
        DB::beginTransaction();
        try {
            $route = $forward->handle($r->user(), $memo, $d);
            $recipientIds = array_values(array_unique(array_map('intval', $d['recipient_ids'])));
            $users = User::whereIn('id', $recipientIds)->get(['id', 'name', 'title'])->keyBy('id');

            $previews = [];
            foreach ($recipientIds as $recipientId) {
                $v = $vis->viewAt($memo, $route->path, $recipientId);
                $recipientUser = $users->get($recipientId);
                $previews[] = [
                    'recipient_id' => $recipientId,
                    'recipient' => [
                        'id' => $recipientId,
                        'name' => $recipientUser?->name ?? '',
                        'title' => $recipientUser?->title,
                    ],
                    'notes' => $v['notes']->map->only('id', 'page_number', 'x', 'y', 'body', 'classification', 'author_id')->values()->all(),
                    'annotations' => $v['annotations']->map->only('id', 'page_number', 'tool', 'geometry', 'style')->values()->all(),
                    'replies' => $v['replies']->map->only('id', 'note_id', 'body', 'author_id')->values()->all(),
                    'minutes' => $v['minutes']->map->only('id', 'page_number', 'x', 'y', 'body', 'classification', 'author_id')->values()->all(),
                    'minute_replies' => $v['minute_replies']->map->only('id', 'minute_id', 'body', 'author_id')->values()->all(),
                ];
            }

            $first = $previews[0];

            return [
                'notes' => $first['notes'],
                'annotations' => $first['annotations'],
                'replies' => $first['replies'],
                'minutes' => $first['minutes'],
                'minute_replies' => $first['minute_replies'],
                'recipients' => $previews,
            ];
        } finally {
            DB::rollBack();
        }
    }

    /** Recipient marks the memo done; blocked while an open Requires-Action note is assigned to them. */
    public function complete(Request $r, Memo $memo, ChainVisibility $vis)
    {
        $d = $r->validate(['route_id' => 'nullable|integer|min:1']);
        $route = $vis->viewerRoute($memo, $r->user(), isset($d['route_id']) ? (int) $d['route_id'] : null);
        $open = \App\Models\StickyNote::where('sticky_notes.memo_id', $memo->id)
            ->where('requires_action', 1)->where('status', 'open')->where('assigned_to', $r->user()->id)
            ->visibleInChain($route->path, $r->user()->id)->count();
        abort_if($open > 0, 409, "Resolve $open pending action note(s) first.");
        MemoRecipient::where(['memo_id' => $memo->id, 'user_id' => $r->user()->id])->update(['action_state' => 'done']);
        return ['action_state' => 'done'];
    }
}
