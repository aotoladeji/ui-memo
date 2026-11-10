<?php

namespace App\Services;

use App\Models\{Annotation, Memo, MemoRecipient, MemoRoute, StickyNote, StickyNoteReply, User};

class ChainVisibility
{
    /** The hop a user is viewing from. No recipient row => 404 (existence is not leaked). */
    public function viewerRoute(Memo $memo, User $u, ?int $routeId = null): MemoRoute
    {
        $q = MemoRecipient::where(['memo_id' => $memo->id, 'user_id' => $u->id]);
        $row = $routeId ? $q->where('route_id', $routeId)->firstOrFail() : $q->latest('id')->firstOrFail();
        return $row->route;
    }

    /** Everything the viewer may see: filtered notes, annotations and replies. */
    public function view(Memo $memo, User $u, ?int $routeId = null): array
    {
        $r = $this->viewerRoute($memo, $u, $routeId);
        return ['route' => $r] + $this->viewAt($memo, $r->path, $u->id);
    }

    /** Core filter by hop path; also used by forward-preview (inside a rolled-back transaction). */
    public function viewAt(Memo $memo, string $vpath, int $uid): array
    {
        $r = (object) ['path' => $vpath];
        $u = (object) ['id' => $uid];

        $notes = StickyNote::where('sticky_notes.memo_id', $memo->id)->whereNull('sticky_notes.deleted_at')
            ->visibleInChain($r->path, $u->id)->get();
        $anns = Annotation::where('annotations.memo_id', $memo->id)->whereNull('annotations.deleted_at')
            ->visibleInChain($r->path, $u->id)->get();

        $originPaths = MemoRoute::where('memo_id', $memo->id)->pluck('path', 'id');
        $replies = StickyNoteReply::whereIn('note_id', $notes->pluck('id'))->orderBy('id')->get()
            ->filter(fn ($rp) => $this->replyVisible($rp, $originPaths[$rp->origin_route_id] ?? '', $r->path, $u->id))
            ->values();

        return ['notes' => $notes, 'annotations' => $anns, 'replies' => $replies];
    }

    /** A reply follows its thread's visibility; it is hidden only if it sits on a sibling branch. */
    private function replyVisible(StickyNoteReply $rp, string $originPath, string $vpath, int $uid): bool
    {
        return $rp->author_id === $uid
            || str_starts_with($originPath, $vpath)      // same hop or downstream
            || str_starts_with($vpath, $originPath);     // ancestor hop (thread masking already applied)
    }

    public function visibleIds(Memo $m, User $u, ?int $routeId = null): array
    {
        $v = $this->view($m, $u, $routeId);
        return ['sticky_note' => $v['notes']->pluck('id')->all(), 'annotation' => $v['annotations']->pluck('id')->all()];
    }
}
