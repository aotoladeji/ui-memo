<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Department, Faculty, Unit};
use App\Services\AdminScopeGuard;
use Illuminate\Http\Request;

/** Faculties: SuperAdmin only (route middleware). Departments/Units: SuperAdmin or in-scope Admin. */
class OrgController extends Controller
{
    public function __construct(private AdminScopeGuard $scope) {}

    public function tree(Request $r)
    {
        $deptIds = $this->scope->departmentIds($r->user());
        return Faculty::with(['departments' => fn ($q) => $deptIds === null ? $q : $q->whereIn('id', $deptIds),
                              'departments.units'])->orderBy('name')->get();
    }

    // Faculties
    public function storeFaculty(Request $r) { return response(Faculty::create($r->validate(['name' => 'required|max:160', 'code' => 'required|max:20|unique:faculties,code'])), 201); }
    public function updateFaculty(Request $r, Faculty $faculty) { $faculty->update($r->validate(['name' => 'sometimes|max:160', 'code' => "sometimes|max:20|unique:faculties,code,{$faculty->id}"])); return $faculty; }
    public function destroyFaculty(Faculty $faculty) { abort_if($faculty->departments()->exists(), 409, 'Remove its departments first.'); $faculty->delete(); return response()->noContent(); }

    // Departments
    public function storeDepartment(Request $r)
    {
        $d = $r->validate(['faculty_id' => 'required|exists:faculties,id', 'name' => 'required|max:160', 'code' => 'required|max:20']);
        abort_unless($r->user()->isSuperAdmin(), 403, 'Only SuperAdmin creates departments.');
        return response(Department::create($d), 201);
    }
    public function updateDepartment(Request $r, Department $department)
    {
        abort_unless($this->scope->canDepartment($r->user(), $department->id), 403);
        $department->update($r->validate(['name' => 'sometimes|max:160', 'code' => 'sometimes|max:20']));
        return $department;
    }
    public function destroyDepartment(Request $r, Department $department)
    {
        abort_unless($r->user()->isSuperAdmin(), 403);
        abort_if($department->units()->exists(), 409, 'Remove its units first.');
        $department->delete();
        return response()->noContent();
    }

    // Units
    public function storeUnit(Request $r)
    {
        $d = $r->validate(['department_id' => 'required|exists:departments,id', 'name' => 'required|max:160', 'code' => 'required|max:20', 'head_user_id' => 'nullable|exists:users,id']);
        abort_unless($this->scope->canDepartment($r->user(), $d['department_id']), 403);
        return response(Unit::create($d), 201);
    }
    public function updateUnit(Request $r, Unit $unit)
    {
        abort_unless($this->scope->canUnit($r->user(), $unit), 403);
        $unit->update($r->validate(['name' => 'sometimes|max:160', 'code' => 'sometimes|max:20', 'head_user_id' => 'nullable|exists:users,id']));
        return $unit;
    }
    public function destroyUnit(Request $r, Unit $unit)
    {
        abort_unless($this->scope->canUnit($r->user(), $unit), 403);
        abort_if($unit->members()->exists(), 409, 'Reassign its members first.');
        $unit->delete();
        return response()->noContent();
    }
}
