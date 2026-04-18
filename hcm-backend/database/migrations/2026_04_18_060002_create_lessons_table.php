<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('content_type', ['video', 'pdf', 'text', 'quiz']);
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->string('file_url')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
