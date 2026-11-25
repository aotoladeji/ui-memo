<?php

namespace App\Actions;

use App\Models\{AuditLog, Memo, MemoDocument, MemoRecipient, MemoRoute, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InitiateMemo
{
    /** Creates a draft for file upload: memo + document + origin hop + author recipient row. */
    public function handle(User $u, array $in, UploadedFile $file): Memo
    {
        $disk = config('filesystems.memo_disk', 'local');
        $path = $file->store('memos/' . now()->format('Y/m'), $disk);
        if (! $path) {
            throw new RuntimeException('Failed to store uploaded memo file.');
        }

        $realPath = $file->getRealPath();
        $sha256 = $realPath && is_file($realPath)
            ? hash_file('sha256', $realPath)
            : hash('sha256', (string) Storage::disk($disk)->get($path));

        try {
            return DB::transaction(function () use ($u, $in, $file, $path, $sha256) {
                $memo = Memo::create([
                    'uuid' => (string) Str::uuid(),
                    'ref_no' => $this->refNo($u),
                    'subject' => $in['subject'],
                    'created_by' => $u->id,
                    'origin_unit_id' => $u->unit_id,
                    'routing_type' => $in['routing_type'] ?? 'peer',
                    'priority' => $in['priority'] ?? 'normal',
                    'status' => 'draft',
                ]);

                MemoDocument::create([
                    'memo_id' => $memo->id,
                    'kind' => 'file',
                    'storage_path' => $path,
                    'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'page_count' => (int) ($in['page_count'] ?? 1),
                    'page_dims' => $in['page_dims'] ?? null,         // client sends measured pdf.js sizes
                    'sha256' => $sha256,
                ]);

                $route = MemoRoute::create([
                    'memo_id' => $memo->id,
                    'depth' => 0,
                    'path' => '',
                    'kind' => 'origin',
                    'from_user_id' => $u->id,
                ]);
                $route->update(['path' => "/{$route->id}/"]);

                MemoRecipient::create([
                    'memo_id' => $memo->id,
                    'route_id' => $route->id,
                    'user_id' => $u->id,
                    'recipient_type' => 'author',
                    'delivered_at' => now(),
                ]);
                AuditLog::record($memo, $u, 'memo.created');
                return $memo;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    /** Creates a draft for template memo: memo + document + origin hop + author recipient row. */
    public function handleTemplate(User $u, array $in, array $template): Memo
    {
        return DB::transaction(function () use ($u, $in, $template) {
            $refNo = $this->refNo($u);
            if (empty($template['reference_number'])) {
                $template['reference_number'] = $refNo;
            }

            $memo = Memo::create([
                'uuid' => (string) Str::uuid(),
                'ref_no' => $refNo,
                'subject' => $in['subject'],
                'created_by' => $u->id,
                'origin_unit_id' => $u->unit_id,
                'routing_type' => $in['routing_type'] ?? 'peer',
                'priority' => $in['priority'] ?? 'normal',
                'status' => 'draft',
            ]);

            MemoDocument::create([
                'memo_id' => $memo->id,
                'kind' => 'template',
                'storage_path' => null,
                'mime' => 'application/json',
                'page_count' => (int) ($in['page_count'] ?? 1),
                'page_dims' => $in['page_dims'] ?? null,
                'template_data' => $template,
                'sha256' => null,
            ]);

            $route = MemoRoute::create([
                'memo_id' => $memo->id,
                'depth' => 0,
                'path' => '',
                'kind' => 'origin',
                'from_user_id' => $u->id,
            ]);
            $route->update(['path' => "/{$route->id}/"]);

            MemoRecipient::create([
                'memo_id' => $memo->id,
                'route_id' => $route->id,
                'user_id' => $u->id,
                'recipient_type' => 'author',
                'delivered_at' => now(),
            ]);
            AuditLog::record($memo, $u, 'memo.created');
            return $memo;
        });
    }

    public function refNo(User $u): string
    {
        $u->loadMissing('unit');
        $code = $u->unit?->code ?? 'UI';
        return sprintf('UI/%s/%d/%04d', $code, now()->year, (Memo::withTrashed()->max('id') ?? 0) + 1);
    }
}
