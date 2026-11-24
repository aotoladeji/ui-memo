<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Memo, Minute, MinuteReply};
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
        if (!$memo || !$memo->exists) {
            $memoId = $r->input('memo_id');
            abort_unless($memoId, 422, 'Memo ID is required.');
            $memo = Memo::findOrFail($memoId);
        }

        $route = $vis->viewerRoute($memo, $r->user(), $r->integer('route_id') ?: null);

        $d = $r->validate([
            'page_number' => 'required|integer|min:1',
            'x' => 'required|numeric|between:0,1',
            'y' => 'required|numeric|between:0,1',
            'body' => 'required|string|max:5000',
            'color' => 'nullable|string|max:9',
            'classification' => 'nullable|in:standard,executive,confidential',
        ]);

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
    public function update(Request $r, Minute $minute)
    {
        abort_unless($minute->author_id === $r->user()->id, 403, 'Only the author can edit this minute.');

        $d = $r->validate([
            'page_number' => 'integer|min:1',
            'x' => 'numeric|between:0,1',
            'y' => 'numeric|between:0,1',
            'body' => 'string|max:5000',
        ]);

        $updateData = array_intersect_key($d, array_flip(['page_number', 'x', 'y', 'body']));
        $minute->update($updateData);

        return response()->json($minute->fresh());
    }

    /**
     * Delete a minute.
     * Route: DELETE /minutes/{minute}
     */
    public function destroy(Request $r, Minute $minute)
    {
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
        $memo = Memo::findOrFail($minute->memo_id);
        $route = $vis->viewerRoute($memo, $r->user(), $r->integer('route_id') ?: null);

        $d = $r->validate([
            'body' => 'required|string|max:5000',
        ]);

        $reply = MinuteReply::create([
            'minute_id' => $minute->id,
            'origin_route_id' => $route->id,
            'author_id' => $r->user()->id,
            'body' => $d['body'],
        ]);

        return response($reply, 201);
    }
}
