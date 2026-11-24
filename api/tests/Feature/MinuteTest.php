<?php

namespace Tests\Feature;

use App\Models\{Department, Faculty, Memo, MemoDocument, MemoRecipient, MemoRoute, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MinuteTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $recipient;
    private User $outsider;
    private Memo $memo;
    private MemoRoute $originRoute;

    protected function setUp(): void
    {
        parent::setUp();

        $f = Faculty::create(['name' => 'Faculty of Science', 'code' => 'SCI']);
        $d = Department::create(['faculty_id' => $f->id, 'name' => 'Computer Science', 'code' => 'CSC']);
        $u = Unit::create(['department_id' => $d->id, 'name' => 'ICT Unit', 'code' => 'ICT']);

        $this->author = User::create([
            'staff_no' => 'ST001',
            'name' => 'Ada Okafor',
            'email' => 'st001@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Systems Officer',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_executive' => false,
            'is_active' => true,
        ]);

        $this->recipient = User::create([
            'staff_no' => 'HD001',
            'name' => 'Dr. Babatunde',
            'email' => 'hd001@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Unit Head',
            'unit_id' => $u->id,
            'role' => 'unit_head',
            'is_executive' => true,
            'is_active' => true,
        ]);

        $this->outsider = User::create([
            'staff_no' => 'OUT01',
            'name' => 'Outsider User',
            'email' => 'out@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Visitor',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_executive' => false,
            'is_active' => true,
        ]);

        $this->memo = Memo::create([
            'uuid' => (string) \Str::uuid(),
            'ref_no' => 'MEMO/2026/001',
            'subject' => 'Minute Test Memo',
            'created_by' => $this->author->id,
            'routing_type' => 'peer',
            'status' => 'sent',
        ]);

        MemoDocument::create([
            'memo_id' => $this->memo->id,
            'kind' => 'file',
            'storage_path' => 'memos/test.pdf',
            'mime' => 'application/pdf',
            'sha256' => str_repeat('a', 64),
            'page_count' => 2,
        ]);

        $this->originRoute = MemoRoute::create([
            'memo_id' => $this->memo->id,
            'parent_route_id' => null,
            'depth' => 0,
            'path' => '',
            'kind' => 'origin',
            'from_user_id' => $this->author->id,
        ]);
        $this->originRoute->update(['path' => '/' . $this->originRoute->id . '/']);

        MemoRecipient::create([
            'memo_id' => $this->memo->id,
            'route_id' => $this->originRoute->id,
            'user_id' => $this->author->id,
            'recipient_type' => 'author',
        ]);

        MemoRecipient::create([
            'memo_id' => $this->memo->id,
            'route_id' => $this->originRoute->id,
            'user_id' => $this->recipient->id,
            'recipient_type' => 'to',
        ]);
    }

    public function test_creating_updating_and_deleting_minute(): void
    {
        Sanctum::actingAs($this->recipient);

        // Outsider cannot create minute
        Sanctum::actingAs($this->outsider);
        $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.25,
            'y' => 0.50,
            'body' => 'Please treat urgently.',
        ])->assertStatus(404);

        // Recipient can create minute
        Sanctum::actingAs($this->recipient);
        $createRes = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.25,
            'y' => 0.50,
            'body' => 'Please treat urgently.',
            'color' => '#E0E7FF',
            'classification' => 'standard',
        ]);
        $createRes->assertStatus(201);
        $minuteId = $createRes->json('id');
        $this->assertNotNull($minuteId);
        $this->assertEquals('Please treat urgently.', $createRes->json('body'));
        $this->assertEquals(1, $createRes->json('page_number'));
        $this->assertEquals(0.25, $createRes->json('x'));
        $this->assertEquals(0.50, $createRes->json('y'));

        // Non-author cannot update minute
        Sanctum::actingAs($this->author);
        $this->patchJson("/api/v1/minutes/{$minuteId}", [
            'body' => 'Tampered body',
        ])->assertStatus(403);

        // Author updates minute position and body
        Sanctum::actingAs($this->recipient);
        $patchRes = $this->patchJson("/api/v1/minutes/{$minuteId}", [
            'page_number' => 2,
            'x' => 0.30,
            'y' => 0.60,
            'body' => 'Updated: please treat today.',
        ]);
        $patchRes->assertOk();
        $this->assertEquals('Updated: please treat today.', $patchRes->json('body'));
        $this->assertEquals(2, $patchRes->json('page_number'));
        $this->assertEquals(0.30, $patchRes->json('x'));
        $this->assertEquals(0.60, $patchRes->json('y'));

        // Non-author cannot delete minute
        Sanctum::actingAs($this->author);
        $this->deleteJson("/api/v1/minutes/{$minuteId}")->assertStatus(403);

        // Author deletes minute
        Sanctum::actingAs($this->recipient);
        $this->deleteJson("/api/v1/minutes/{$minuteId}")->assertNoContent();

        // Check it is gone
        $detail = $this->getJson("/api/v1/memos/{$this->memo->id}")->assertOk();
        $this->assertEmpty($detail->json('minutes'));
    }

    public function test_replying_to_minute(): void
    {
        Sanctum::actingAs($this->recipient);
        $createRes = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.1,
            'y' => 0.2,
            'body' => 'Initial minute.',
        ])->assertStatus(201);
        $minuteId = $createRes->json('id');

        // Author of memo replies to minute
        Sanctum::actingAs($this->author);
        $replyRes = $this->postJson("/api/v1/minutes/{$minuteId}/replies", [
            'body' => 'Noted and working on it.',
        ]);
        $replyRes->assertStatus(201);
        $this->assertEquals('Noted and working on it.', $replyRes->json('body'));
        $this->assertEquals($minuteId, $replyRes->json('minute_id'));
        $this->assertEquals($this->author->id, $replyRes->json('author_id'));

        // Outsider cannot reply
        Sanctum::actingAs($this->outsider);
        $this->postJson("/api/v1/minutes/{$minuteId}/replies", [
            'body' => 'Outsider comment',
        ])->assertStatus(404);
    }

    public function test_detail_view_serializes_minutes_and_minute_replies(): void
    {
        Sanctum::actingAs($this->recipient);
        $createRes = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.15,
            'y' => 0.35,
            'body' => 'Approval requested.',
        ])->assertStatus(201);
        $minuteId = $createRes->json('id');

        Sanctum::actingAs($this->author);
        $this->postJson("/api/v1/minutes/{$minuteId}/replies", [
            'body' => 'Approved.',
        ])->assertStatus(201);

        // Fetch memo detail view
        $detail = $this->getJson("/api/v1/memos/{$this->memo->id}")->assertOk();

        $minutes = $detail->json('minutes');
        $this->assertIsArray($minutes);
        $this->assertCount(1, $minutes);
        $this->assertEquals($minuteId, $minutes[0]['id']);
        $this->assertEquals('Approval requested.', $minutes[0]['body']);
        $this->assertEquals($this->recipient->id, $minutes[0]['author_id']);

        $minuteReplies = $detail->json('minuteReplies');
        $this->assertIsArray($minuteReplies);
        $this->assertCount(1, $minuteReplies);
        $this->assertEquals($minuteId, $minuteReplies[0]['minute_id']);
        $this->assertEquals('Approved.', $minuteReplies[0]['body']);
        $this->assertEquals($this->author->id, $minuteReplies[0]['author_id']);

        $authors = $detail->json('authors');
        $this->assertArrayHasKey((string) $this->recipient->id, $authors);
        $this->assertArrayHasKey((string) $this->author->id, $authors);
    }
}
