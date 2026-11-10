<?php

namespace App\Services;

use App\Models\{Department, Unit, User};

/** Decides whether a (scoped) Admin may touch an org node or a user. SuperAdmin may touch anything. */
class AdminScopeGuard
{
    public function departmentIds(User $a): ?array   // null = unrestricted
    {
        if ($a->isSuperAdmin()) return null;
        $scopes = $a->adminScopes()->get();
        $facIds = $scopes->where('scope_type', 'faculty')->pluck('faculty_id');
        return Department::whereIn('faculty_id', $facIds)->pluck('id')
            ->merge($scopes->where('scope_type', 'department')->pluck('department_id'))->unique()->values()->all();
    }

    public function canDepartment(User $a, int $deptId): bool
    {
        $ids = $this->departmentIds($a);
        return $ids === null || in_array($deptId, $ids, true);
    }

    public function canUnit(User $a, Unit $u): bool { return $this->canDepartment($a, $u->department_id); }

    /** Admins manage Staff/Unit Heads inside their scope only. Never Admins or SuperAdmins. */
    public function canUser(User $a, User $target): bool
    {
        if ($a->isSuperAdmin()) return true;
        if (in_array($target->role, ['super_admin', 'admin'], true)) return false;
        return $target->unit && $this->canUnit($a, $target->unit);
    }
}
