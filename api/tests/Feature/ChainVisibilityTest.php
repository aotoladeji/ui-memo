<?php

namespace Tests\Feature;

use App\Models\{Memo, MemoDocument, MemoRecipient, MemoRoute, StickyNote, StickyNoteReply, Unit, User, Department, Faculty};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The masking engine is the product's core promise. These tests encode it. */
class ChainVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $vc; private User $dvc; private User $staff; private User $staff2; private User $other;
    private Memo $memo; private MemoRoute $origin;

    protected function setUp(): void
    {
        parent::setUp();
        $f = Faculty::create(['name' => 'F', 'code' => 'F']);
        $d = Department::create(['faculty_id' => $f->id, 'name' => 'D', 'code' => 'D']);
        $u = Unit::create(['department_id' => $d->id, 'name' => 'U', 'code' => 'U']);
        $mk = fn ($n, $exec = false) => tap(new User(['staff_no' => $n, 'name' => $n, 'email' => "$n@t.test",
            'password' => 'password123', 'unit_id' => $u->id, 'is_executive' => $exec]), fn ($x) => $x->save());
        [$this->vc, $this->dvc, $this->staff, $this->staff2, $this->other] =
            [$mk('vc', true), $mk('dvc', true), $mk('st1'), $mk('st2'), $mk('out')];

        $this->memo = Memo::create(['uuid' => (string) \Str::uuid(), 'ref_no' => 'T/1', 'subject' => 'S',
            'created_by' => $this->vc->id, 'routing_type' => 'peer', 'status' => 'sent']);
        MemoDocument::create(['memo_id' => $this->memo->id, 'storage_path' => 'x.pdf', 'mime' => 'application/pdf', 'sha256' => str_repeat('0', 64)]);
        $this->origin = $this->route(null, $this->vc, 'origin');
        $this->recipient($this->origin, $this->vc, 'author');
        $this->recipient($this->origin, $this->dvc);
    }

    private function route(?MemoRoute $parent, User $from, string $kind = 'forward', array $attrs = []): MemoRoute
    {
        $r = MemoRoute::create(['memo_id' => $this->memo->id, 'parent_route_id' => $parent?->id, 'depth' => $parent ? $parent->depth + 1 : 0,
            'path' => '', 'kind' => $kind, 'from_user_id' => $from->id] + $attrs);
        $r->update(['path' => ($parent->path ?? '/') . $r->id . '/']);
        return $r->refresh();
    }

    private function recipient(MemoRoute $r, User $u, string $type = 'to'): void
    {
        MemoRecipient::create(['memo_id' => $this->memo->id, 'route_id' => $r->id, 'user_id' => $u->id, 'recipient_type' => $type]);
    }

    private function note(User $a, MemoRoute $r, string $cls = 'standard'): StickyNote
    {
        return StickyNote::create(['memo_id' => $this->memo->id, 'origin_route_id' => $r->id, 'author_id' => $a->id,
            'page_number' => 1, 'x' => .5, 'y' => .5, 'body' => "note by {$a->name}", 'classification' => $cls]);
    }

    private function noteIdsFor(User $u): array
    {
        Sanctum::actingAs($u);
        return collect($this->getJson("/api/v1/memos/{$this->memo->id}")->assertOk()->json('notes'))->pluck('id')->all();
    }

    public function test_executive_note_is_hidden_after_forward_with_classification_mask(): void
    {
        $vcNote = $this->note($this->vc, $this->origin, 'executive');
        $dvcNote = $this->note($this->dvc, $this->origin, 'executive');
        $plain = $this->note($this->dvc, $this->origin, 'standard');

        Sanctum::actingAs($this->dvc);
        $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$this->staff->id], 'include_annotations' => true, 'note_mode' => 'all',
            'hidden_classifications' => ['executive'], 'rules' => [],
        ])->assertCreated();

        $ids = $this->noteIdsFor($this->staff);
        $this->assertEqualsCanonicalizing([$plain->id], $ids);
        $this->assertContains($vcNote->id, $this->noteIdsFor($this->vc));   // original trail intact
        $this->assertContains($dvcNote->id, $this->noteIdsFor($this->dvc));
    }

    public function test_explicit_include_overrides_classification_mask_and_exclude_overrides_all(): void
    {
        $exec = $this->note($this->vc, $this->origin, 'executive');
        $plain = $this->note($this->vc, $this->origin, 'standard');

        Sanctum::actingAs($this->dvc);
        $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$this->staff->id], 'include_annotations' => true, 'note_mode' => 'all',
            'hidden_classifications' => ['executive'],
            'rules' => [['target_type' => 'sticky_note', 'target_id' => $exec->id, 'effect' => 'include'],
                        ['target_type' => 'sticky_note', 'target_id' => $plain->id, 'effect' => 'exclude']],
        ])->assertCreated();

        $this->assertEqualsCanonicalizing([$exec->id], $this->noteIdsFor($this->staff));
    }

    public function test_hidden_items_stay_hidden_on_second_forward(): void
    {
        $exec = $this->note($this->vc, $this->origin, 'executive');
        $hop1 = $this->route($this->origin, $this->dvc, 'forward', ['hidden_classifications' => ['executive']]);
        $this->recipient($hop1, $this->staff);

        Sanctum::actingAs($this->staff);
        $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$this->staff2->id], 'include_annotations' => true, 'note_mode' => 'all', 'rules' => [],
        ])->assertCreated();

        $this->assertNotContains($exec->id, $this->noteIdsFor($this->staff2));
    }

    public function test_sender_cannot_reference_an_item_they_cannot_see(): void
    {
        $exec = $this->note($this->vc, $this->origin, 'executive');
        $hop1 = $this->route($this->origin, $this->dvc, 'forward', ['hidden_classifications' => ['executive']]);
        $this->recipient($hop1, $this->staff);

        Sanctum::actingAs($this->staff);
        $this->postJson("/api/v1/memos/{$this->memo->id}/forward", [
            'recipient_ids' => [$this->staff2->id], 'include_annotations' => true, 'note_mode' => 'all',
            'rules' => [['target_type' => 'sticky_note', 'target_id' => $exec->id, 'effect' => 'include']],
        ])->assertStatus(422);
    }

    public function test_downstream_notes_and_replies_reach_upstream_but_not_siblings(): void
    {
        $hopA = $this->route($this->origin, $this->dvc, 'forward');
        $hopB = $this->route($this->origin, $this->dvc, 'forward');
        $this->recipient($hopA, $this->staff);
        $this->recipient($hopB, $this->staff2);

        $staffNote = $this->note($this->staff, $hopA);
        StickyNoteReply::create(['note_id' => $staffNote->id, 'origin_route_id' => $hopA->id, 'author_id' => $this->staff->id, 'body' => 'done']);

        $this->assertContains($staffNote->id, $this->noteIdsFor($this->dvc));
        $this->assertContains($staffNote->id, $this->noteIdsFor($this->vc));
        $this->assertNotContains($staffNote->id, $this->noteIdsFor($this->staff2));   // sibling branch
    }

    public function test_non_recipient_gets_404(): void
    {
        Sanctum::actingAs($this->other);
        $this->getJson("/api/v1/memos/{$this->memo->id}")->assertNotFound();
    }

    public function test_global_broadcast_requires_flag(): void
    {
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/v1/memos', ['subject' => 'All staff', 'routing_type' => 'global',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('m.pdf', 10, 'application/pdf')])->assertForbidden();
    }

    public function test_only_super_admin_toggles_global_flag(): void
    {
        Sanctum::actingAs($this->dvc);
        $this->patchJson("/api/v1/admin/users/{$this->staff->id}/permissions", ['can_send_global_broadcast' => true])->assertForbidden();
    }
}
