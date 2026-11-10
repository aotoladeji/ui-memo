<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Chain-of-custody visibility for sticky_notes and annotations.
 * See docs: an item is visible to a viewer at hop path $vpath when
 *   author = viewer  OR  origin is same/downstream hop  OR
 *   (origin is an ancestor hop AND no hop after the origin, up to the viewer, hides it).
 */
trait ChainScoped
{
    public function scopeVisibleInChain(Builder $q, string $vpath, int $uid): Builder
    {
        $t = $this->getTable();
        $type = $t === 'sticky_notes' ? 'sticky_note' : 'annotation';
        $mode = $type === 'sticky_note' ? 'note_mode' : 'annotation_mode';

        $hidden = "CASE
          WHEN EXISTS (SELECT 1 FROM memo_forward_visibility_rules r WHERE r.route_id = h.id
               AND r.target_type = '$type' AND r.target_id = $t.id AND r.effect = 'exclude') THEN 1
          WHEN EXISTS (SELECT 1 FROM memo_forward_visibility_rules r WHERE r.route_id = h.id
               AND r.target_type = '$type' AND r.target_id = $t.id AND r.effect = 'include') THEN 0
          WHEN h.$mode = 'none' THEN 1
          WHEN JSON_CONTAINS(COALESCE(h.hidden_classifications, JSON_ARRAY()), JSON_QUOTE($t.classification)) THEN 1
          ELSE 0 END = 1";

        return $q->join('memo_routes as o', 'o.id', '=', "$t.origin_route_id")
            ->select("$t.*")
            ->where(fn ($w) => $w->where("$t.visibility_scope", 'chain')->orWhere("$t.author_id", $uid))
            ->where(function ($w) use ($t, $uid, $vpath, $hidden) {
                $w->where("$t.author_id", $uid)
                  ->orWhere('o.path', 'like', $vpath . '%')
                  ->orWhere(function ($x) use ($vpath, $hidden) {
                      $x->whereRaw("? LIKE CONCAT(o.path, '%')", [$vpath])
                        ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('memo_routes as h')
                            ->whereRaw('h.memo_id = o.memo_id')
                            ->whereRaw("? LIKE CONCAT(h.path, '%')", [$vpath])
                            ->whereRaw('h.depth > o.depth')
                            ->whereRaw($hidden));
                  });
            });
    }
}
