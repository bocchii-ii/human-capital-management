<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('onboarding_task_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('onboarding_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['onboarding_assignment_id', 'onboarding_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_task_completions');
    }
};
