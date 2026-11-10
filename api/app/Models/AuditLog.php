<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['meta' => 'array'];

    public static function record(?Memo $memo, ?User $actor, string $action, array $meta = []): void
    {
        static::create(['memo_id' => $memo?->id, 'actor_id' => $actor?->id, 'action' => $action,
                        'meta' => $meta, 'ip' => request()?->ip()]);
    }
}
