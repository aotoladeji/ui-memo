<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AdminScope, PermissionAuditLog, User};
use App\Services\AdminScopeGuard;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    public function __construct(private AdminScopeGuard $scope) {}

    public function index(Request $r)
    {
        $deptIds = $this->scope->departmentIds($r->user());
        $s = trim((string) $r->query('q', ''));
        return User::with('unit:id,name,code,department_id')
            ->when($deptIds !== null, fn ($q) => $q->whereHas('unit', fn ($u) => $u->whereIn('department_id', $deptIds))->whereIn('role', ['staff', 'unit_head']))
            ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('staff_no', 'like', "$s%")))
            ->orderBy('name')->paginate(30);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'staff_no' => 'required|max:30|unique:users', 'name' => 'required|max:160', 'email' => 'required|email|unique:users',
            'password' => 'required|min:10', 'unit_id' => 'required|exists:units,id', 'title' => 'nullable|max:120',
            'role' => 'required|in:admin,unit_head,staff', 'is_executive' => 'boolean',
        ]);
        $actor = $r->user();
        abort_if($d['role'] === 'admin' && !$actor->isSuperAdmin(), 403, 'Only SuperAdmin creates Admins.');
        $unit = \App\Models\Unit::findOrFail($d['unit_id']);
        abort_unless($this->scope->canUnit($actor, $unit), 403, 'Unit is outside your scope.');
        $u = new User(collect($d)->except('role')->all());   // role is guarded; set explicitly
        $u->role = $d['role'];
        $u->save();
        return response($u, 201);
    }

    public function update(Request $r, User $user)
    {
        abort_unless($this->scope->canUser($r->user(), $user), 403);
        $d = $r->validate(['name' => 'sometimes|max:160', 'title' => 'nullable|max:120', 'unit_id' => 'sometimes|exists:units,id',
                           'is_active' => 'boolean', 'is_executive' => 'boolean', 'password' => 'sometimes|min:10']);
        if (isset($d['unit_id'])) abort_unless($this->scope->canUnit($r->user(), \App\Models\Unit::findOrFail($d['unit_id'])), 403);
        $user->update($d);
        return $user;
    }

    public function destroy(Request $r, User $user)
    {
        abort_unless($this->scope->canUser($r->user(), $user) && $user->id !== $r->user()->id, 403);
        $user->update(['is_active' => false]);   // deactivate, never hard-delete (memo history must remain)
        $user->tokens()->delete();
        return response()->noContent();
    }

    /** SuperAdmin only (route middleware). Audited. */
    public function permissions(Request $r, User $user)
    {
        $d = $r->validate(['can_send_global_broadcast' => 'required|boolean']);
        PermissionAuditLog::create(['actor_id' => $r->user()->id, 'target_user_id' => $user->id,
            'permission' => 'can_send_global_broadcast', 'old_value' => $user->can_send_global_broadcast,
            'new_value' => $d['can_send_global_broadcast']]);
        $user->forceFill($d)->save();
        return $user->only('id', 'name', 'can_send_global_broadcast');
    }

    public function assignScope(Request $r, User $user)
    {
        $d = $r->validate(['scope_type' => 'required|in:faculty,department',
                           'faculty_id' => 'required_if:scope_type,faculty|nullable|exists:faculties,id',
                           'department_id' => 'required_if:scope_type,department|nullable|exists:departments,id']);
        abort_unless($user->role === 'admin', 422, 'Scopes apply to Admin accounts only.');
        return response(AdminScope::create($d + ['user_id' => $user->id]), 201);
    }

    public function removeScope(User $user, AdminScope $scope)
    {
        abort_unless($scope->user_id === $user->id, 404);
        $scope->delete();
        return response()->noContent();
    }
}
