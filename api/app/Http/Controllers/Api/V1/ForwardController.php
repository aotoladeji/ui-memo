<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ForwardMemo;
use App\Http\Controllers\Controller;
use App\Models\{Memo, MemoRecipient};
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
        'rules.*.target_type' => 'required|in:sticky_note,annotation',
        'rules.*.target_id' => 'required|integer',
        'rules.*.effect' => 'required|in:include,exclude',
        'from_route_id' => 'nullable|integer',
    ];

    public function store(Request $r, Memo $memo, ForwardMemo $forward)
    {
        $route = $forward->handle($r->user(), $memo, $r->validate(self::RULES));
        return response(['forward_id' => $route->id], 201);
    }

    /** Dry-run: what would the recipient see? Creates the hop inside a transaction, reads, rolls back. */
    public function preview(Request $r, Memo $memo, ForwardMemo $forward, ChainVisibility $vis)
    {
        $d = $r->validate(self::RULES);
        DB::beginTransaction();
        try {
            $route = $forward->handle($r->user(), $memo, $d);
            $v = $vis->viewAt($memo, $route->path, (int) $d['recipient_ids'][0]);
            return [
                'notes' => $v['notes']->map->only('id', 'page_number', 'x', 'y', 'body', 'classification', 'author_id'),
                'annotations' => $v['annotations']->map->only('id', 'page_number', 'tool', 'geometry', 'style'),
                'replies' => $v['replies']->map->only('id', 'note_id', 'body', 'author_id'),
            ];
        } finally {
            DB::rollBack();
        }
    }

    /** Recipient marks the memo done; blocked while an open Requires-Action note is assigned to them. */
    public function complete(Request $r, Memo $memo, ChainVisibility $vis)
    {
        $route = $vis->viewerRoute($memo, $r->user());
        $open = \App\Models\StickyNote::where('sticky_notes.memo_id', $memo->id)
            ->where('requires_action', 1)->where('status', 'open')->where('assigned_to', $r->user()->id)
            ->visibleInChain($route->path, $r->user()->id)->count();
        abort_if($open > 0, 409, "Resolve $open pending action note(s) first.");
        MemoRecipient::where(['memo_id' => $memo->id, 'user_id' => $r->user()->id])->update(['action_state' => 'done']);
        return ['action_state' => 'done'];
    }
}
