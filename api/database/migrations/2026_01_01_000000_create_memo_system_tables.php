<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Runs the reference DDL in database/schema/memo_system.sql (statements split on ';' + newline).
// If you ran `php artisan install:api`, delete its personal_access_tokens migration:
// this file already creates that table. Also delete Laravel's default users migration.
return new class extends Migration {
    public function up(): void
    {
        $sql = file_get_contents(database_path('schema/memo_system.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $stmt) {
            DB::unprepared($stmt);
        }
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['audit_logs','permission_audit_logs','memo_forward_visibility_rules','sticky_note_replies',
                  'sticky_notes','annotations','memo_recipients','memo_routes','memo_documents','memos',
                  'admin_scopes','users','units','departments','faculties','personal_access_tokens'] as $t) {
            DB::statement("DROP TABLE IF EXISTS $t");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
