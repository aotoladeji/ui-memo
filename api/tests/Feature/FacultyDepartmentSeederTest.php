<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Unit;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FacultyDepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacultyDepartmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_organization_rows_are_imported_idempotently_with_faculty_relationships(): void
    {
        $this->seed(FacultyDepartmentSeeder::class);

        $this->assertSame(33, Faculty::count());
        $this->assertSame(175, Department::count());

        $agriculture = Faculty::where('name', 'Agriculture')->firstOrFail();
        $agriculturalEconomics = Department::where('name', 'Agricultural Economics')->firstOrFail();
        $this->assertSame($agriculture->id, $agriculturalEconomics->faculty_id);

        $computing = Faculty::where('name', 'Computing')->firstOrFail();
        $computerScience = Department::where('name', 'Computer Science and Artificial Intelligence')->firstOrFail();
        $this->assertSame($computing->id, $computerScience->faculty_id);
        $this->assertSame('Academic', $computerScience->dept_category);

        $this->seed(FacultyDepartmentSeeder::class);
        $this->assertSame(33, Faculty::count());
        $this->assertSame(175, Department::count());
    }

    public function test_default_database_seeder_reuses_imported_faculty_and_department_for_demo_data(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $science = Faculty::where('name', 'Science')->firstOrFail();
        $computerScience = Department::where('name', 'Computer Science')
            ->where('faculty_id', $science->id)
            ->firstOrFail();
        $ictUnit = Unit::where('name', 'ICT Unit')->firstOrFail();

        $this->assertSame($computerScience->id, $ictUnit->department_id);
        $this->assertSame(1, Faculty::where('name', 'Science')->count());
        $this->assertSame(34, Faculty::count());
        $this->assertSame(176, Department::count());
    }
}
