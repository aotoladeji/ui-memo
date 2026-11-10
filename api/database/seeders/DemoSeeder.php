<?php

namespace Database\Seeders;

use App\Models\{Department, Faculty, Unit, User};
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $fac = Faculty::create(['name' => 'Faculty of Science', 'code' => 'SCI']);
        $dep = Department::create(['faculty_id' => $fac->id, 'name' => 'Computer Science', 'code' => 'CSC']);
        $registry = Unit::create(['department_id' => $dep->id, 'name' => 'Registry', 'code' => 'REG']);
        $ict = Unit::create(['department_id' => $dep->id, 'name' => 'ICT Unit', 'code' => 'ICT']);

        $make = function (string $no, string $name, string $role, ?Unit $unit, array $extra = []) {
            $u = new User(['staff_no' => $no, 'name' => $name, 'email' => strtolower($no) . '@ui.edu.ng',
                           'password' => 'ChangeMe#2026', 'unit_id' => $unit?->id] + $extra);
            $u->role = $role;
            $u->save();
            return $u;
        };

        $make('SA001', 'System Super Admin', 'super_admin', null, ['title' => 'Super Administrator']);
        $vc = $make('VC001', 'Vice-Chancellor', 'staff', $registry, ['title' => 'Vice-Chancellor', 'is_executive' => true]);
        $vc->forceFill(['can_send_global_broadcast' => true])->save();
        $make('DVC01', 'Deputy Vice-Chancellor (Admin)', 'staff', $registry, ['title' => 'DVC (Administration)', 'is_executive' => true]);
        $make('HD001', 'ICT Unit Head', 'unit_head', $ict, ['title' => 'Head, ICT Unit']);
        $make('ST001', 'Ada Okafor', 'staff', $ict, ['title' => 'Systems Officer']);
        $make('ST002', 'Tunde Bello', 'staff', $ict, ['title' => 'Network Engineer']);
    }
}
