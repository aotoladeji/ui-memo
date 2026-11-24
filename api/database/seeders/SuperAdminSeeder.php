<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'sa001@ui.edu.ng'],
            [
                'staff_no' => 'SA001',
                'name' => 'System Super Admin',
                'password' => 'ChangeMe#2026',
                'role' => 'super_admin',
                'title' => 'Super Administrator',
                'unit_id' => null,
                'is_executive' => false,
                'can_send_global_broadcast' => true,
                'is_active' => true,
            ]
        );
    }
}
