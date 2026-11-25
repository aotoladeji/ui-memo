<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Memo, MemoRecipient, StickyNote, StickyNoteReply};
use App\Services\ChainVisibility;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function store(Request $r, Memo $memo, ChainVisibility $vis)
    {
        $d = $r->validate([
            'route_id' => 'nullable|integer|min:1',
            'page_number' => 'required|integer|min:1',
            'x' => 'required|numeric|between:0,1',
            'y' => 'required|numeric|between:0,1',
            'body' => 'required|string|max:5000',
            'color' => 'nullable|string|max:9',
            'classification' => 'nullable|in:standard,executive,confidential',
            'visibility_scope' => 'nullable|in:chain,private',
            'requires_action' => 'boolean',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);
        $route = $vis->viewerRoute($memo, $r->user(), isset($d['route_id']) ? (int) $d['route_id'] : null);
        if (!empty($d['assigned_to'])) {   // assignee must hold the memo
            abort_unless(MemoRecipient::where(['memo_id' => $memo->id, 'user_id' => $d['assigned_to']])->exists(), 422, 'Assignee does not have this memo.');
        }
        unset($d['route_id']);
        $note = StickyNote::create($d + [
            'memo_id' => $memo->id,
            'origin_route_id' => $route->id,
            'author_id' => $r->user()->id,
            'classification' => $d['classification'] ?? ($r->user()->is_executive ? 'executive' : 'standard'),
        ]);
        return response($note->load('replies'), 201);
    }

    /** Move, edit, flag, or resolve. Author may edit/move; author or assignee may resolve. */
    public function update(Request $r, StickyNote $note, ChainVisibility $vis)
    {
        $d = $r->validate([
            'route_id' => 'nullable|integer|min:1',
            'page_number' => 'integer|min:1',
            'x' => 'numeric|between:0,1',
            'y' => 'numeric|between:0,1',
            'body' => 'string|max:5000',
            'requires_action' => 'boolean',
            'status' => 'in:open,resolved',
        ]);
        $this->assertVisible($r, $note, $vis, isset($d['route_id']) ? (int) $d['route_id'] : null);
        $isAuthor = $note->author_id === $r->user()->id;
        $layout = array_intersect_key($d, array_flip(['page_number', 'x', 'y', 'body', 'requires_action']));
        abort_if(!empty($layout) && !$isAuthor, 403, 'Only the author can edit this note.');
        if (isset($d['status'])) {
            abort_unless($isAuthor || $note->assigned_to === $r->user()->id, 403, 'Only the author or assignee can resolve.');
            $layout += [
                'status' => $d['status'],
                'resolved_by' => $d['status'] === 'resolved' ? $r->user()->id : null,
                'resolved_at' => $d['status'] === 'resolved' ? now() : null,
            ];
        }
        $note->update($layout);
        return response()->json($note->fresh('replies'));
    }

    public function destroy(Request $r, StickyNote $note, ChainVisibility $vis)
    {
        $d = $r->validate(['route_id' => 'nullable|integer|min:1']);
        $this->assertVisible($r, $note, $vis, isset($d['route_id']) ? (int) $d['route_id'] : null);
        abort_unless($note->author_id === $r->user()->id, 403, 'Only the author can delete this note.');
        $note->delete();
        return response()->noContent();
    }

    public function reply(Request $r, StickyNote $note, ChainVisibility $vis)
    {
        $d = $r->validate([
            'route_id' => 'nullable|integer|min:1',
            'body' => 'required|string|max:5000',
        ]);
        [, $route] = $this->assertVisible($r, $note, $vis, isset($d['route_id']) ? (int) $d['route_id'] : null);
        $rp = StickyNoteReply::create([
            'note_id' => $note->id,
            'origin_route_id' => $route->id,
            'author_id' => $r->user()->id,
            'body' => $d['body'],
        ]);
        return response($rp, 201);
    }

    /** A note the user cannot see in their chain does not exist for them (404). */
    private function assertVisible(Request $r, StickyNote $note, ChainVisibility $vis, ?int $routeId = null): array
    {
        $memo = Memo::findOrFail($note->memo_id);
        $route = $vis->viewerRoute($memo, $r->user(), $routeId);
        StickyNote::where('sticky_notes.id', $note->id)->whereNull('sticky_notes.deleted_at')->visibleInChain($route->path, $r->user()->id)->firstOrFail();
        return [$memo, $route];
    }
}
