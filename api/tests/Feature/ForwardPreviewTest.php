<?php

namespace Tests\Feature;

use App\Models\{Department, Faculty, Memo, MemoDocument, MemoRecipient, MemoRoute, Minute, StickyNote, StickyNoteReply, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForwardPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $vc;
    private User $dvc;
    private User $staffNew;
    private Memo $memo;
    private MemoRoute $origin;

    protected function setUp(): void
    {
        parent::setUp();

        $f = Faculty::create(['name' => 'Science', 'code' => 'SCI']);
        $d = Department::create(['faculty_id' => $f->id, 'name' => 'CSC', 'code' => 'CSC']);
        $u = Unit::create(['department_id' => $d->id, 'name' => 'ICT', 'code' => 'ICT']);

        $mk = fn (string $no, string $name, bool $exec = false) => User::forceCreate([
            'staff_no' => $no,
            'name' => $name,
            'email' => strtolower($no) . '@ui.edu.ng',
            'password' => 'password123',
            'title' => $name,
            'unit_id' => $u->id,
            'role' => 'staff',
            'is_executive' => $exec,
            'is_active' => true,
        ]);

        $this->vc = $mk('VC001', 'Vice-Chancellor', true);
        $this->dvc = $mk('DVC01', 'Deputy VC', true);
        $this->staffNew = $mk('ST001', 'Ada Okafor', false);

        $this->memo = Memo::create([
            'uuid' => (string) \Str::uuid(),
            'ref_no' => 'UI/VC/2026/0001',
            'subject' => 'Multi-recipient Preview Memo',
            'created_by' => $this->vc->id,
            'routing_type' => 'peer',
            'status' => 'sent',
        ]);

        MemoDocument::create([
            'memo_id' => $this->memo->id,
            'kind' => 'file',
            'storage_path' => 'memos/test.pdf',
            'mime' => 'application/pdf',
            'sha256' => str_repeat('1', 64),
        ]);

        $this->origin = MemoRoute::create([
            'memo_id' => $this->memo->id,
            'depth' => 0,
            'path' => '',
            'kind' => 'origin',
            'from_user_id' => $this->vc->id,
        ]);
        $this->origin->update(['path' => "/{$this->origin->id}/"]);

        MemoRecipient::create(['memo_id' => $this->memo->id, 'route_id' => $this->origin->id, 'user_id' => $this->vc->id, 'recipient_type' => 'author']);
        MemoRecipient::create(['memo_id' => $this->memo->id, 'route_id' => $this->origin->id, 'user_id' => $this->dvc->id, 'recipient_type' => 'to']);
    }

    public function test_forward_preview_returns_distinct_results_per_recipient_and_rolls_back(): void
    {
        // On origin:
        // - VC writes a standard note (visible to all)
        // - DVC writes an executive note, a private note, and an executive minute (all authored by DVC)
        $vcStdNote = StickyNote::create([
            'memo_id' => $this->memo->id,
            'origin_route_id' => $this->origin->id,
            'author_id' => $this->vc->id,
            'page_number' => 1,
            'x' => 0.2,
            'y' => 0.2,
            'body' => 'VC standard note',
            'classification' => 'standard',
            'visibility_scope' => 'chain',
        ]);

        $dvcExecNote = StickyNote::create([
            'memo_id' => $this->memo->id,
            'origin_route_id' => $this->origin->id,
            'author_id' => $this->dvc->id,
            'page_number' => 1,
            'x' => 0.4,
            'y' => 0.4,
            'body' => 'DVC executive note',
            'classification' => 'executive',
            'visibility_scope' => 'chain',
        ]);

        $dvcPrivateNote = StickyNote::create([
            'memo_id' => $this->memo->id,
            'origin_route_id' => $this->origin->id,
            'author_id' => $this->dvc->id,
            'page_number' => 1,
            'x' => 0.5,
            'y' => 0.5,
            'body' => 'DVC private reminder',
            'classification' => 'standard',
            'visibility_scope' => 'private',
        ]);

        $dvcExecMinute = Minute::create([
            'memo_id' => $this->memo->id,
            'origin_route_id' => $this->origin->id,
            'author_id' => $this->dvc->id,
            'page_number' => 1,
            'x' => 0.6,
            'y' => 0.6,
            'body' => 'DVC executive minute',
            'classification' => 'executive',
        ]);

        // DVC also posted a reply on a sibling branch
        $siblingHop = MemoRoute::create([
            'memo_id' => $this->memo->id,
            'parent_route_id' => $this->origin->id,
            'depth' => 1,
            'path' => '',
            'kind' => 'forward',
            'from_user_id' => $this->vc->id,
        ]);
        $siblingHop->update(['path' => $this->origin->path . $siblingHop->id . '/']);
        $dvcSiblingReply = StickyNoteReply::create([
            'note_id' => $vcStdNote->id,
            'origin_route_id' => $siblingHop->id,
            'author_id' => $this->dvc->id,
            'body' => 'DVC sibling reply',
        ]);

        $routeCountBefore = MemoRoute::count();
        $recipientCountBefore = MemoRecipient::count();

        // VC previews forwarding to [staffNew, dvc] with executive items hidden
        Sanctum::actingAs($this->vc);
        $res = $this->postJson("/api/v1/memos/{$this->memo->id}/forward-preview", [
            'recipient_ids' => [$this->staffNew->id, $this->dvc->id],
            'include_annotations' => true,
            'note_mode' => 'all',
            'hidden_classifications' => ['executive'],
            'rules' => [],
            'from_route_id' => $this->origin->id,
        ])->assertOk();

        // Top-level fields still match first recipient (backward compatibility)
        $this->assertEquals([$vcStdNote->id], collect($res->json('notes'))->pluck('id')->all());

        $recipients = collect($res->json('recipients'))->keyBy('recipient_id');
        $this->assertCount(2, $recipients);

        // Recipient 1 (staffNew, no prior authored history): sees only VC's standard note, no executive/private items, no sibling reply
        $previewNew = $recipients->get($this->staffNew->id);
        $this->assertSame('Ada Okafor', $previewNew['recipient']['name']);
        $this->assertEqualsCanonicalizing([$vcStdNote->id], collect($previewNew['notes'])->pluck('id')->all());
        $this->assertSame([], $previewNew['replies']);
        $this->assertSame([], $previewNew['minutes']);

        // Recipient 2 (dvc, authored exec/private items & sibling reply): sees own authored items too
        $previewDvc = $recipients->get($this->dvc->id);
        $this->assertSame('Deputy VC', $previewDvc['recipient']['name']);
        $this->assertEqualsCanonicalizing(
            [$vcStdNote->id, $dvcExecNote->id, $dvcPrivateNote->id],
            collect($previewDvc['notes'])->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing([$dvcSiblingReply->id], collect($previewDvc['replies'])->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$dvcExecMinute->id], collect($previewDvc['minutes'])->pluck('id')->all());

        // Dry-run transaction rolled back cleanly
        $this->assertSame($routeCountBefore, MemoRoute::count());
        $this->assertSame($recipientCountBefore, MemoRecipient::count());
    }
}
