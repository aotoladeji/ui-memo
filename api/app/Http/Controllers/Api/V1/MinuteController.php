<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Memo, MemoRoute, Minute, MinuteReply};
use App\Services\ChainVisibility;
use Illuminate\Http\Request;

class MinuteController extends Controller
{
    /**
     * Store a new minute on a memo.
     * Route: POST /memos/{memo}/minutes (or POST /minutes)
     */
    public function store(Request $r, ChainVisibility $vis, ?Memo $memo = null)
    {
        $d = $r->validate([
            'memo_id' => $memo && $memo->exists ? 'nullable|integer' : 'required|integer|exists:memos,id',
            'route_id' => 'nullable|integer|min:1',
            'page_number' => 'required|integer|min:1',
            'x' => 'required|numeric|between:0,1',
            'y' => 'required|numeric|between:0,1',
            'body' => 'required|string|max:5000',
            'color' => 'nullable|string|max:9',
            'classification' => 'nullable|in:standard,executive,confidential',
        ]);

        if (!$memo || !$memo->exists) {
            $memo = Memo::findOrFail($d['memo_id']);
        }

        $route = $vis->viewerRoute($memo, $r->user(), isset($d['route_id']) ? (int) $d['route_id'] : null);

        $minute = Minute::create([
            'memo_id' => $memo->id,
            'origin_route_id' => $route->id,
            'author_id' => $r->user()->id,
            'page_number' => $d['page_number'],
            'x' => $d['x'],
            'y' => $d['y'],
            'body' => $d['body'],
            'color' => $d['color'] ?? '#E0E7FF',
            'classification' => $d['classification'] ?? ($r->user()->is_executive ? 'executive' : 'standard'),
        ]);

        return response($minute, 201);
    }

    /**
     * Update an existing minute.
     * Route: PATCH /minutes/{minute}
     */
    public function update(Request $r, Minute $minute, ChainVisibility $vis)
    {
        $d = $r->validate([
            'route_id' => 'nullable|integer|min:1',
            'page_number' => 'integer|min:1',
            'x' => 'numeric|between:0,1',
            'y' => 'numeric|between:0,1',
            'body' => 'string|max:5000',
        ]);

        $this->assertVisible($r, $minute, $vis, isset($d['route_id']) ? (int) $d['route_id'] : null);
        abort_unless($minute->author_id === $r->user()->id, 403, 'Only the author can edit this minute.');

        $updateData = array_intersect_key($d, array_flip(['page_number', 'x', 'y', 'body']));
        $minute->update($updateData);

        return response()->json($minute->fresh());
    }

    /**
     * Delete a minute.
     * Route: DELETE /minutes/{minute}
     */
    public function destroy(Request $r, Minute $minute, ChainVisibility $vis)
    {
        $d = $r->validate(['route_id' => 'nullable|integer|min:1']);
        $this->assertVisible($r, $minute, $vis, isset($d['route_id']) ? (int) $d['route_id'] : null);
        abort_unless($minute->author_id === $r->user()->id, 403, 'Only the author can delete this minute.');

        $minute->delete();

        return response()->noContent();
    }

    /**
     * Reply to a minute.
     * Route: POST /minutes/{minute}/replies
     */
    public function reply(Request $r, Minute $minute, ChainVisibility $vis)
    {
        $d = $r->validate([
            'route_id' => 'nullable|integer|min:1',
            'body' => 'required|string|max:5000',
        ]);

        [, $route] = $this->assertVisible($r, $minute, $vis, isset($d['route_id']) ? (int) $d['route_id'] : null);

        $reply = MinuteReply::create([
            'minute_id' => $minute->id,
            'origin_route_id' => $route->id,
            'author_id' => $r->user()->id,
            'body' => $d['body'],
        ]);

        return response($reply, 201);
    }

    /**
     * Ensure the authenticated user holds the memo on the selected route and can see the minute.
     * Returns [$memo, $route] or aborts with 404 so hidden/sibling-branch minutes are not leaked.
     *
     * @return array{0: Memo, 1: MemoRoute}
     */
    private function assertVisible(Request $r, Minute $minute, ChainVisibility $vis, ?int $routeId = null): array
    {
        $memo = Memo::findOrFail($minute->memo_id);
        $route = $vis->viewerRoute($memo, $r->user(), $routeId);
        Minute::where('minutes.id', $minute->id)
            ->whereNull('minutes.deleted_at')
            ->visibleInChain($route->path, $r->user()->id)
            ->firstOrFail();

        return [$memo, $route];
    }
}
