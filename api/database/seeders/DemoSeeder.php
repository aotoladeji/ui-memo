<?php

namespace Database\Seeders;

use App\Models\{Department, Faculty, Memo, MemoDocument, MemoRecipient, MemoRoute, StickyNote, Unit, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $sci = Faculty::firstOrCreate(['name' => 'Science'], ['code' => 'SCI']);
        $adminFac = Faculty::firstOrCreate(['name' => 'Administration'], ['code' => 'ADMIN']);

        $csc = Department::firstOrCreate(
            ['faculty_id' => $sci->id, 'name' => 'Computer Science'],
            ['code' => 'CSC', 'has_logo' => false],
        );
        $central = Department::firstOrCreate(
            ['faculty_id' => $adminFac->id, 'name' => 'Central Administration'],
            ['code' => 'CENTRAL', 'has_logo' => false],
        );

        $registry = Unit::create(['department_id' => $central->id, 'name' => 'Registry', 'code' => 'REG']);
        $ict = Unit::create(['department_id' => $csc->id, 'name' => 'ICT Unit', 'code' => 'ICT']);
        $vcOffice = Unit::create(['department_id' => $central->id, 'name' => 'Office of the Vice-Chancellor', 'code' => 'VC']);

        $make = function (string $no, string $name, string $role, ?Unit $unit, array $extra = []) {
            $u = new User([
                'staff_no' => $no,
                'name' => $name,
                'email' => strtolower($no) . '@ui.edu.ng',
                'password' => 'ChangeMe#2026',
                'unit_id' => $unit?->id,
            ] + $extra);
            $u->role = $role;
            $u->save();
            return $u;
        };

        $sa = $make('SA001', 'System Super Admin', 'super_admin', null, ['title' => 'Super Administrator']);
        $vc = $make('VC001', 'Vice-Chancellor', 'staff', $vcOffice, ['title' => 'Vice-Chancellor', 'is_executive' => true]);
        $vc->forceFill(['can_send_global_broadcast' => true])->save();

        $dvc = $make('DVC01', 'Deputy Vice-Chancellor (Admin)', 'staff', $vcOffice, ['title' => 'DVC (Administration)', 'is_executive' => true]);
        $hd = $make('HD001', 'ICT Unit Head', 'unit_head', $ict, ['title' => 'Head, ICT Unit']);
        $st1 = $make('ST001', 'Ada Okafor', 'staff', $ict, ['title' => 'Systems Officer']);
        $st2 = $make('ST002', 'Tunde Bello', 'staff', $ict, ['title' => 'Network Engineer']);

        // Dummy PDF storage for seed file memos
        $disk = config('filesystems.memo_disk', 'local');
        $dummyPdfPath = 'memos/sample.pdf';
        Storage::disk($disk)->put($dummyPdfPath, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n190\n%%EOF");

        // Memo 1: Sent by VC to DVC
        $m1 = Memo::create([
            'uuid' => (string) Str::uuid(),
            'ref_no' => 'UI/VC/2026/0001',
            'subject' => 'Approval of 2027 Research Grant Allocations',
            'created_by' => $vc->id,
            'origin_unit_id' => $vcOffice->id,
            'routing_type' => 'peer',
            'priority' => 'urgent',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        MemoDocument::create([
            'memo_id' => $m1->id,
            'kind' => 'file',
            'storage_path' => $dummyPdfPath,
            'mime' => 'application/pdf',
            'page_count' => 1,
            'page_dims' => [['w' => 595, 'h' => 842]],
            'sha256' => hash('sha256', 'sample1'),
        ]);
        $r1 = MemoRoute::create([
            'memo_id' => $m1->id,
            'depth' => 0,
            'path' => '',
            'kind' => 'origin',
            'from_user_id' => $vc->id,
        ]);
        $r1->update(['path' => "/{$r1->id}/"]);
        MemoRecipient::create(['memo_id' => $m1->id, 'route_id' => $r1->id, 'user_id' => $vc->id, 'recipient_type' => 'author', 'delivered_at' => now()]);
        MemoRecipient::create(['memo_id' => $m1->id, 'route_id' => $r1->id, 'user_id' => $dvc->id, 'recipient_type' => 'to', 'delivered_at' => now()]);

        StickyNote::create([
            'memo_id' => $m1->id,
            'origin_route_id' => $r1->id,
            'author_id' => $vc->id,
            'page_number' => 1,
            'x' => 0.6,
            'y' => 0.3,
            'body' => 'DVC: keep this between us until the Bursary confirms the figures.',
            'color' => '#FDE68A',
            'classification' => 'executive',
            'visibility_scope' => 'chain',
            'requires_action' => true,
            'assigned_to' => $dvc->id,
            'status' => 'open',
        ]);
        StickyNote::create([
            'memo_id' => $m1->id,
            'origin_route_id' => $r1->id,
            'author_id' => $vc->id,
            'page_number' => 1,
            'x' => 0.3,
            'y' => 0.55,
            'body' => 'Line 3 looks high. Please confirm.',
            'color' => '#FDE68A',
            'classification' => 'standard',
            'visibility_scope' => 'chain',
            'requires_action' => false,
            'assigned_to' => null,
            'status' => 'open',
        ]);

        // Memo 2: Sent by HD to ST1 and ST2
        $m2 = Memo::create([
            'uuid' => (string) Str::uuid(),
            'ref_no' => 'UI/ICT/2026/0002',
            'subject' => 'Staff Welfare Policy Review',
            'created_by' => $hd->id,
            'origin_unit_id' => $ict->id,
            'routing_type' => 'internal_unit',
            'priority' => 'normal',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        MemoDocument::create([
            'memo_id' => $m2->id,
            'kind' => 'file',
            'storage_path' => $dummyPdfPath,
            'mime' => 'application/pdf',
            'page_count' => 1,
            'page_dims' => [['w' => 595, 'h' => 842]],
            'sha256' => hash('sha256', 'sample2'),
        ]);
        $r2 = MemoRoute::create([
            'memo_id' => $m2->id,
            'depth' => 0,
            'path' => '',
            'kind' => 'origin',
            'from_user_id' => $hd->id,
        ]);
        $r2->update(['path' => "/{$r2->id}/"]);
        MemoRecipient::create(['memo_id' => $m2->id, 'route_id' => $r2->id, 'user_id' => $hd->id, 'recipient_type' => 'author', 'delivered_at' => now()]);
        MemoRecipient::create(['memo_id' => $m2->id, 'route_id' => $r2->id, 'user_id' => $st1->id, 'recipient_type' => 'to', 'delivered_at' => now()]);
        MemoRecipient::create(['memo_id' => $m2->id, 'route_id' => $r2->id, 'user_id' => $st2->id, 'recipient_type' => 'to', 'delivered_at' => now()]);

        // Memo 3: Template Memo by ST1 to HD
        $m3 = Memo::create([
            'uuid' => (string) Str::uuid(),
            'ref_no' => 'UI/ICT/2026/0003',
            'subject' => 'Request for After-Hours Office Access',
            'created_by' => $st1->id,
            'origin_unit_id' => $ict->id,
            'routing_type' => 'peer',
            'priority' => 'normal',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        MemoDocument::create([
            'memo_id' => $m3->id,
            'kind' => 'template',
            'storage_path' => null,
            'mime' => 'application/json',
            'page_count' => 1,
            'page_dims' => [['w' => 595, 'h' => 842]],
            'template_data' => [
                'department_id' => $csc->id,
                'department_name' => 'Computer Science',
                'from_name' => 'Ada Okafor',
                'from_staff_no' => 'ST001',
                'to' => 'Head, ICT Unit',
                'thru' => [],
                'cc' => [],
                'date' => now()->format('Y-m-d'),
                'subject' => 'Request for After-Hours Office Access',
                'office_line' => 'Office of the Systems Officer',
                'body' => "I am writing to request permission for after-hours office access during the upcoming network upgrade window.\n\nAll security protocols will be strictly maintained.",
                'signature' => [
                    'name' => 'Ada Okafor',
                    'title' => 'Systems Officer',
                    'unit' => 'ICT Unit',
                ],
                'reference_number' => 'UI/ICT/2026/0003',
            ],
            'sha256' => null,
        ]);
        $r3 = MemoRoute::create([
            'memo_id' => $m3->id,
            'depth' => 0,
            'path' => '',
            'kind' => 'origin',
            'from_user_id' => $st1->id,
        ]);
        $r3->update(['path' => "/{$r3->id}/"]);
        MemoRecipient::create(['memo_id' => $m3->id, 'route_id' => $r3->id, 'user_id' => $st1->id, 'recipient_type' => 'author', 'delivered_at' => now()]);
        MemoRecipient::create(['memo_id' => $m3->id, 'route_id' => $r3->id, 'user_id' => $hd->id, 'recipient_type' => 'to', 'delivered_at' => now()]);
    }
}
