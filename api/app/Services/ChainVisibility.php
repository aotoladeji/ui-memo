<?php

namespace App\Services;

use App\Models\{Annotation, Memo, MemoRecipient, MemoRoute, Minute, MinuteReply, StickyNote, StickyNoteReply, User};

class ChainVisibility
{
    /** The hop a user is viewing from. No recipient row (or invalid route for user/memo) => 404 (existence is not leaked). */
    public function viewerRoute(Memo $memo, User $u, ?int $routeId = null): MemoRoute
    {
        $q = MemoRecipient::where(['memo_id' => $memo->id, 'user_id' => $u->id])
            ->whereHas('route', fn ($route) => $route->where('memo_id', $memo->id));
        $row = $routeId !== null ? $q->where('route_id', $routeId)->firstOrFail() : $q->latest('id')->firstOrFail();
        return $row->route;
    }

    /** Everything the viewer may see: filtered notes, annotations, replies, minutes and minute replies. */
    public function view(Memo $memo, User $u, ?int $routeId = null): array
    {
        $r = $this->viewerRoute($memo, $u, $routeId);
        return ['route' => $r] + $this->viewAt($memo, $r->path, $u->id);
    }

    /** Core filter by hop path; also used by forward-preview (inside a rolled-back transaction). */
    public function viewAt(Memo $memo, string $vpath, int $uid): array
    {
        $notes = StickyNote::where('sticky_notes.memo_id', $memo->id)->whereNull('sticky_notes.deleted_at')
            ->visibleInChain($vpath, $uid)->get();
        $anns = Annotation::where('annotations.memo_id', $memo->id)->whereNull('annotations.deleted_at')
            ->visibleInChain($vpath, $uid)->get();
        $minutes = Minute::where('minutes.memo_id', $memo->id)->whereNull('minutes.deleted_at')
            ->visibleInChain($vpath, $uid)->orderBy('minutes.id')->get();

        $originPaths = MemoRoute::where('memo_id', $memo->id)->pluck('path', 'id');

        $replies = StickyNoteReply::whereIn('note_id', $notes->pluck('id'))->orderBy('id')->get()
            ->filter(fn ($rp) => $originPaths->has($rp->origin_route_id)
                && $this->replyVisible($rp->author_id, $originPaths[$rp->origin_route_id], $vpath, $uid, true))
            ->values();

        $minuteReplies = MinuteReply::whereIn('minute_id', $minutes->pluck('id'))->orderBy('id')->get()
            ->filter(fn ($rp) => $originPaths->has($rp->origin_route_id)
                && $this->replyVisible($rp->author_id, $originPaths[$rp->origin_route_id], $vpath, $uid, false))
            ->values();

        return [
            'notes' => $notes,
            'annotations' => $anns,
            'replies' => $replies,
            'minutes' => $minutes,
            'minute_replies' => $minuteReplies,
        ];
    }

    /** A reply follows its parent thread's visibility; it is hidden if it sits on a sibling branch. */
    private function replyVisible(int $authorId, string $originPath, string $vpath, int $uid, bool $authorMaySeeAcrossBranches): bool
    {
        return ($authorMaySeeAcrossBranches && $authorId === $uid)
            || str_starts_with($originPath, $vpath)      // same hop or downstream
            || str_starts_with($vpath, $originPath);     // ancestor hop (parent masking already applied)
    }

    public function visibleIds(Memo $m, User $u, ?int $routeId = null): array
    {
        $v = $this->view($m, $u, $routeId);
        return [
            'sticky_note' => $v['notes']->pluck('id')->all(),
            'annotation' => $v['annotations']->pluck('id')->all(),
            'minute' => $v['minutes']->pluck('id')->all(),
        ];
    }
}
