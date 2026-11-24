<?php

namespace Tests\Feature;

use App\Models\{AdminScope, Department, Faculty, PermissionAuditLog, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $scopedAdmin;
    private User $staffUser;
    private Department $dept1;
    private Department $dept2;

    protected function setUp(): void
    {
        parent::setUp();

        $faculty = Faculty::create(['name' => 'Faculty of Science', 'code' => 'SCI']);
        $this->dept1 = Department::create(['faculty_id' => $faculty->id, 'name' => 'Computer Science', 'code' => 'CSC']);
        $this->dept2 = Department::create(['faculty_id' => $faculty->id, 'name' => 'Physics', 'code' => 'PHY']);

        $unit1 = Unit::create(['department_id' => $this->dept1->id, 'name' => 'ICT Unit', 'code' => 'ICT']);
        $unit2 = Unit::create(['department_id' => $this->dept2->id, 'name' => 'Lab Unit', 'code' => 'LAB']);

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

        $this->scopedAdmin = User::forceCreate([
            'staff_no' => 'AD001',
            'name' => 'Admin User',
            'email' => 'ad001@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Faculty Officer',
            'unit_id' => $unit1->id,
            'role' => 'admin',
            'is_executive' => false,
            'is_active' => true,
        ]);

        AdminScope::create([
            'user_id' => $this->scopedAdmin->id,
            'scope_type' => 'department',
            'department_id' => $this->dept1->id,
        ]);

        $this->staffUser = User::forceCreate([
            'staff_no' => 'ST001',
            'name' => 'Ada Okafor',
            'email' => 'st001@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Systems Officer',
            'unit_id' => $unit1->id,
            'role' => 'staff',
            'is_executive' => false,
            'is_active' => true,
        ]);

        User::forceCreate([
            'staff_no' => 'ST002',
            'name' => 'Bob Physics',
            'email' => 'bob@ui.edu.ng',
            'password' => 'password123',
            'title' => 'Lab Tech',
            'unit_id' => $unit2->id,
            'role' => 'staff',
            'is_executive' => false,
            'is_active' => true,
        ]);
    }

    public function test_user_listing_returns_data_envelope_matching_frontend_shape(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/admin/users');
        $res->assertOk();
        $res->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'staff_no',
                    'email',
                    'title',
                    'role',
                    'is_active',
                    'can_send_global_broadcast',
                ],
            ],
        ]);

        $users = collect($res->json('data'));
        $withUnit = $users->firstWhere('staff_no', 'ST001');
        $this->assertNotNull($withUnit['unit']);
        $this->assertSame('ICT Unit', $withUnit['unit']['name']);

        $withoutUnit = $users->firstWhere('staff_no', 'SA001');
        $this->assertNull($withoutUnit['unit']);

        $this->assertCount(4, $res->json('data'));
    }

    public function test_user_listing_with_search_filter(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/admin/users?q=Ada');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Ada Okafor', $res->json('data.0.name'));

        $resStaff = $this->getJson('/api/v1/admin/users?q=ST002');
        $resStaff->assertOk();
        $this->assertCount(1, $resStaff->json('data'));
        $this->assertSame('Bob Physics', $resStaff->json('data.0.name'));
    }

    public function test_scoped_admin_only_sees_users_in_scope(): void
    {
        Sanctum::actingAs($this->scopedAdmin);

        $res = $this->getJson('/api/v1/admin/users');
        $res->assertOk();
        // scopedAdmin has scope only on dept1 (CSC), so sees Ada (dept1) but NOT Bob (dept2)
        $names = collect($res->json('data'))->pluck('name')->all();
        $this->assertContains('Ada Okafor', $names);
        $this->assertNotContains('Bob Physics', $names);
    }

    public function test_superadmin_can_toggle_global_broadcast_permission_with_audit(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->patchJson("/api/v1/admin/users/{$this->staffUser->id}/permissions", [
            'can_send_global_broadcast' => true,
        ]);
        $res->assertOk();
        $this->assertTrue($res->json('can_send_global_broadcast'));

        $this->staffUser->refresh();
        $this->assertTrue($this->staffUser->can_send_global_broadcast);

        // Verify audit log entry
        $this->assertDatabaseHas('permission_audit_logs', [
            'actor_id' => $this->superAdmin->id,
            'target_user_id' => $this->staffUser->id,
            'permission' => 'can_send_global_broadcast',
            'new_value' => '1',
        ]);

        // Non-superadmin cannot change permissions
        Sanctum::actingAs($this->staffUser);
        $this->patchJson("/api/v1/admin/users/{$this->staffUser->id}/permissions", [
            'can_send_global_broadcast' => false,
        ])->assertForbidden();
    }

    public function test_assign_and_remove_admin_scope(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Assign scope to admin
        $res = $this->postJson("/api/v1/admin/admins/{$this->scopedAdmin->id}/scopes", [
            'scope_type' => 'department',
            'department_id' => $this->dept2->id,
        ]);
        $res->assertStatus(201);
        $scopeId = $res->json('id');
        $this->assertNotNull($scopeId);

        // Non-admin cannot receive scopes (422)
        $this->postJson("/api/v1/admin/admins/{$this->staffUser->id}/scopes", [
            'scope_type' => 'department',
            'department_id' => $this->dept2->id,
        ])->assertStatus(422);

        // Remove scope
        $delRes = $this->deleteJson("/api/v1/admin/admins/{$this->scopedAdmin->id}/scopes/{$scopeId}");
        $delRes->assertNoContent();
        $this->assertDatabaseMissing('admin_scopes', ['id' => $scopeId]);
    }
}
