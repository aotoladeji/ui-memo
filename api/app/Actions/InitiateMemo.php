<?php

namespace App\Actions;

use App\Models\{AuditLog, Memo, MemoDocument, MemoRecipient, MemoRoute, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InitiateMemo
{
    /** Creates a draft: memo + document + origin hop + author recipient row. */
    public function handle(User $u, array $in, UploadedFile $file): Memo
    {
        return DB::transaction(function () use ($u, $in, $file) {
            $path = $file->store('memos/' . now()->format('Y/m'), config('filesystems.memo_disk', 'local'));

            $memo = Memo::create([
                'uuid' => (string) Str::uuid(),
                'ref_no' => $this->refNo($u),
                'subject' => $in['subject'],
                'created_by' => $u->id,
                'origin_unit_id' => $u->unit_id,
                'routing_type' => $in['routing_type'],
                'priority' => $in['priority'] ?? 'normal',
                'status' => 'draft',
            ]);

            MemoDocument::create([
                'memo_id' => $memo->id, 'storage_path' => $path, 'mime' => $file->getMimeType(),
                'page_count' => (int) ($in['page_count'] ?? 1),
                'page_dims' => $in['page_dims'] ?? null,         // client sends measured pdf.js sizes
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ]);

            $route = MemoRoute::create(['memo_id' => $memo->id, 'depth' => 0, 'path' => '', 'kind' => 'origin',
                                        'from_user_id' => $u->id]);
            $route->update(['path' => "/{$route->id}/"]);

            MemoRecipient::create(['memo_id' => $memo->id, 'route_id' => $route->id, 'user_id' => $u->id,
                                   'recipient_type' => 'author', 'delivered_at' => now()]);
            AuditLog::record($memo, $u, 'memo.created');
            return $memo;
        });
    }

    private function refNo(User $u): string
    {
        $code = $u->unit?->code ?? 'UI';
        return sprintf('UI/%s/%d/%04d', $code, now()->year, (Memo::withTrashed()->max('id') ?? 0) + 1);
    }
}
