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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('stage')->default('applied'); // applied, screening, interview, offer, hired, rejected
            $table->text('cover_letter')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['job_requisition_id', 'applicant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
