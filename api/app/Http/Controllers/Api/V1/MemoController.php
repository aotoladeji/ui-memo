<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\{InitiateMemo, SendMemo};
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Memo, MemoDocument, MemoRecipient, Minute, MinuteReply, User};
use App\Services\{ChainVisibility, RoutingGuard};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function index(Request $r)
    {
        $d = $r->validate([
            'box' => 'nullable|in:inbox,sent,drafts',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1',
        ]);

        $user = $r->user();
        $box = $d['box'] ?? 'inbox';
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) ($d['per_page'] ?? self::DEFAULT_PER_PAGE)));

        $q = Memo::visibleTo($user)
            ->with(['author.unit.department'])
            ->latest('memos.id');

        match ($box) {
            'drafts' => $q->where('status', 'draft')->where('created_by', $user->id),
            'sent'   => $q->where('status', '!=', 'draft')->where('created_by', $user->id),
            default  => $q->where('status', '!=', 'draft')->where('created_by', '!=', $user->id),
        };

        $paginator = $q->paginate($perPage);
        $memos = $paginator->getCollection();
        $memoIds = $memos->pluck('id');

        $unreadMemoIds = $memoIds->isEmpty()
            ? collect()
            : MemoRecipient::whereIn('memo_id', $memoIds)
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->pluck('memo_id')
                ->flip();

        $data = $memos->map(function ($m) use ($user, $unreadMemoIds) {
            $author = $m->author;
            $dept = $author?->unit?->department?->name ?? $author?->unit?->name ?? '';
            $unread = $m->created_by !== $user->id && isset($unreadMemoIds[$m->id]);

            return [
                'id' => $m->id,
                'ref_no' => $m->ref_no,
                'subject' => $m->subject,
                'priority' => $m->priority,
                'status' => $m->status,
                'sent_at' => $m->sent_at,
                'unread' => $unread,
                'author' => [
                    'id' => $author?->id,
                    'name' => $author?->name ?? '',
                    'title' => $author?->title,
                    'department' => $dept,
                ],
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function store(Request $r, InitiateMemo $initiate, RoutingGuard $guard)
    {
        $user = $r->user();

        if ($r->input('kind') === 'template') {
            abort_if(! $user->unit_id, 422, 'Account must belong to a unit to create a template memo.');

            $d = $r->validate([
                'subject' => 'required|string|max:255',
                'routing_type' => 'nullable|in:internal_unit,peer,global',
                'priority' => 'nullable|in:normal,urgent',
                'page_count' => 'nullable|integer|min:1|max:500',
                'page_dims' => 'nullable|array',
                'template' => 'required|array',
                'template.to' => 'required|string|filled',
                'template.date' => ['required', 'date_format:Y-m-d'],
            ]);

            $routing = $d['routing_type'] ?? 'peer';
            if ($routing === 'global') {
                $guard->assertCanSend($user, 'global');
            }

            $user->loadMissing('unit.department');
            $unit = $user->unit;
            $dept = $unit?->department;

            $template = $r->input('template', []);
            $template['to'] = $template['to'];
            $template['date'] = $template['date'];
            $template['subject'] = $d['subject'];
            $template['thru'] = isset($template['thru']) && is_array($template['thru']) ? array_values($template['thru']) : [];
            $template['cc'] = isset($template['cc']) && is_array($template['cc']) ? array_values($template['cc']) : [];
            $template['office_line'] = $template['office_line'] ?? '';
            $template['body'] = $template['body'] ?? '';

            // Overwrite fixed account presets - ignore client spoofed values
            $template['from_name'] = $user->name;
            $template['from_staff_no'] = $user->staff_no ?? '';
            $template['department_name'] = $dept?->name ?? $unit?->name ?? '';
            if ($dept?->id) {
                $template['department_id'] = $dept->id;
            }
            $template['signature'] = [
                'name' => $user->name,
                'title' => $user->title ?? '',
                'unit' => $unit?->name ?? '',
            ];

            $d['routing_type'] = $routing;
            $memo = $initiate->handleTemplate($user, $d, $template);
            return response()->json(['id' => $memo->id, 'ref_no' => $memo->ref_no], 201);
        }

        $d = $r->validate([
            'subject' => 'required|string|max:255',
            'routing_type' => 'required|in:internal_unit,peer,global',
            'priority' => 'nullable|in:normal,urgent',
            'file' => 'required|file|mimetypes:application/pdf,image/png,image/jpeg|mimes:pdf,png,jpg,jpeg|max:20480',
            'page_count' => 'nullable|integer|min:1|max:500',
            'page_dims' => 'nullable|array',
            'page_dims.*.w' => 'required_with:page_dims|numeric|min:1',
            'page_dims.*.h' => 'required_with:page_dims|numeric|min:1',
        ], [
            'file.mimes' => 'Only PDF, PNG, and JPEG files are supported. Word documents (.doc, .docx) are not supported and must be exported to PDF first.',
            'file.mimetypes' => 'Only PDF, PNG, and JPEG files are supported. Word documents (.doc, .docx) are not supported and must be exported to PDF first.',
        ]);

        if ($d['routing_type'] === 'global') {
            $guard->assertCanSend($user, 'global');
        }

        $memo = $initiate->handle($user, $d, $r->file('file'));
        return response()->json(['id' => $memo->id, 'ref_no' => $memo->ref_no], 201);
    }

    public function updateTemplate(Request $r, Memo $memo)
    {
        abort_if($memo->status !== 'draft' || $memo->created_by !== $r->user()->id, 403, 'Forbidden');

        $d = $r->validate([
            'subject' => 'sometimes|required|string|max:255',
            'page_count' => 'nullable|integer|min:1|max:500',
            'page_dims' => 'nullable|array',
            'template' => 'nullable|array',
        ]);

        if (isset($d['subject'])) {
            $memo->update(['subject' => $d['subject']]);
        }

        $doc = $memo->document;
        if ($doc && $doc->kind === 'template') {
            $existingTemplate = $doc->template_data ?? [];
            $newTemplate = $r->input('template', []);

            $u = $r->user()->loadMissing('unit.department');
            $unit = $u->unit;
            $dept = $unit?->department;

            $merged = array_merge($existingTemplate, $newTemplate);
            if (isset($d['subject'])) {
                $merged['subject'] = $d['subject'];
            }
            $merged['from_name'] = $u->name;
            $merged['from_staff_no'] = $u->staff_no ?? '';
            $merged['department_name'] = $dept?->name ?? $unit?->name ?? '';
            if ($dept?->id) {
                $merged['department_id'] = $dept->id;
            }
            $merged['signature'] = [
                'name' => $u->name,
                'title' => $u->title ?? '',
                'unit' => $unit?->name ?? '',
            ];
            if (isset($newTemplate['thru']) && is_array($newTemplate['thru'])) {
                $merged['thru'] = array_values($newTemplate['thru']);
            }
            if (isset($newTemplate['cc']) && is_array($newTemplate['cc'])) {
                $merged['cc'] = array_values($newTemplate['cc']);
            }

            $docUpdates = ['template_data' => $merged];
            if (isset($d['page_count'])) {
                $docUpdates['page_count'] = $d['page_count'];
            }
            if (array_key_exists('page_dims', $d)) {
                $docUpdates['page_dims'] = $d['page_dims'];
            }
            $doc->update($docUpdates);
        }

        return response()->json(['status' => 'updated']);
    }

    public function send(Request $r, Memo $memo, SendMemo $send)
    {
        $d = $r->validate(['recipient_ids' => 'array', 'recipient_ids.*' => 'integer|exists:users,id']);
        $send->handle($r->user(), $memo, $d['recipient_ids'] ?? []);
        return ['status' => 'sent'];
    }

    /** Memo + FILTERED annotations/notes/replies/minutes/minuteReplies for this viewer. Masked items are never serialised. */
    public function show(Request $r, Memo $memo, ChainVisibility $vis)
    {
        $d = $r->validate(['route_id' => 'nullable|integer|min:1']);
        $v = $vis->view($memo, $r->user(), isset($d['route_id']) ? (int) $d['route_id'] : null);   // 404 if not a recipient
        MemoRecipient::where(['memo_id' => $memo->id, 'user_id' => $r->user()->id, 'route_id' => $v['route']->id])
            ->whereNull('read_at')->update(['read_at' => now()]);
        AuditLog::record($memo, $r->user(), 'memo.viewed', ['route_id' => $v['route']->id]);

        $doc = $memo->document;
        $isTemplate = $doc && $doc->kind === 'template';

        $authorIds = collect($v['notes'])->pluck('author_id')
            ->merge(collect($v['replies'])->pluck('author_id'))
            ->merge(collect($v['annotations'])->pluck('author_id'))
            ->merge(collect($v['minutes'])->pluck('author_id'))
            ->merge(collect($v['minute_replies'])->pluck('author_id'))
            ->unique()
            ->filter();

        $names = User::whereIn('id', $authorIds)
            ->get(['id', 'name', 'title', 'is_executive'])
            ->keyBy('id');

        return [
            'memo' => $memo->only('id', 'ref_no', 'subject', 'priority', 'status', 'routing_type', 'created_by'),
            'route' => ['id' => $v['route']->id, 'kind' => $v['route']->kind, 'depth' => $v['route']->depth],
            'document' => [
                'url' => $isTemplate ? null : "/api/v1/memos/{$memo->id}/file",
                'mime' => $doc?->mime ?? ($isTemplate ? 'application/json' : 'application/pdf'),
                'page_count' => (int) ($doc?->page_count ?? 1),
                'page_dims' => $doc?->page_dims,
                'kind' => $isTemplate ? 'template' : 'file',
                'template' => $isTemplate ? ($doc->template_data ?? null) : null,
            ],
            'annotations' => $v['annotations'],
            'notes' => $v['notes'],
            'replies' => $v['replies'],
            'minutes' => $v['minutes'],
            'minuteReplies' => $v['minute_replies'],
            'authors' => (object) $names->toArray(),
        ];
    }

    /** Streams the original, only after the recipient check. Swap for an S3 temporaryUrl in production. */
    public function file(Request $r, Memo $memo, ChainVisibility $vis)
    {
        $d = $r->validate(['route_id' => 'nullable|integer|min:1']);
        $vis->viewerRoute($memo, $r->user(), isset($d['route_id']) ? (int) $d['route_id'] : null);
        $doc = $memo->document;
        if (! $doc || $doc->kind === 'template') {
            abort(404, 'No file exists for template memo.');
        }
        return Storage::disk(config('filesystems.memo_disk', 'local'))->response($doc->storage_path, null, ['Content-Type' => $doc->mime]);
    }
}
