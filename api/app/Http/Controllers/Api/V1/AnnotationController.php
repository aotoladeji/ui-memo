<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Annotation, Memo};
use App\Services\ChainVisibility;
use Illuminate\Http\Request;

class AnnotationController extends Controller
{
    /** Batch upsert for debounced autosave. Returns client_id => id so the UI can swap temp ids. */
    public function sync(Request $r, Memo $memo, ChainVisibility $vis)
    {
        $route = $vis->viewerRoute($memo, $r->user(), $r->integer('route_id') ?: null);
        $d = $r->validate([
            'items' => 'required|array|max:200',
            'items.*.client_id' => 'required|string|max:64',
            'items.*.id' => 'nullable|integer',
            'items.*.page_number' => 'required|integer|min:1',
            'items.*.tool' => 'required|in:freehand,highlight,text,stamp,signature,shape',
            'items.*.geometry' => 'required|array',
            'items.*.style' => 'nullable|array',
            'items.*.classification' => 'nullable|in:standard,executive,confidential',
            'items.*.z_index' => 'nullable|integer',
        ]);

        $map = [];
        foreach ($d['items'] as $it) {
            $payload = [
                'page_number' => $it['page_number'], 'tool' => $it['tool'], 'geometry' => $it['geometry'],
                'style' => $it['style'] ?? null, 'z_index' => $it['z_index'] ?? 0,
                'classification' => $it['classification'] ?? ($r->user()->is_executive ? 'executive' : 'standard'),
            ];
            if (!empty($it['id'])) {
                $a = Annotation::where(['id' => $it['id'], 'memo_id' => $memo->id, 'author_id' => $r->user()->id])->firstOrFail();
                $a->update($payload);
            } else {
                $a = Annotation::create($payload + ['memo_id' => $memo->id, 'origin_route_id' => $route->id,
                                                    'author_id' => $r->user()->id]);
            }
            $map[] = ['client_id' => $it['client_id'], 'id' => $a->id];
        }
        return ['saved' => $map];
    }

    public function destroy(Request $r, Annotation $annotation)
    {
        abort_unless($annotation->author_id === $r->user()->id, 403);
        $annotation->delete();                      // soft delete: audit trail preserved
        return response()->noContent();
    }
}
