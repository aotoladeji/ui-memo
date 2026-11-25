<?php

namespace Tests\Feature;

use App\Models\{AdminScope, Department, Faculty, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentAdminTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $facA;
    private Faculty $facB;
    private Department $deptA1;
    private Department $deptA2;
    private Department $deptB1;

    private User $superAdmin;
    private User $facAAdmin;
    private User $deptA1Admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facA = Faculty::create(['name' => 'Faculty of Science', 'code' => 'SCI']);
        $this->facB = Faculty::create(['name' => 'Faculty of Arts', 'code' => 'ART']);

        $this->deptA1 = Department::create(['faculty_id' => $this->facA->id, 'name' => 'Computer Science', 'code' => 'CSC']);
        $this->deptA2 = Department::create(['faculty_id' => $this->facA->id, 'name' => 'Mathematics', 'code' => 'MAT']);
        $this->deptB1 = Department::create(['faculty_id' => $this->facB->id, 'name' => 'History', 'code' => 'HIS']);

        $unitA1 = Unit::create(['department_id' => $this->deptA1->id, 'name' => 'ICT Unit', 'code' => 'ICT']);

        $this->superAdmin = User::forceCreate([
            'staff_no' => 'SA001',
            'name' => 'Super Admin',
            'email' => 'sa@ui.edu.ng',
            'password' => 'password123',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->facAAdmin = User::forceCreate([
            'staff_no' => 'AD_FAC',
            'name' => 'Science Faculty Admin',
            'email' => 'adfac@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $unitA1->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
        AdminScope::create([
            'user_id' => $this->facAAdmin->id,
            'scope_type' => 'faculty',
            'faculty_id' => $this->facA->id,
        ]);

        $this->deptA1Admin = User::forceCreate([
            'staff_no' => 'AD_DEPT',
            'name' => 'CSC Dept Admin',
            'email' => 'addept@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $unitA1->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
        AdminScope::create([
            'user_id' => $this->deptA1Admin->id,
            'scope_type' => 'department',
            'department_id' => $this->deptA1->id,
        ]);

        $this->staff = User::forceCreate([
            'staff_no' => 'ST001',
            'name' => 'Staff Member',
            'email' => 'staff@ui.edu.ng',
            'password' => 'password123',
            'unit_id' => $unitA1->id,
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    public function test_department_creation_authorization_by_role_and_scope(): void
    {
        // SuperAdmin can create in any faculty
        Sanctum::actingAs($this->superAdmin);
        $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->facB->id,
            'name' => 'Linguistics',
            'code' => 'LIN',
        ])->assertCreated();

        // Faculty-scoped Admin can create in their own faculty
        Sanctum::actingAs($this->facAAdmin);
        $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->facA->id,
            'name' => 'Physics',
            'code' => 'PHY',
        ])->assertCreated();

        // Faculty-scoped Admin cannot create in another faculty
        $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->facB->id,
            'name' => 'Philosophy',
            'code' => 'PHI',
        ])->assertForbidden();

        // Department-scoped Admin cannot create new departments in the faculty
        Sanctum::actingAs($this->deptA1Admin);
        $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->facA->id,
            'name' => 'Statistics',
            'code' => 'STA',
        ])->assertForbidden();

        // Ordinary staff cannot create departments
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/v1/admin/departments', [
            'faculty_id' => $this->facA->id,
            'name' => 'Botany',
            'code' => 'BOT',
        ])->assertForbidden();
    }

    public function test_department_deletion_authorization_by_role_and_scope(): void
    {
        // Ordinary staff cannot delete any department
        Sanctum::actingAs($this->staff);
        $this->deleteJson("/api/v1/admin/departments/{$this->deptA2->id}")->assertForbidden();

        // Department-scoped Admin cannot delete an out-of-scope department (same faculty or other faculty)
        Sanctum::actingAs($this->deptA1Admin);
        $this->deleteJson("/api/v1/admin/departments/{$this->deptA2->id}")->assertForbidden();
        $this->deleteJson("/api/v1/admin/departments/{$this->deptB1->id}")->assertForbidden();

        // Faculty-scoped Admin cannot delete a department in another faculty
        Sanctum::actingAs($this->facAAdmin);
        $this->deleteJson("/api/v1/admin/departments/{$this->deptB1->id}")->assertForbidden();

        // Faculty-scoped Admin CAN delete an empty department in their scoped faculty
        $this->deleteJson("/api/v1/admin/departments/{$this->deptA2->id}")->assertNoContent();
        $this->assertSoftDeleted('departments', ['id' => $this->deptA2->id]);

        // Department-scoped Admin CAN delete their own scoped empty department (here deptA1 has a unit -> 409; empty -> 204)
        $emptyScopedDept = Department::create(['faculty_id' => $this->facA->id, 'name' => 'Geology', 'code' => 'GEO']);
        AdminScope::create([
            'user_id' => $this->deptA1Admin->id,
            'scope_type' => 'department',
            'department_id' => $emptyScopedDept->id,
        ]);
        Sanctum::actingAs($this->deptA1Admin);
        $this->deleteJson("/api/v1/admin/departments/{$this->deptA1->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/departments/{$emptyScopedDept->id}")->assertNoContent();

        // SuperAdmin can delete any empty department
        Sanctum::actingAs($this->superAdmin);
        $this->deleteJson("/api/v1/admin/departments/{$this->deptB1->id}")->assertNoContent();
        $this->assertSoftDeleted('departments', ['id' => $this->deptB1->id]);
    }
}
