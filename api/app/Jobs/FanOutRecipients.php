<?php

namespace App\Jobs;

use App\Models\{MemoRecipient, User};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FanOutRecipients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $memoId, public int $routeId, public string $type,
        public array $recipientIds, public int $senderId,
    ) {}

    public function handle(): void
    {
        $sender = User::findOrFail($this->senderId);
        $q = User::where('is_active', 1)->where('id', '!=', $sender->id);

        match ($this->type) {
            'global' => null,
            'internal_unit' => $q->where('unit_id', $sender->unit_id)
                                 ->when($this->recipientIds, fn ($x) => $x->whereIn('id', $this->recipientIds)),
            'peer' => $q->whereIn('id', $this->recipientIds),
        };

        $q->select('id')->chunkById(1000, function ($users) {
            $now = now();
            MemoRecipient::insertOrIgnore($users->map(fn ($u) => [
                'memo_id' => $this->memoId, 'route_id' => $this->routeId, 'user_id' => $u->id,
                'recipient_type' => 'to', 'delivered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        });
    }
}
