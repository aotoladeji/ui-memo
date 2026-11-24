<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 20)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 20);
            $table->boolean('has_logo')->default(false);
            $table->string('logo_path', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['faculty_id', 'code'], 'uq_dept');
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 20);
            $table->unsignedBigInteger('head_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['department_id', 'code'], 'uq_unit');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('staff_no', 30)->unique();
            $table->string('name', 160);
            $table->string('email', 190)->unique();
            $table->string('password', 255);
            $table->string('role', 30)->default('staff');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('title', 120)->nullable();
            $table->boolean('is_executive')->default(false);
            $table->boolean('can_send_global_broadcast')->default(false);
            $table->string('signature_path', 255)->nullable();
            $table->string('stamp_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->index('unit_id', 'ix_users_unit');
            $table->index('role', 'ix_users_role');
        });

        Schema::create('admin_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope_type', 30);
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('ref_no', 40)->unique();
            $table->string('subject', 255);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('origin_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('routing_type', 30);
            $table->string('priority', 30)->default('normal');
            $table->string('status', 30)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_by', 'ix_memo_creator');
            $table->index(['status', 'sent_at'], 'ix_memo_status');
        });

        Schema::create('memo_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->string('kind', 30)->default('file');
            $table->string('storage_path', 255)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedSmallInteger('page_count')->default(1);
            $table->json('page_dims')->nullable();
            $table->json('template_data')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('memo_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->foreignId('parent_route_id')->nullable()->constrained('memo_routes')->nullOnDelete();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->string('path', 255)->default('');
            $table->string('kind', 30);
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('annotation_mode', 30)->default('all');
            $table->string('note_mode', 30)->default('all');
            $table->json('hidden_classifications')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->index('memo_id', 'ix_route_memo');
            $table->index(['memo_id', 'path'], 'ix_route_path');
        });

        Schema::create('memo_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('memo_routes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_type', 30)->default('to');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('action_state', 30)->default('none');
            $table->timestamps();
            $table->unique(['route_id', 'user_id'], 'uq_route_user');
            $table->index(['user_id', 'memo_id'], 'ix_inbox');
        });

        Schema::create('annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->foreignId('origin_route_id')->constrained('memo_routes')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->string('tool', 30);
            $table->json('geometry');
            $table->json('style')->nullable();
            $table->string('classification', 30)->default('standard');
            $table->string('visibility_scope', 30)->default('chain');
            $table->integer('z_index')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['memo_id', 'page_number'], 'ix_ann_page');
            $table->index('origin_route_id', 'ix_ann_origin');
        });

        Schema::create('sticky_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->foreignId('origin_route_id')->constrained('memo_routes')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->decimal('x', 8, 6);
            $table->decimal('y', 8, 6);
            $table->text('body');
            $table->string('color', 9)->default('#FDE68A');
            $table->string('classification', 30)->default('standard');
            $table->string('visibility_scope', 30)->default('chain');
            $table->boolean('requires_action')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['memo_id', 'page_number'], 'ix_note_page');
            $table->index(['memo_id', 'requires_action', 'status'], 'ix_note_action');
        });

        Schema::create('sticky_note_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('sticky_notes')->cascadeOnDelete();
            $table->foreignId('origin_route_id')->constrained('memo_routes')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index('note_id', 'ix_reply_note');
        });

        Schema::create('memo_forward_visibility_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('memo_routes')->cascadeOnDelete();
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');
            $table->string('effect', 30);
            $table->unique(['route_id', 'target_type', 'target_id'], 'uq_rule');
        });

        Schema::create('permission_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('target_user_id');
            $table->string('permission', 60);
            $table->boolean('old_value');
            $table->boolean('new_value');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memo_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 60);
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['memo_id', 'created_at'], 'ix_audit_memo');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('permission_audit_logs');
        Schema::dropIfExists('memo_forward_visibility_rules');
        Schema::dropIfExists('sticky_note_replies');
        Schema::dropIfExists('sticky_notes');
        Schema::dropIfExists('annotations');
        Schema::dropIfExists('memo_recipients');
        Schema::dropIfExists('memo_routes');
        Schema::dropIfExists('memo_documents');
        Schema::dropIfExists('memos');
        Schema::dropIfExists('admin_scopes');
        Schema::dropIfExists('users');
        Schema::dropIfExists('units');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('faculties');
    }
};
