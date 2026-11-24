<?php

namespace Tests\Feature;

use App\Models\{Department, Faculty, Memo, MemoDocument, MemoRecipient, MemoRoute, StickyNote, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $recipient;
    private User $assignee;
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

        $this->assignee = User::create([
            'staff_no' => 'ST002',
            'name' => 'Chidi Okonkwo',
            'email' => 'st002@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Technical Officer',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_executive' => false,
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
            'ref_no' => 'MEMO/2026/NOTE01',
            'subject' => 'Note Feature Test',
            'created_by' => $this->author->id,
            'routing_type' => 'peer',
            'status' => 'sent',
        ]);

        MemoDocument::create([
            'memo_id' => $this->memo->id,
            'kind' => 'file',
            'storage_path' => 'memos/test.pdf',
            'mime' => 'application/pdf',
            'sha256' => str_repeat('b', 64),
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

        MemoRecipient::create([
            'memo_id' => $this->memo->id,
            'route_id' => $this->originRoute->id,
            'user_id' => $this->assignee->id,
            'recipient_type' => 'cc',
        ]);
    }

    public function test_create_and_update_note(): void
    {
        Sanctum::actingAs($this->author);

        $createRes = $this->postJson("/api/v1/memos/{$this->memo->id}/notes", [
            'page_number' => 1,
            'x' => 0.2,
            'y' => 0.3,
            'body' => 'Initial note text',
            'color' => '#FEF08A',
            'requires_action' => true,
            'assigned_to' => $this->assignee->id,
        ]);
        $createRes->assertStatus(201);
        $noteId = $createRes->json('id');
        $this->assertNotNull($noteId);
        $this->assertEquals('Initial note text', $createRes->json('body'));
        $this->assertTrue((bool) $createRes->json('requires_action'));
        $this->assertEquals($this->assignee->id, $createRes->json('assigned_to'));

        // Non-author cannot edit layout/body
        Sanctum::actingAs($this->recipient);
        $this->patchJson("/api/v1/notes/{$noteId}", [
            'body' => 'Hijacked note',
        ])->assertStatus(403);

        // Author updates note
        Sanctum::actingAs($this->author);
        $updateRes = $this->patchJson("/api/v1/notes/{$noteId}", [
            'body' => 'Updated note text',
            'x' => 0.4,
            'y' => 0.5,
        ]);
        $updateRes->assertOk();
        $this->assertEquals('Updated note text', $updateRes->json('body'));
        $this->assertEquals(0.4, $updateRes->json('x'));
        $this->assertEquals(0.5, $updateRes->json('y'));
    }

    public function test_resolve_note_by_assignee_and_author(): void
    {
        Sanctum::actingAs($this->author);
        $createRes = $this->postJson("/api/v1/memos/{$this->memo->id}/notes", [
            'page_number' => 1,
            'x' => 0.2,
            'y' => 0.3,
            'body' => 'Action required here',
            'requires_action' => true,
            'assigned_to' => $this->assignee->id,
        ])->assertStatus(201);
        $noteId = $createRes->json('id');

        // Third party cannot resolve
        Sanctum::actingAs($this->recipient);
        $this->patchJson("/api/v1/notes/{$noteId}", [
            'status' => 'resolved',
        ])->assertStatus(403);

        // Assignee can resolve
        Sanctum::actingAs($this->assignee);
        $res = $this->patchJson("/api/v1/notes/{$noteId}", [
            'status' => 'resolved',
        ])->assertOk();
        $this->assertEquals('resolved', $res->json('status'));
        $this->assertEquals($this->assignee->id, $res->json('resolved_by'));
        $this->assertNotNull($res->json('resolved_at'));

        // Author can re-open
        Sanctum::actingAs($this->author);
        $res = $this->patchJson("/api/v1/notes/{$noteId}", [
            'status' => 'open',
        ])->assertOk();
        $this->assertEquals('open', $res->json('status'));
    }

    public function test_reply_to_note(): void
    {
        Sanctum::actingAs($this->author);
        $createRes = $this->postJson("/api/v1/memos/{$this->memo->id}/notes", [
            'page_number' => 1,
            'x' => 0.2,
            'y' => 0.3,
            'body' => 'Please provide status update.',
        ])->assertStatus(201);
        $noteId = $createRes->json('id');

        // Recipient replies
        Sanctum::actingAs($this->recipient);
        $replyRes = $this->postJson("/api/v1/notes/{$noteId}/replies", [
            'body' => 'Status is in progress.',
        ]);
        $replyRes->assertStatus(201);
        $this->assertEquals('Status is in progress.', $replyRes->json('body'));
        $this->assertEquals($noteId, $replyRes->json('note_id'));
        $this->assertEquals($this->recipient->id, $replyRes->json('author_id'));

        // Outsider cannot reply
        Sanctum::actingAs($this->outsider);
        $this->postJson("/api/v1/notes/{$noteId}/replies", [
            'body' => 'Should fail',
        ])->assertStatus(404);
    }

    public function test_delete_note(): void
    {
        Sanctum::actingAs($this->author);
        $createRes = $this->postJson("/api/v1/memos/{$this->memo->id}/notes", [
            'page_number' => 1,
            'x' => 0.2,
            'y' => 0.3,
            'body' => 'Temporary note to be deleted.',
        ])->assertStatus(201);
        $noteId = $createRes->json('id');

        // Non-author cannot delete
        Sanctum::actingAs($this->recipient);
        $this->deleteJson("/api/v1/notes/{$noteId}")->assertStatus(403);

        // Author deletes note
        Sanctum::actingAs($this->author);
        $this->deleteJson("/api/v1/notes/{$noteId}")->assertNoContent();

        // Note is gone: patch or delete should 404
        $this->patchJson("/api/v1/notes/{$noteId}", ['body' => 'Should fail'])->assertStatus(404);
    }
}
