<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FacultyDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $facultyRows = $this->readCsv('faculty.csv');
        $departmentRows = $this->readCsv('department.csv');

        DB::transaction(function () use ($facultyRows, $departmentRows) {
            $facultiesBySourceId = [];

            foreach ($facultyRows as $row) {
                $sourceId = $this->sourceId($row, 'faculty_id');
                $name = trim($row['faculty'] ?? '');
                if ($name === '' || isset($facultiesBySourceId[$sourceId])) {
                    throw new RuntimeException("Invalid or duplicate faculty row for source ID {$sourceId}.");
                }

                $faculty = Faculty::withTrashed()->updateOrCreate(
                    ['code' => "CSV-F-{$sourceId}"],
                    ['name' => $name],
                );
                if ($faculty->trashed()) {
                    $faculty->restore();
                }

                $facultiesBySourceId[$sourceId] = $faculty;
            }

            foreach ($departmentRows as $row) {
                $sourceId = $this->sourceId($row, 'department_id');
                $facultySourceId = $this->sourceId($row, 'faculty_id');
                $faculty = $facultiesBySourceId[$facultySourceId] ?? null;
                $name = trim($row['department'] ?? '');
                if (!$faculty || $name === '') {
                    throw new RuntimeException("Invalid department row for source ID {$sourceId}.");
                }

                $department = Department::withTrashed()->updateOrCreate(
                    ['faculty_id' => $faculty->id, 'code' => "CSV-D-{$sourceId}"],
                    [
                        'name' => $name,
                        'dept_category' => trim($row['dept_category'] ?? '') ?: null,
                    ],
                );
                if ($department->trashed()) {
                    $department->restore();
                }
            }
        });
    }

    private function readCsv(string $filename): array
    {
        $path = database_path("seeders/data/{$filename}");
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to read organization CSV: {$path}");
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if ($headers === false) {
                throw new RuntimeException("Organization CSV has no header: {$path}");
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $headers = array_map('trim', $headers);
            $rows = [];

            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new RuntimeException("Invalid column count in organization CSV: {$path}");
                }
                $rows[] = array_combine($headers, $values);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function sourceId(array $row, string $column): int
    {
        $value = filter_var($row[$column] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new RuntimeException("Invalid {$column} in organization CSV.");
        }

        return $value;
    }
}
