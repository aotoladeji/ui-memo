<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Chain-of-custody visibility for sticky_notes, annotations, and minutes.
 *
 * An item on origin route `o` (`o.path`, `o.depth`) is visible to a viewer at hop path `$vpath` when:
 *   - Notes and annotations: the author always sees their own items on a memo they hold, OR the route paths
 *     are ancestor/descendant as below.
 *   - Minutes: route paths must be ancestor/descendant; authors cannot see their minutes on sibling branches.
 *   - `o.path` starts with `$vpath` (same hop or downstream hop — downstream items flow upstream), OR
 *   - `$vpath` starts with `o.path` (ancestor hop) AND no hop `h` after `o` up to `$vpath` hides it.
 *
 * Precedence at hop `h`:
 *   - Explicit `exclude` rule for `(target_type, target_id)` -> hidden.
 *   - Explicit `include` rule for `(target_type, target_id)` -> visible (overrides mode and classification).
 *   - For sticky notes/annotations: `h.note_mode = 'none'` / `h.annotation_mode = 'none'` -> hidden.
 *     (Minutes are not hidden by `note_mode = 'none'`, only by explicit rules or `hidden_classifications`.)
 *   - `classification` in `h.hidden_classifications` -> hidden.
 */
trait ChainScoped
{
    public function scopeVisibleInChain(Builder $q, string $vpath, int $uid): Builder
    {
        $t = $this->getTable();
        $type = match ($t) {
            'sticky_notes' => 'sticky_note',
            'minutes' => 'minute',
            default => 'annotation',
        };
        $mode = match ($type) {
            'sticky_note' => 'note_mode',
            'annotation' => 'annotation_mode',
            default => null,
        };
        $hasVisibilityScope = $type !== 'minute';

        $driver = $q->getConnection()->getDriverName();
        $originPrefixExpr = match ($driver) {
            'mysql', 'mariadb' => "CONCAT(o.path, '%')",
            default => "(o.path || '%')",
        };
        $hopPrefixExpr = match ($driver) {
            'mysql', 'mariadb' => "CONCAT(h.path, '%')",
            default => "(h.path || '%')",
        };
        $classificationExpr = match ($driver) {
            'mysql', 'mariadb' => "JSON_CONTAINS(COALESCE(h.hidden_classifications, JSON_ARRAY()), JSON_QUOTE($t.classification))",
            'pgsql' => "COALESCE(h.hidden_classifications::jsonb, '[]'::jsonb) @> to_jsonb($t.classification::text)",
            default => "EXISTS (SELECT 1 FROM json_each(COALESCE(h.hidden_classifications, '[]')) WHERE json_each.value = $t.classification)",
        };

        $modeClause = $mode ? "WHEN h.$mode = 'none' THEN 1\n          " : '';

        $hidden = "CASE
          WHEN EXISTS (SELECT 1 FROM memo_forward_visibility_rules r WHERE r.route_id = h.id
               AND r.target_type = '$type' AND r.target_id = $t.id AND r.effect = 'exclude') THEN 1
          WHEN EXISTS (SELECT 1 FROM memo_forward_visibility_rules r WHERE r.route_id = h.id
               AND r.target_type = '$type' AND r.target_id = $t.id AND r.effect = 'include') THEN 0
          {$modeClause}WHEN {$classificationExpr} THEN 1
          ELSE 0 END = 1";

        $routeRelation = function ($w) use ($vpath, $originPrefixExpr) {
            $w->where('o.path', 'like', $vpath . '%')
                ->orWhereRaw("? LIKE {$originPrefixExpr}", [$vpath]);
        };
        $routeVisibility = function ($w) use ($vpath, $hidden, $originPrefixExpr, $hopPrefixExpr) {
            $w->where('o.path', 'like', $vpath . '%')
                ->orWhere(function ($x) use ($vpath, $hidden, $originPrefixExpr, $hopPrefixExpr) {
                    $x->whereRaw("? LIKE {$originPrefixExpr}", [$vpath])
                        ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('memo_routes as h')
                            ->whereRaw('h.memo_id = o.memo_id')
                            ->whereRaw("? LIKE {$hopPrefixExpr}", [$vpath])
                            ->whereRaw('h.depth > o.depth')
                            ->whereRaw($hidden));
                });
        };

        return $q->join('memo_routes as o', 'o.id', '=', "$t.origin_route_id")
            ->select("$t.*")
            ->when($hasVisibilityScope, fn ($w) => $w->where(fn ($x) => $x->where("$t.visibility_scope", 'chain')->orWhere("$t.author_id", $uid)))
            ->where(function ($w) use ($t, $type, $uid, $routeRelation, $routeVisibility) {
                if ($type === 'minute') {
                    $w->where(fn ($own) => $own->where("$t.author_id", $uid)->where($routeRelation))
                        ->orWhere(fn ($other) => $other->where("$t.author_id", '!=', $uid)->where($routeVisibility));
                } else {
                    $w->where("$t.author_id", $uid)->orWhere($routeVisibility);
                }
            });
    }
}
