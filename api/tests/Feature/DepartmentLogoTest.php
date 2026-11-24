<?php

namespace Tests\Feature;

use App\Models\{Department, Faculty, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentLogoTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $staffUser;
    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->faculty = Faculty::create(['name' => 'Faculty of Science', 'code' => 'SCI']);

        $dept = Department::create([
            'faculty_id' => $this->faculty->id,
            'name' => 'Computer Science',
            'code' => 'CSC',
            'has_logo' => false,
        ]);

        $unit = Unit::create(['department_id' => $dept->id, 'name' => 'ICT Unit', 'code' => 'ICT']);

        $this->superAdmin = User::forceCreate([
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

        $this->staffUser = User::create([
            'staff_no' => 'ST001',
            'name' => 'Ada Okafor',
            'email' => 'st001@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Systems Officer',
            'unit_id' => $unit->id,
            'role' => 'staff',
            'is_executive' => false,
            'is_active' => true,
        ]);
    }

    public function test_superadmin_creates_department_with_logo_and_user_reads_it(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $file = UploadedFile::fake()->image('physics.png', 50, 50);

        $createRes = $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->faculty->id,
            'name' => 'Physics',
            'code' => 'PHY',
            'logo' => $file,
        ]);
        $createRes->assertStatus(201);
        $deptId = $createRes->json('id');
        $this->assertNotNull($deptId);

        // GET /admin/org returns has_logo == true
        $orgRes = $this->getJson('/api/v1/admin/org')->assertOk();
        $departments = collect($orgRes->json('0.departments') ?? $orgRes->json('data.0.departments') ?? []);
        if ($departments->isEmpty()) {
            // Check direct array structure if tree returns array of faculties
            $faculties = $orgRes->json();
            $deptFound = collect($faculties[0]['departments'] ?? [])->firstWhere('id', $deptId);
        } else {
            $deptFound = $departments->firstWhere('id', $deptId);
        }
        $this->assertNotNull($deptFound);
        $this->assertTrue((bool) $deptFound['has_logo']);

        // Any authenticated user can read logo
        Sanctum::actingAs($this->staffUser);
        $logoRes = $this->get("/api/v1/departments/{$deptId}/logo");
        $logoRes->assertOk();
        $this->assertStringStartsWith('image/', $logoRes->headers->get('Content-Type'));
    }

    public function test_returns_404_when_no_logo_and_accepts_upload_later(): void
    {
        $dept = Department::create([
            'faculty_id' => $this->faculty->id,
            'name' => 'Chemistry',
            'code' => 'CHM',
            'has_logo' => false,
        ]);

        Sanctum::actingAs($this->staffUser);
        $this->get("/api/v1/departments/{$dept->id}/logo")->assertNotFound();

        // SuperAdmin uploads logo later
        Sanctum::actingAs($this->superAdmin);
        $file = UploadedFile::fake()->image('chemistry.png', 50, 50);
        $uploadRes = $this->postJson("/api/v1/admin/departments/{$dept->id}/logo", [
            'logo' => $file,
        ]);
        $uploadRes->assertOk();

        // Now accessible
        Sanctum::actingAs($this->staffUser);
        $this->get("/api/v1/departments/{$dept->id}/logo")->assertOk();
    }

    public function test_rejects_non_images_and_files_over_1mb(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Non-image file
        $pdfFile = UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf');
        $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->faculty->id,
            'name' => 'Botany',
            'code' => 'BOT',
            'logo' => $pdfFile,
        ])->assertStatus(422);

        // File over 1MB (1025 KB)
        $hugeFile = UploadedFile::fake()->create('huge.png', 1025, 'image/png');
        $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->faculty->id,
            'name' => 'Botany',
            'code' => 'BOT',
            'logo' => $hugeFile,
        ])->assertStatus(422);

        // Non-admin cannot create department
        Sanctum::actingAs($this->staffUser);
        $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->faculty->id,
            'name' => 'Botany',
            'code' => 'BOT',
        ])->assertStatus(403);
    }
}
