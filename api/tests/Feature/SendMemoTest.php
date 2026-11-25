<?php

namespace Tests\Feature;

use App\Jobs\FanOutRecipients;
use App\Models\{AuditLog, Department, Faculty, Memo, MemoDocument, MemoRecipient, MemoRoute, MemoSendOutbox, Unit, User};
use App\Services\SendOutboxProcessor;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class SendMemoTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;
    private User $author;
    private User $peerA;
    private User $peerB;

    protected function setUp(): void
    {
        parent::setUp();

        $faculty = Faculty::create(['name' => 'Science', 'code' => 'SCI']);
        $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Computer Science', 'code' => 'CSC']);
        $this->unit = Unit::create(['department_id' => $dept->id, 'name' => 'ICT Unit', 'code' => 'ICT']);

        $mk = fn (string $no, string $name, string $role = 'staff', bool $broadcast = false) => User::forceCreate([
            'staff_no' => $no,
            'name' => $name,
            'email' => strtolower($no) . '@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $this->unit->id,
            'role' => $role,
            'can_send_global_broadcast' => $broadcast,
            'is_active' => true,
        ]);

        $this->author = $mk('ST001', 'Author User', 'staff', true);
        $this->peerA = $mk('ST002', 'Peer A');
        $this->peerB = $mk('ST003', 'Peer B');
    }

    private function createDraft(string $routing = 'peer'): Memo
    {
        Sanctum::actingAs($this->author);
        $id = $this->postJson('/api/v1/memos', [
            'kind' => 'template',
            'subject' => 'Send Safety Memo',
            'routing_type' => $routing,
            'template' => [
                'to' => 'All Concerned',
                'date' => '2026-06-25',
                'body' => 'Body',
            ],
        ])->assertCreated()->json('id');

        return Memo::findOrFail($id);
    }

    public function test_repeated_send_is_rejected_without_duplicate_audit_outbox_or_recipients(): void
    {
        $memo = $this->createDraft('peer');

        Sanctum::actingAs($this->author);
        $this->postJson("/api/v1/memos/{$memo->id}/send", [
            'recipient_ids' => [$this->peerA->id, $this->peerB->id, $this->peerA->id],
        ])->assertOk();

        // Repeated / concurrent second send attempt must fail with 409 Conflict
        $this->postJson("/api/v1/memos/{$memo->id}/send", [
            'recipient_ids' => [$this->peerA->id, $this->peerB->id],
        ])->assertStatus(409);

        $this->assertEquals(1, AuditLog::where('memo_id', $memo->id)->where('action', 'memo.sent')->count());
        $this->assertEquals(1, MemoSendOutbox::where('memo_id', $memo->id)->count());
        $this->assertEquals('completed', MemoSendOutbox::where('memo_id', $memo->id)->value('status'));

        // Author + peerA + peerB = 3 unique recipient rows
        $this->assertEquals(3, MemoRecipient::where('memo_id', $memo->id)->count());
        $this->assertEquals(1, MemoRecipient::where('memo_id', $memo->id)->where('user_id', $this->peerA->id)->count());
        $this->assertEquals(1, MemoRecipient::where('memo_id', $memo->id)->where('user_id', $this->peerB->id)->count());
    }

    public function test_rollback_leaves_draft_unchanged_without_audit_outbox_or_dispatch(): void
    {
        Queue::fake();
        $memo = $this->createDraft('internal_unit');

        AuditLog::creating(function (AuditLog $log) {
            if ($log->action === 'memo.sent') {
                throw new RuntimeException('Simulated audit failure');
            }
        });

        try {
            Sanctum::actingAs($this->author);
            $this->postJson("/api/v1/memos/{$memo->id}/send", [
                'recipient_ids' => [],
            ])->assertStatus(500);
        } finally {
            AuditLog::flushEventListeners();
        }

        $memo->refresh();
        $this->assertSame('draft', $memo->status);
        $this->assertNull($memo->sent_at);
        $this->assertEquals(0, AuditLog::where('memo_id', $memo->id)->where('action', 'memo.sent')->count());
        $this->assertEquals(0, MemoSendOutbox::where('memo_id', $memo->id)->count());
        $this->assertEquals(0, MemoRecipient::where('memo_id', $memo->id)->where('recipient_type', 'to')->count());
        Queue::assertNothingPushed();
    }

    public function test_queue_dispatch_failure_after_commit_keeps_outbox_and_retry_delivers_once(): void
    {
        $memo = $this->createDraft('internal_unit');

        $realDispatcher = app(Dispatcher::class);
        $failingDispatcher = new class($realDispatcher) implements Dispatcher {
            public function __construct(private Dispatcher $inner) {}
            public function dispatch($command) { throw new RuntimeException('Queue connection failed'); }
            public function dispatchSync($command, $handler = null) { return $this->inner->dispatchSync($command, $handler); }
            public function dispatchNow($command, $handler = null) { return $this->inner->dispatchNow($command, $handler); }
            public function hasCommandHandler($command) { return $this->inner->hasCommandHandler($command); }
            public function getCommandHandler($command) { return $this->inner->getCommandHandler($command); }
            public function pipeThrough(array $pipes) { return $this->inner->pipeThrough($pipes); }
            public function map(array $map) { return $this->inner->map($map); }
        };
        app()->instance(Dispatcher::class, $failingDispatcher);

        try {
            Sanctum::actingAs($this->author);
            $this->postJson("/api/v1/memos/{$memo->id}/send", [
                'recipient_ids' => [],
            ])->assertOk();
        } finally {
            app()->instance(Dispatcher::class, $realDispatcher);
        }

        // Memo and audit log committed; outbox persisted as pending with last_error; no recipients delivered yet
        $memo->refresh();
        $this->assertSame('sent', $memo->status);
        $this->assertEquals(1, AuditLog::where('memo_id', $memo->id)->where('action', 'memo.sent')->count());

        $outbox = MemoSendOutbox::where('memo_id', $memo->id)->firstOrFail();
        $this->assertSame('pending', $outbox->status);
        $this->assertNull($outbox->dispatched_at);
        $this->assertStringContainsString('Queue connection failed', (string) $outbox->last_error);
        $this->assertEquals(0, MemoRecipient::where('memo_id', $memo->id)->where('recipient_type', 'to')->count());

        // Retry via artisan command delivers all unit recipients
        $this->artisan('memos:retry-outbox')->assertExitCode(0);

        $outbox->refresh();
        $this->assertSame('completed', $outbox->status);
        $this->assertNull($outbox->last_error);
        $this->assertEquals(1, $outbox->attempts);
        $this->assertEquals(2, MemoRecipient::where('memo_id', $memo->id)->where('recipient_type', 'to')->count());

        // Repeating retry or re-running job is idempotent (no duplicate recipients)
        $this->artisan('memos:retry-outbox')->assertExitCode(0);
        (new FanOutRecipients($memo->id, $outbox->route_id, 'internal_unit', [], $this->author->id, $outbox->id))->handle();

        $this->assertEquals(2, MemoRecipient::where('memo_id', $memo->id)->where('recipient_type', 'to')->count());
        $this->assertEquals(1, $outbox->fresh()->attempts);
    }

    public function test_fan_out_retry_after_partial_failure_does_not_duplicate_recipients(): void
    {
        $memo = $this->createDraft('internal_unit');
        $route = MemoRoute::where(['memo_id' => $memo->id, 'kind' => 'origin'])->firstOrFail();

        $memo->update(['status' => 'sent', 'sent_at' => now()]);
        $outbox = MemoSendOutbox::create([
            'memo_id' => $memo->id,
            'route_id' => $route->id,
            'sender_id' => $this->author->id,
            'routing_type' => 'internal_unit',
            'recipient_ids' => [],
            'status' => 'failed',
            'attempts' => 1,
            'last_error' => 'Transient worker timeout',
        ]);

        // Simulate peerA already inserted before failure on earlier attempt
        MemoRecipient::create([
            'memo_id' => $memo->id,
            'route_id' => $route->id,
            'user_id' => $this->peerA->id,
            'recipient_type' => 'to',
        ]);

        app(SendOutboxProcessor::class)->retryPending();

        $outbox->refresh();
        $this->assertSame('completed', $outbox->status);
        $this->assertEquals(2, $outbox->attempts);
        $this->assertEquals(2, MemoRecipient::where('memo_id', $memo->id)->where('recipient_type', 'to')->count());
        $this->assertEquals(1, MemoRecipient::where('memo_id', $memo->id)->where('user_id', $this->peerA->id)->count());
        $this->assertEquals(1, MemoRecipient::where('memo_id', $memo->id)->where('user_id', $this->peerB->id)->count());
    }

    public function test_database_queue_outbox_is_retried_only_after_dispatch_lease_expires(): void
    {
        config(['queue.default' => 'database']);
        $memo = $this->createDraft('internal_unit');

        Sanctum::actingAs($this->author);
        $this->postJson("/api/v1/memos/{$memo->id}/send", ['recipient_ids' => []])->assertOk();

        $outbox = MemoSendOutbox::where('memo_id', $memo->id)->firstOrFail();
        $this->assertSame('pending', $outbox->status);
        $this->assertNotNull($outbox->dispatched_at);
        $this->assertDatabaseCount('jobs', 1);

        app(SendOutboxProcessor::class)->retryPending();
        $this->assertDatabaseCount('jobs', 1);

        $outbox->update(['dispatched_at' => now()->subMinutes(11)]);
        app(SendOutboxProcessor::class)->retryPending();
        $this->assertDatabaseCount('jobs', 2);
        $this->assertTrue($outbox->fresh()->dispatched_at->greaterThan(now()->subMinute()));
    }
}
