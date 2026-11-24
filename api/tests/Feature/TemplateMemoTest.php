<?php

namespace Tests\Feature;

use App\Models\{Department, Faculty, Memo, MemoDocument, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemplateMemoTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $recipient;
    private User $noUnitUser;
    private Department $dept;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $faculty = Faculty::create(['name' => 'Faculty of Science', 'code' => 'SCI']);
        $this->dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Computer Science', 'code' => 'CSC']);
        $this->unit = Unit::create(['department_id' => $this->dept->id, 'name' => 'ICT Unit', 'code' => 'ICT']);

        $this->author = User::create([
            'staff_no' => 'ST001',
            'name' => 'Ada Okafor',
            'email' => 'st001@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Systems Officer',
            'unit_id' => $this->unit->id,
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
            'unit_id' => $this->unit->id,
            'role' => 'unit_head',
            'is_executive' => true,
            'is_active' => true,
        ]);

        $this->noUnitUser = User::create([
            'staff_no' => 'SA001',
            'name' => 'Super Admin',
            'email' => 'sa001@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Super Administrator',
            'unit_id' => null,
            'role' => 'super_admin',
            'is_executive' => true,
            'is_active' => true,
        ]);
    }

    public function test_create_template_memo_presets_account_info_and_ignores_spoofed_fields(): void
    {
        Sanctum::actingAs($this->author);

        $payload = [
            'kind' => 'template',
            'subject' => 'Request for access',
            'routing_type' => 'peer',
            'page_count' => 1,
            'template' => [
                'to' => 'The Deputy Director, MIS',
                'date' => '2026-06-25',
                'body' => 'I am writing to request access.',
                'from_name' => 'Someone Else',
                'from_staff_no' => 'FAKE999',
                'department_name' => 'Fake Dept',
                'signature' => ['name' => 'Someone Else', 'title' => 'Hacker', 'unit' => 'Rogue Unit'],
            ],
        ];

        $response = $this->postJson('/api/v1/memos', $payload);
        $response->assertStatus(201);
        $memoId = $response->json('id');
        $this->assertNotNull($memoId);
        $this->assertNotEmpty($response->json('ref_no'));

        $detail = $this->getJson("/api/v1/memos/{$memoId}")->assertOk();
        $doc = $detail->json('document');

        $this->assertEquals('template', $doc['kind']);
        $this->assertNull($doc['url']);
        $this->assertNotNull($doc['template']);
        $this->assertEquals('Ada Okafor', $doc['template']['from_name']);
        $this->assertEquals('ST001', $doc['template']['from_staff_no']);
        $this->assertEquals('Computer Science', $doc['template']['department_name']);
        $this->assertEquals('Request for access', $doc['template']['subject']);
        $this->assertEquals([
            'name' => 'Ada Okafor',
            'title' => 'Systems Officer',
            'unit' => 'ICT Unit',
        ], $doc['template']['signature']);
    }

    public function test_rejects_template_for_accounts_with_no_unit(): void
    {
        Sanctum::actingAs($this->noUnitUser);

        $payload = [
            'kind' => 'template',
            'subject' => 'Test memo',
            'routing_type' => 'peer',
            'template' => [
                'to' => 'The Deputy Director, MIS',
                'date' => '2026-06-25',
                'body' => 'I am writing to request access.',
            ],
        ];

        $response = $this->postJson('/api/v1/memos', $payload);
        $response->assertStatus(422);
    }

    public function test_requires_to_and_valid_date(): void
    {
        Sanctum::actingAs($this->author);

        // Missing To
        $this->postJson('/api/v1/memos', [
            'kind' => 'template',
            'subject' => 'Test memo',
            'routing_type' => 'peer',
            'template' => [
                'to' => '',
                'date' => '2026-06-25',
                'body' => 'Body text',
            ],
        ])->assertStatus(422);

        // Invalid date format
        $this->postJson('/api/v1/memos', [
            'kind' => 'template',
            'subject' => 'Test memo',
            'routing_type' => 'peer',
            'template' => [
                'to' => 'The Director',
                'date' => 'yesterday',
                'body' => 'Body text',
            ],
        ])->assertStatus(422);
    }

    public function test_author_can_edit_draft_template(): void
    {
        Sanctum::actingAs($this->author);

        $createResponse = $this->postJson('/api/v1/memos', [
            'kind' => 'template',
            'subject' => 'Initial subject',
            'routing_type' => 'peer',
            'template' => [
                'to' => 'The Director',
                'date' => '2026-06-25',
                'body' => 'Initial body',
            ],
        ])->assertStatus(201);

        $memoId = $createResponse->json('id');

        $patchResponse = $this->patchJson("/api/v1/memos/{$memoId}/template", [
            'subject' => 'New subject',
            'page_count' => 1,
            'template' => [
                'to' => 'The Director',
                'date' => '2026-06-25',
                'thru' => ['The Deputy Director'],
                'body' => 'Updated body.',
            ],
        ]);
        $patchResponse->assertOk();

        $detail = $this->getJson("/api/v1/memos/{$memoId}")->assertOk();
        $this->assertEquals('New subject', $detail->json('memo.subject'));
        $this->assertEquals('New subject', $detail->json('document.template.subject'));
        $this->assertEquals(['The Deputy Director'], $detail->json('document.template.thru'));
        $this->assertEquals('Updated body.', $detail->json('document.template.body'));
    }

    public function test_editing_prohibited_after_sent_or_by_non_author(): void
    {
        Sanctum::actingAs($this->author);

        $createResponse = $this->postJson('/api/v1/memos', [
            'kind' => 'template',
            'subject' => 'Draft memo',
            'routing_type' => 'peer',
            'template' => [
                'to' => 'The Director',
                'date' => '2026-06-25',
                'body' => 'Draft body',
            ],
        ])->assertStatus(201);

        $memoId = $createResponse->json('id');

        // Non-author cannot edit
        Sanctum::actingAs($this->recipient);
        $this->patchJson("/api/v1/memos/{$memoId}/template", [
            'subject' => 'Hijacked',
            'template' => [
                'to' => 'The Director',
                'date' => '2026-06-25',
                'body' => 'Hijacked body',
            ],
        ])->assertStatus(403);

        // Author sends memo
        Sanctum::actingAs($this->author);
        $this->postJson("/api/v1/memos/{$memoId}/send", [
            'recipient_ids' => [$this->recipient->id],
        ])->assertOk();

        // Author cannot edit after send
        $this->patchJson("/api/v1/memos/{$memoId}/template", [
            'subject' => 'Edit after sent',
            'template' => [
                'to' => 'The Director',
                'date' => '2026-06-25',
                'body' => 'Edit after sent',
            ],
        ])->assertStatus(403);
    }

    public function test_file_endpoint_returns_404_for_template_memos(): void
    {
        Sanctum::actingAs($this->author);

        $createResponse = $this->postJson('/api/v1/memos', [
            'kind' => 'template',
            'subject' => 'Template memo',
            'routing_type' => 'peer',
            'template' => [
                'to' => 'The Director',
                'date' => '2026-06-25',
                'body' => 'Template body',
            ],
        ])->assertStatus(201);

        $memoId = $createResponse->json('id');

        $this->getJson("/api/v1/memos/{$memoId}/file")->assertNotFound();
    }

    public function test_index_filters_by_box_and_returns_unread_status(): void
    {
        Sanctum::actingAs($this->author);

        // 1. Create a draft memo
        $draftRes = $this->postJson('/api/v1/memos', [
            'kind' => 'template',
            'subject' => 'Draft Memo 1',
            'routing_type' => 'peer',
            'template' => [
                'to' => 'The Director',
                'date' => '2026-06-25',
                'body' => 'Body text',
            ],
        ])->assertStatus(201);
        $draftId = $draftRes->json('id');

        // Check drafts box for author
        $draftsRes = $this->getJson('/api/v1/memos?box=drafts')->assertOk();
        $this->assertCount(1, $draftsRes->json('data'));
        $this->assertEquals($draftId, $draftsRes->json('data.0.id'));
        $this->assertFalse($draftsRes->json('data.0.unread'));

        // Check sent box (should be empty)
        $sentRes = $this->getJson('/api/v1/memos?box=sent')->assertOk();
        $this->assertCount(0, $sentRes->json('data'));

        // 2. Send the draft
        $this->postJson("/api/v1/memos/{$draftId}/send", [
            'recipient_ids' => [$this->recipient->id],
        ])->assertOk();

        // Drafts should now be empty for author
        $draftsRes = $this->getJson('/api/v1/memos?box=drafts')->assertOk();
        $this->assertCount(0, $draftsRes->json('data'));

        // Sent box should now have the memo
        $sentRes = $this->getJson('/api/v1/memos?box=sent')->assertOk();
        $this->assertCount(1, $sentRes->json('data'));
        $this->assertEquals($draftId, $sentRes->json('data.0.id'));

        // 3. Switch to recipient -> Check inbox
        Sanctum::actingAs($this->recipient);
        $inboxRes = $this->getJson('/api/v1/memos?box=inbox')->assertOk();
        $this->assertCount(1, $inboxRes->json('data'));
        $this->assertEquals($draftId, $inboxRes->json('data.0.id'));
        $this->assertTrue($inboxRes->json('data.0.unread'));
        $this->assertEquals('Ada Okafor', $inboxRes->json('data.0.author.name'));

        // Recipient views the memo -> marks as read
        $this->getJson("/api/v1/memos/{$draftId}")->assertOk();

        // Check inbox again -> unread should now be false
        $inboxResAfter = $this->getJson('/api/v1/memos?box=inbox')->assertOk();
        $this->assertCount(1, $inboxResAfter->json('data'));
        $this->assertFalse($inboxResAfter->json('data.0.unread'));
    }

    public function test_create_memo_with_file_upload(): void
    {
        Sanctum::actingAs($this->author);

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/memos', [
            'subject' => 'Uploaded Memo',
            'routing_type' => 'peer',
            'priority' => 'normal',
            'file' => $file,
            'page_count' => 1,
            'page_dims' => [['w' => 595, 'h' => 842]],
        ]);

        $response->assertStatus(201);
        $memoId = $response->json('id');
        $this->assertNotNull($memoId);

        $detail = $this->getJson("/api/v1/memos/{$memoId}")->assertOk();
        $this->assertEquals('file', $detail->json('document.kind'));
        $this->assertEquals("/api/v1/memos/{$memoId}/file", $detail->json('document.url'));
        $this->assertEquals('application/pdf', $detail->json('document.mime'));
    }
}
