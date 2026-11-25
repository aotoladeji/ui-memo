<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Department, Faculty, Unit};
use App\Services\AdminScopeGuard;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

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
        $d = $r->validate([
            'faculty_id' => 'required|exists:faculties,id',
            'name' => 'required|max:160',
            'code' => [
                'required',
                'max:20',
                Rule::unique('departments', 'code')
                    ->where('faculty_id', $r->input('faculty_id'))
                    ->whereNull('deleted_at'),
            ],
            'logo' => 'nullable|file|image|max:1024',
        ]);

        abort_unless(
            $this->scope->canFaculty($r->user(), (int) $d['faculty_id']),
            403,
            'You are not permitted to create departments in this faculty.'
        );

        $storedLogoPath = null;
        try {
            $dept = DB::transaction(function () use ($r, $d, &$storedLogoPath) {
                $dept = Department::create(collect($d)->except('logo')->all());

                if ($r->hasFile('logo')) {
                    $storedLogoPath = $this->storeLogoFile($dept, $r->file('logo'));
                    $dept->update([
                        'has_logo' => true,
                        'logo_path' => $storedLogoPath,
                    ]);
                }

                return $dept;
            });

            return response($dept->fresh(), 201);
        } catch (Throwable $e) {
            if ($storedLogoPath && Storage::disk('local')->exists($storedLogoPath)) {
                Storage::disk('local')->delete($storedLogoPath);
            }
            throw $e;
        }
    }

    public function uploadLogo(Request $r, Department $department)
    {
        abort_unless($this->scope->canDepartment($r->user(), $department->id), 403);
        $r->validate([
            'logo' => 'required|file|image|max:1024',
        ]);

        $oldPath = $department->logo_path;
        $path = $this->storeLogoFile($department, $r->file('logo'));

        try {
            $department->update([
                'has_logo' => true,
                'logo_path' => $path,
            ]);
        } catch (Throwable $e) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        if ($oldPath && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        return response($department->fresh(), 200);
    }

    private function storeLogoFile(Department $department, UploadedFile $file): string
    {
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => $file->guessExtension() ?: ($file->getClientOriginalExtension() ?: 'png'),
        };

        return $file->storeAs('logos', "dept_{$department->id}.{$ext}", 'local');
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
        abort_unless($this->scope->canDepartment($r->user(), $department->id), 403);
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
