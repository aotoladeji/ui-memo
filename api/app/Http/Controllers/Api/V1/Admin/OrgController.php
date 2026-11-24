<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Department, Faculty, Unit};
use App\Services\AdminScopeGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        abort_unless($r->user()->isSuperAdmin(), 403, 'Only SuperAdmin creates departments.');
        $d = $r->validate([
            'faculty_id' => 'required|exists:faculties,id',
            'name' => 'required|max:160',
            'code' => 'required|max:20',
            'logo' => 'nullable|file|image|max:1024',
        ]);

        $dept = Department::create(collect($d)->except('logo')->all());

        if ($r->hasFile('logo')) {
            $file = $r->file('logo');
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                default => $file->guessExtension() ?: ($file->getClientOriginalExtension() ?: 'png'),
            };
            $path = $file->storeAs('logos', "dept_{$dept->id}.{$ext}", 'local');
            $dept->update([
                'has_logo' => true,
                'logo_path' => $path,
            ]);
        }

        return response($dept->fresh(), 201);
    }

    public function uploadLogo(Request $r, Department $department)
    {
        abort_unless($this->scope->canDepartment($r->user(), $department->id), 403);
        $r->validate([
            'logo' => 'required|file|image|max:1024',
        ]);

        if ($department->logo_path && Storage::disk('local')->exists($department->logo_path)) {
            Storage::disk('local')->delete($department->logo_path);
        }

        $file = $r->file('logo');
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => $file->guessExtension() ?: ($file->getClientOriginalExtension() ?: 'png'),
        };
        $path = $file->storeAs('logos', "dept_{$department->id}.{$ext}", 'local');

        $department->update([
            'has_logo' => true,
            'logo_path' => $path,
        ]);

        return response($department->fresh(), 200);
    }

    public function showLogo(Department $department)
    {
        if (!$department->has_logo || !$department->logo_path || !Storage::disk('local')->exists($department->logo_path)) {
            abort(404, 'Logo not found.');
        }

        $mime = Storage::disk('local')->mimeType($department->logo_path) ?: 'image/png';
        return Storage::disk('local')->response($department->logo_path, null, [
            'Content-Type' => $mime,
        ]);
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
        if ($department->logo_path && Storage::disk('local')->exists($department->logo_path)) {
            Storage::disk('local')->delete($department->logo_path);
        }
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
