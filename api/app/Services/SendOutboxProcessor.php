<?php

namespace App\Services;

use App\Jobs\FanOutRecipients;
use App\Models\MemoSendOutbox;
use Illuminate\Contracts\Bus\Dispatcher;
use Throwable;

/**
 * Dispatches and retries memo recipient fan-out using the transactional outbox table.
 */
class SendOutboxProcessor
{
    private const DISPATCH_TIMEOUT_MINUTES = 10;

    public function __construct(private Dispatcher $dispatcher) {}

    public function dispatch(MemoSendOutbox $outbox): void
    {
        if ($outbox->status === 'completed') {
            return;
        }

        $job = new FanOutRecipients(
            $outbox->memo_id,
            $outbox->route_id,
            $outbox->routing_type,
            $outbox->recipient_ids ?? [],
            $outbox->sender_id,
            $outbox->id,
        );

        try {
            if ($outbox->routing_type === 'peer') {
                $this->dispatcher->dispatchSync($job);
            } else {
                $this->dispatcher->dispatch($job);
                $outbox->refresh();
                if ($outbox->status !== 'completed') {
                    $outbox->update(['dispatched_at' => now(), 'last_error' => null]);
                }
            }
        } catch (Throwable $e) {
            report($e);
            $outbox->refresh();
            if ($outbox->status !== 'completed') {
                $outbox->update([
                    'dispatched_at' => null,
                    'last_error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** Re-dispatch any pending or failed outbox rows whose delivery did not complete. */
    public function retryPending(): int
    {
        $count = 0;
        MemoSendOutbox::whereIn('status', ['pending', 'failed'])
            ->where(function ($query) {
                $query->whereNull('dispatched_at')
                    ->orWhere('dispatched_at', '<=', now()->subMinutes(self::DISPATCH_TIMEOUT_MINUTES));
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count) {
                foreach ($rows as $outbox) {
                    $this->dispatch($outbox);
                    $count++;
                }
            });

        return $count;
    }
}
