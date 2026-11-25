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

    public function test_visible_hidden_and_sibling_branch_minutes_and_replies(): void
    {
        $f = Faculty::first();
        $d = Department::first();
        $u = Unit::first();

        $staffA = User::create([
            'staff_no' => 'ST010',
            'name' => 'Staff Branch A',
            'email' => 'sta@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Officer A',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        $staffB = User::create([
            'staff_no' => 'ST011',
            'name' => 'Staff Branch B',
            'email' => 'stb@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Officer B',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        // Executive author creates an executive minute on origin
        Sanctum::actingAs($this->recipient);
        $execMinuteId = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.1,
            'y' => 0.1,
            'body' => 'Executive directive',
            'classification' => 'executive',
        ])->assertCreated()->json('id');

        Sanctum::actingAs($this->author);
        $execReplyId = $this->postJson("/api/v1/minutes/{$execMinuteId}/replies", [
            'body' => 'Reply to executive minute',
        ])->assertCreated()->json('id');

        // Standard author creates a standard minute on origin
        $stdMinuteId = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.2,
            'y' => 0.2,
            'body' => 'Standard minute',
            'classification' => 'standard',
        ])->assertCreated()->json('id');

        // Author forwards to Staff A hiding executive items (hopA) and to Staff B without mask (hopB)
        $hopAId = $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$staffA->id],
            'include_annotations' => true,
            'note_mode' => 'all',
            'hidden_classifications' => ['executive'],
            'rules' => [],
        ])->assertCreated()->json('forward_id');

        $hopBId = $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$staffB->id],
            'include_annotations' => true,
            'note_mode' => 'all',
            'hidden_classifications' => [],
            'rules' => [],
        ])->assertCreated()->json('forward_id');

        // Staff A creates a minute on hopA and replies to standard minute on hopA
        Sanctum::actingAs($staffA);
        $branchAMinuteId = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.3,
            'y' => 0.3,
            'body' => 'Branch A minute',
            'classification' => 'standard',
            'route_id' => $hopAId,
        ])->assertCreated()->json('id');

        $branchAReplyId = $this->postJson("/api/v1/minutes/{$stdMinuteId}/replies", [
            'body' => 'Branch A reply to standard minute',
            'route_id' => $hopAId,
        ])->assertCreated()->json('id');

        // Staff B creates a minute and reply on hopB
        Sanctum::actingAs($staffB);
        $branchBMinuteId = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.4,
            'y' => 0.4,
            'body' => 'Branch B minute',
            'classification' => 'standard',
            'route_id' => $hopBId,
        ])->assertCreated()->json('id');

        $branchBReplyId = $this->postJson("/api/v1/minutes/{$stdMinuteId}/replies", [
            'body' => 'Branch B reply to standard minute',
            'route_id' => $hopBId,
        ])->assertCreated()->json('id');

        // Staff A sees standard minute + own Branch A minute, NOT executive minute, NOT Branch B minute, NOT Branch B reply
        Sanctum::actingAs($staffA);
        $detailA = $this->getJson("/api/v1/memos/{$this->memo->id}")->assertOk();
        $aMinuteIds = collect($detailA->json('minutes'))->pluck('id')->all();
        $aReplyIds = collect($detailA->json('minuteReplies'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$stdMinuteId, $branchAMinuteId], $aMinuteIds);
        $this->assertEqualsCanonicalizing([$branchAReplyId], $aReplyIds);
        $this->assertArrayNotHasKey((string) $this->recipient->id, $detailA->json('authors'));
        $this->assertArrayNotHasKey((string) $staffB->id, $detailA->json('authors'));

        // Staff B sees executive minute, standard minute, own Branch B minute, and Branch B reply, NOT Branch A minute/reply
        Sanctum::actingAs($staffB);
        $detailB = $this->getJson("/api/v1/memos/{$this->memo->id}")->assertOk();
        $bMinuteIds = collect($detailB->json('minutes'))->pluck('id')->all();
        $bReplyIds = collect($detailB->json('minuteReplies'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$execMinuteId, $stdMinuteId, $branchBMinuteId], $bMinuteIds);
        $this->assertEqualsCanonicalizing([$execReplyId, $branchBReplyId], $bReplyIds);
        $this->assertArrayNotHasKey((string) $staffA->id, $detailB->json('authors'));

        // Upstream author sees all downstream minutes and replies from both branches
        Sanctum::actingAs($this->author);
        $detailOrigin = $this->getJson("/api/v1/memos/{$this->memo->id}")->assertOk();
        $this->assertEqualsCanonicalizing(
            [$execMinuteId, $stdMinuteId, $branchAMinuteId, $branchBMinuteId],
            collect($detailOrigin->json('minutes'))->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$execReplyId, $branchAReplyId, $branchBReplyId],
            collect($detailOrigin->json('minuteReplies'))->pluck('id')->all()
        );
    }

    public function test_unauthorized_minute_reply_edit_delete_and_route_selection(): void
    {
        $u = Unit::first();
        $staffA = User::create([
            'staff_no' => 'ST020',
            'name' => 'Branch A',
            'email' => 'st20@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_active' => true,
        ]);
        $staffB = User::create([
            'staff_no' => 'ST021',
            'name' => 'Branch B',
            'email' => 'st21@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        // Executive minute on origin
        Sanctum::actingAs($this->recipient);
        $execMinuteId = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.1,
            'y' => 0.1,
            'body' => 'Confidential exec minute',
            'classification' => 'executive',
        ])->assertCreated()->json('id');

        Sanctum::actingAs($this->author);
        $hopAId = $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$staffA->id],
            'include_annotations' => true,
            'note_mode' => 'all',
            'hidden_classifications' => ['executive'],
            'rules' => [],
        ])->assertCreated()->json('forward_id');

        $hopBId = $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$staffB->id],
            'include_annotations' => true,
            'note_mode' => 'all',
            'hidden_classifications' => [],
            'rules' => [],
        ])->assertCreated()->json('forward_id');

        // Staff A creates a minute on hopA
        Sanctum::actingAs($staffA);
        $branchAMinuteId = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.3,
            'y' => 0.3,
            'body' => 'Branch A private branch minute',
            'route_id' => $hopAId,
        ])->assertCreated()->json('id');

        // Staff A cannot reply to the hidden executive minute (404)
        $this->postJson("/api/v1/minutes/{$execMinuteId}/replies", [
            'body' => 'Trying to reply to masked minute',
            'route_id' => $hopAId,
        ])->assertNotFound();

        // Staff A cannot spoof hopBId (belongs to Staff B) on show, store, patch, delete, reply
        $this->getJson("/api/v1/memos/{$this->memo->id}?route_id={$hopBId}")->assertNotFound();
        $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.5,
            'y' => 0.5,
            'body' => 'Spoofed route',
            'route_id' => $hopBId,
        ])->assertNotFound();
        $this->patchJson("/api/v1/minutes/{$branchAMinuteId}", [
            'body' => 'Edited via spoofed route',
            'route_id' => $hopBId,
        ])->assertNotFound();
        $this->deleteJson("/api/v1/minutes/{$branchAMinuteId}?route_id={$hopBId}")->assertNotFound();

        // Staff B (sibling branch) cannot reply to, edit, or delete Staff A's minute (404)
        Sanctum::actingAs($staffB);
        $this->postJson("/api/v1/minutes/{$branchAMinuteId}/replies", [
            'body' => 'Cross-branch reply',
            'route_id' => $hopBId,
        ])->assertNotFound();
        $this->patchJson("/api/v1/minutes/{$branchAMinuteId}", [
            'body' => 'Cross-branch edit',
            'route_id' => $hopBId,
        ])->assertNotFound();
        $this->deleteJson("/api/v1/minutes/{$branchAMinuteId}?route_id={$hopBId}")->assertNotFound();

        // Outsider cannot edit or delete Staff A's minute (404, does not leak existence with 403)
        Sanctum::actingAs($this->outsider);
        $this->patchJson("/api/v1/minutes/{$branchAMinuteId}", ['body' => 'Outsider'])->assertNotFound();
        $this->deleteJson("/api/v1/minutes/{$branchAMinuteId}")->assertNotFound();

        // A route from a different memo cannot be selected on this memo.
        $otherMemo = Memo::create([
            'uuid' => (string) \Str::uuid(),
            'ref_no' => 'MEMO/2026/OTHER',
            'subject' => 'Other Memo',
            'created_by' => $staffA->id,
            'routing_type' => 'peer',
            'status' => 'sent',
        ]);
        $otherRoute = MemoRoute::create([
            'memo_id' => $otherMemo->id,
            'depth' => 0,
            'path' => '',
            'kind' => 'origin',
            'from_user_id' => $staffA->id,
        ]);
        $otherRoute->update(['path' => "/{$otherRoute->id}/"]);
        MemoRecipient::create([
            'memo_id' => $otherMemo->id,
            'route_id' => $otherRoute->id,
            'user_id' => $staffA->id,
            'recipient_type' => 'author',
        ]);

        Sanctum::actingAs($staffA);
        $this->getJson("/api/v1/memos/{$this->memo->id}?route_id={$otherRoute->id}")->assertNotFound();
        $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.4,
            'y' => 0.4,
            'body' => 'Wrong memo route',
            'route_id' => $otherRoute->id,
        ])->assertNotFound();

        // A mismatched recipient row must not make a route from another memo valid.
        $unownedRoute = MemoRoute::create([
            'memo_id' => $otherMemo->id,
            'depth' => 0,
            'path' => '',
            'kind' => 'origin',
            'from_user_id' => $staffA->id,
        ]);
        $unownedRoute->update(['path' => "/{$unownedRoute->id}/"]);
        MemoRecipient::create([
            'memo_id' => $this->memo->id,
            'route_id' => $unownedRoute->id,
            'user_id' => $staffA->id,
            'recipient_type' => 'to',
        ]);
        $this->getJson("/api/v1/memos/{$this->memo->id}?route_id={$unownedRoute->id}")->assertNotFound();
        $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.4,
            'y' => 0.4,
            'body' => 'Mismatched memo route',
            'route_id' => $unownedRoute->id,
        ])->assertNotFound();
    }

    public function test_same_author_cannot_see_or_change_minute_across_sibling_routes(): void
    {
        $staff = User::create([
            'staff_no' => 'ST030',
            'name' => 'Same User on Both Branches',
            'email' => 'st30@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => Unit::first()->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->author);
        $routeA = $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$staff->id],
            'include_annotations' => true,
            'note_mode' => 'all',
            'rules' => [],
        ])->assertCreated()->json('forward_id');
        $routeB = $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$staff->id],
            'include_annotations' => true,
            'note_mode' => 'all',
            'rules' => [],
        ])->assertCreated()->json('forward_id');

        Sanctum::actingAs($staff);
        $minuteId = $this->postJson("/api/v1/memos/{$this->memo->id}/minutes", [
            'page_number' => 1,
            'x' => 0.3,
            'y' => 0.4,
            'body' => 'Only branch A can see this',
            'route_id' => $routeA,
        ])->assertCreated()->json('id');
        $this->postJson("/api/v1/minutes/{$minuteId}/replies", [
            'body' => 'Reply belongs to branch A',
            'route_id' => $routeA,
        ])->assertCreated();

        $this->getJson("/api/v1/memos/{$this->memo->id}?route_id={$routeA}")
            ->assertOk()
            ->assertJsonCount(1, 'minutes')
            ->assertJsonCount(1, 'minuteReplies');

        $siblingDetail = $this->getJson("/api/v1/memos/{$this->memo->id}?route_id={$routeB}")->assertOk();
        $this->assertSame([], $siblingDetail->json('minutes'));
        $this->assertSame([], $siblingDetail->json('minuteReplies'));

        $this->postJson("/api/v1/minutes/{$minuteId}/replies", [
            'body' => 'Cross-branch reply',
            'route_id' => $routeB,
        ])->assertNotFound();
        $this->patchJson("/api/v1/minutes/{$minuteId}", [
            'body' => 'Cross-branch edit',
            'route_id' => $routeB,
        ])->assertNotFound();
        $this->deleteJson("/api/v1/minutes/{$minuteId}?route_id={$routeB}")->assertNotFound();
    }
}
