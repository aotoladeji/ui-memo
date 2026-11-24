<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->foreignId('origin_route_id')->constrained('memo_routes')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->integer('page_number');
            $table->double('x');
            $table->double('y');
            $table->text('body');
            $table->string('color', 20)->default('#E0E7FF');
            $table->string('classification', 20)->default('standard');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['memo_id', 'page_number']);
            $table->index('origin_route_id');
        });

        Schema::create('minute_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained('minutes')->cascadeOnDelete();
            $table->foreignId('origin_route_id')->constrained('memo_routes')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index('minute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_replies');
        Schema::dropIfExists('minutes');
    }
};
