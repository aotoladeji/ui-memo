<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memo_send_outboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('memo_routes')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('routing_type', 30);
            $table->json('recipient_ids');
            $table->string('status', 30)->default('pending'); // pending, completed, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['memo_id', 'route_id'], 'uq_outbox_memo_route');
            $table->index(['status', 'created_at'], 'ix_outbox_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memo_send_outboxes');
    }
};
