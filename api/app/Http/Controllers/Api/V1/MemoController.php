<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\{InitiateMemo, SendMemo};
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Memo, MemoRecipient, User};
use App\Services\{ChainVisibility, RoutingGuard};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoController extends Controller
{
    public function index(Request $r)
    {
        $box = $r->query('box', 'inbox');
        $q = Memo::visibleTo($r->user())->with('author:id,name,title')->latest('memos.id');
        match ($box) {
            'drafts' => $q->where('status', 'draft')->where('created_by', $r->user()->id),
            'sent'   => $q->where('status', '!=', 'draft')->where('created_by', $r->user()->id),
            default  => $q->where('status', '!=', 'draft')->where('created_by', '!=', $r->user()->id),
        };
        return $q->paginate(25)->through(fn ($m) => [
            'id' => $m->id, 'ref_no' => $m->ref_no, 'subject' => $m->subject, 'priority' => $m->priority,
            'status' => $m->status, 'sent_at' => $m->sent_at, 'author' => $m->author,
        ]);
    }

    public function store(Request $r, InitiateMemo $initiate, RoutingGuard $guard)
    {
        $d = $r->validate([
            'subject' => 'required|string|max:255',
            'routing_type' => 'required|in:internal_unit,peer,global',
            'priority' => 'nullable|in:normal,urgent',
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg|max:20480',
            'page_count' => 'nullable|integer|min:1|max:500',
            'page_dims' => 'nullable|array',
        ]);
        if ($d['routing_type'] === 'global') $guard->assertCanSend($r->user(), 'global');   // fail early
        $memo = $initiate->handle($r->user(), $d, $r->file('file'));
        return response(['id' => $memo->id, 'ref_no' => $memo->ref_no], 201);
    }

    public function send(Request $r, Memo $memo, SendMemo $send)
    {
        $d = $r->validate(['recipient_ids' => 'array', 'recipient_ids.*' => 'integer|exists:users,id']);
        $send->handle($r->user(), $memo, $d['recipient_ids'] ?? []);
        return ['status' => 'sent'];
    }

    /** Memo + FILTERED annotations/notes/replies for this viewer. Masked items are never serialised. */
    public function show(Request $r, Memo $memo, ChainVisibility $vis)
    {
        $v = $vis->view($memo, $r->user(), $r->integer('route_id') ?: null);   // 404 if not a recipient
        MemoRecipient::where(['memo_id' => $memo->id, 'user_id' => $r->user()->id, 'route_id' => $v['route']->id])
            ->whereNull('read_at')->update(['read_at' => now()]);
        AuditLog::record($memo, $r->user(), 'memo.viewed', ['route_id' => $v['route']->id]);

        $doc = $memo->document;
        $names = User::whereIn('id', collect($v['notes'])->pluck('author_id')
            ->merge($v['replies']->pluck('author_id'))->merge($v['annotations']->pluck('author_id'))->unique())
            ->get(['id', 'name', 'title', 'is_executive'])->keyBy('id');

        return [
            'memo' => $memo->only('id', 'ref_no', 'subject', 'priority', 'status', 'routing_type', 'created_by'),
            'route' => ['id' => $v['route']->id, 'kind' => $v['route']->kind, 'depth' => $v['route']->depth],
            'document' => ['url' => url("/api/v1/memos/{$memo->id}/file"), 'mime' => $doc->mime,
                           'page_count' => $doc->page_count, 'page_dims' => $doc->page_dims],
            'annotations' => $v['annotations'], 'notes' => $v['notes'], 'replies' => $v['replies'],
            'authors' => $names,
        ];
    }

    /** Streams the original, only after the recipient check. Swap for an S3 temporaryUrl in production. */
    public function file(Request $r, Memo $memo, ChainVisibility $vis)
    {
        $vis->viewerRoute($memo, $r->user());
        $doc = $memo->document;
        return Storage::disk(config('filesystems.memo_disk', 'local'))->response($doc->storage_path, null, ['Content-Type' => $doc->mime]);
    }
}
