<?php

namespace Tests\Feature;

use App\Models\{Department, Faculty, Memo, MemoDocument, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class UploadCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $superAdmin;
    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->faculty = Faculty::create(['name' => 'Science', 'code' => 'SCI']);
        $dept = Department::create(['faculty_id' => $this->faculty->id, 'name' => 'CSC', 'code' => 'CSC']);
        $unit = Unit::create(['department_id' => $dept->id, 'name' => 'ICT', 'code' => 'ICT']);

        $this->author = User::forceCreate([
            'staff_no' => 'ST001',
            'name' => 'Ada Okafor',
            'email' => 'st001@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $unit->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        $this->superAdmin = User::forceCreate([
            'staff_no' => 'SA001',
            'name' => 'Super Admin',
            'email' => 'sa001@ui.edu.ng',
            'password' => 'password123',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_rejects_word_documents_and_unsupported_mime_types(): void
    {
        Sanctum::actingAs($this->author);

        $docx = UploadedFile::fake()->create(
            'memo.docx',
            50,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $resDocx = $this->postJson('/api/v1/memos', [
            'subject' => 'Word upload',
            'routing_type' => 'peer',
            'file' => $docx,
        ])->assertStatus(422);
        $this->assertStringContainsString('PDF', $resDocx->json('errors.file.0'));

        $doc = UploadedFile::fake()->create('memo.doc', 50, 'application/msword');
        $this->postJson('/api/v1/memos', [
            'subject' => 'Legacy Word upload',
            'routing_type' => 'peer',
            'file' => $doc,
        ])->assertStatus(422);

        // Supported image upload succeeds
        $png = UploadedFile::fake()->image('scan.png', 100, 100);
        $this->postJson('/api/v1/memos', [
            'subject' => 'Image upload',
            'routing_type' => 'peer',
            'file' => $png,
            'page_count' => 1,
            'page_dims' => [['w' => 100, 'h' => 100]],
        ])->assertCreated();

        $this->assertCount(1, Storage::disk('local')->allFiles('memos'));
    }

    public function test_failed_memo_db_write_deletes_uploaded_file_from_storage(): void
    {
        Sanctum::actingAs($this->author);

        MemoDocument::creating(function () {
            throw new RuntimeException('Simulated database insert failure');
        });

        try {
            $pdf = UploadedFile::fake()->create('budget.pdf', 120, 'application/pdf');
            $this->postJson('/api/v1/memos', [
                'subject' => 'Should rollback and clean up',
                'routing_type' => 'peer',
                'file' => $pdf,
            ])->assertStatus(500);
        } finally {
            MemoDocument::flushEventListeners();
        }

        $this->assertSame([], Storage::disk('local')->allFiles('memos'));
        $this->assertEquals(0, Memo::count());
    }

    public function test_failed_department_logo_db_update_cleans_up_orphaned_logo(): void
    {
        Sanctum::actingAs($this->superAdmin);

        Department::updating(function () {
            throw new RuntimeException('Simulated DB update failure');
        });

        try {
            $logo = UploadedFile::fake()->image('crest.png', 50, 50);
            $this->postJson('/api/v1/admin/departments', [
                'faculty_id' => $this->faculty->id,
                'name' => 'Biochemistry',
                'code' => 'BCH',
                'logo' => $logo,
            ])->assertStatus(500);
        } finally {
            Department::flushEventListeners();
        }

        $this->assertSame([], Storage::disk('local')->allFiles('logos'));
        $this->assertDatabaseMissing('departments', ['code' => 'BCH']);
    }
}
