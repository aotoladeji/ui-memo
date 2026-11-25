<?php

namespace Tests\Feature;

use App\Models\{Department, Faculty, Memo, MemoDocument, MemoRecipient, MemoRoute, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemoPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();

        $f = Faculty::create(['name' => 'Science', 'code' => 'SCI']);
        $d = Department::create(['faculty_id' => $f->id, 'name' => 'CSC', 'code' => 'CSC']);
        $u = Unit::create(['department_id' => $d->id, 'name' => 'ICT', 'code' => 'ICT']);

        $this->sender = User::forceCreate([
            'staff_no' => 'ST001',
            'name' => 'Sender',
            'email' => 'sender@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        $this->receiver = User::forceCreate([
            'staff_no' => 'ST002',
            'name' => 'Receiver',
            'email' => 'receiver@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    private function seedMemo(string $subject, string $status, ?User $to = null): Memo
    {
        $memo = Memo::create([
            'uuid' => (string) \Str::uuid(),
            'ref_no' => 'UI/ICT/2026/' . \Str::random(8),
            'subject' => $subject,
            'created_by' => $this->sender->id,
            'origin_unit_id' => $this->sender->unit_id,
            'routing_type' => 'peer',
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);

        MemoDocument::create([
            'memo_id' => $memo->id,
            'kind' => 'template',
            'mime' => 'application/json',
            'page_count' => 1,
            'template_data' => ['to' => 'Receiver', 'date' => '2026-06-25', 'body' => 'Body'],
        ]);

        $route = MemoRoute::create([
            'memo_id' => $memo->id,
            'depth' => 0,
            'path' => '',
            'kind' => 'origin',
            'from_user_id' => $this->sender->id,
        ]);
        $route->update(['path' => "/{$route->id}/"]);

        MemoRecipient::create([
            'memo_id' => $memo->id,
            'route_id' => $route->id,
            'user_id' => $this->sender->id,
            'recipient_type' => 'author',
        ]);

        if ($to) {
            MemoRecipient::create([
                'memo_id' => $memo->id,
                'route_id' => $route->id,
                'user_id' => $to->id,
                'recipient_type' => 'to',
            ]);
        }

        return $memo;
    }

    public function test_pagination_page_boundaries_max_per_page_and_empty_results(): void
    {
        // Empty boxes initially
        Sanctum::actingAs($this->receiver);
        $empty = $this->getJson('/api/v1/memos?box=inbox')->assertOk();
        $this->assertSame([], $empty->json('data'));
        $this->assertSame(0, $empty->json('meta.total'));
        $this->assertSame(1, $empty->json('meta.last_page'));
        $this->assertSame(20, $empty->json('meta.per_page'));

        // Seed 12 sent memos (received by receiver) and 7 drafts
        for ($i = 1; $i <= 12; $i++) {
            $this->seedMemo("Sent Memo {$i}", 'sent', $this->receiver);
        }
        for ($i = 1; $i <= 7; $i++) {
            $this->seedMemo("Draft Memo {$i}", 'draft');
        }

        // Inbox pages with per_page=5 -> 5, 5, 2
        Sanctum::actingAs($this->receiver);
        $p1 = $this->getJson('/api/v1/memos?box=inbox&page=1&per_page=5')->assertOk();
        $this->assertCount(5, $p1->json('data'));
        $this->assertSame(12, $p1->json('meta.total'));
        $this->assertSame(3, $p1->json('meta.last_page'));
        $this->assertSame('Sent Memo 12', $p1->json('data.0.subject'));

        $p3 = $this->getJson('/api/v1/memos?box=inbox&page=3&per_page=5')->assertOk();
        $this->assertCount(2, $p3->json('data'));
        $this->assertSame('Sent Memo 2', $p3->json('data.0.subject'));
        $this->assertSame('Sent Memo 1', $p3->json('data.1.subject'));

        // Out-of-bounds page returns empty data with accurate meta
        $p4 = $this->getJson('/api/v1/memos?box=inbox&page=4&per_page=5')->assertOk();
        $this->assertSame([], $p4->json('data'));
        $this->assertSame(12, $p4->json('meta.total'));

        // Sent and drafts pagination
        Sanctum::actingAs($this->sender);
        $sentP2 = $this->getJson('/api/v1/memos?box=sent&page=2&per_page=10')->assertOk();
        $this->assertCount(2, $sentP2->json('data'));
        $this->assertSame(12, $sentP2->json('meta.total'));

        $draftsP2 = $this->getJson('/api/v1/memos?box=drafts&page=2&per_page=5')->assertOk();
        $this->assertCount(2, $draftsP2->json('data'));
        $this->assertSame(7, $draftsP2->json('meta.total'));

        // Oversized per_page is clamped to 100; invalid per_page=0 is rejected
        $clamped = $this->getJson('/api/v1/memos?box=sent&per_page=500')->assertOk();
        $this->assertSame(100, $clamped->json('meta.per_page'));
        $this->getJson('/api/v1/memos?box=sent&per_page=0')->assertStatus(422);
    }

    public function test_unread_state_is_preserved_accurately_across_pages(): void
    {
        $memos = [];
        for ($i = 1; $i <= 6; $i++) {
            $memos[$i] = $this->seedMemo("Inbox Item {$i}", 'sent', $this->receiver);
        }

        // Page 1 has memos 6,5,4; Page 2 has memos 3,2,1.
        // Receiver reads memo #6 (on page 1) and memo #2 (on page 2).
        Sanctum::actingAs($this->receiver);
        $this->getJson("/api/v1/memos/{$memos[6]->id}")->assertOk();
        $this->getJson("/api/v1/memos/{$memos[2]->id}")->assertOk();

        $page1 = $this->getJson('/api/v1/memos?box=inbox&page=1&per_page=3')->assertOk();
        $this->assertFalse($page1->json('data.0.unread')); // Memo 6
        $this->assertTrue($page1->json('data.1.unread'));  // Memo 5
        $this->assertTrue($page1->json('data.2.unread'));  // Memo 4

        $page2 = $this->getJson('/api/v1/memos?box=inbox&page=2&per_page=3')->assertOk();
        $this->assertTrue($page2->json('data.0.unread'));  // Memo 3
        $this->assertFalse($page2->json('data.1.unread')); // Memo 2
        $this->assertTrue($page2->json('data.2.unread'));  // Memo 1
    }
}
