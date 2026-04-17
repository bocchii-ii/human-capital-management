<?php

use App\Http\Controllers\Api\V1\ApplicantController;
use App\Http\Controllers\Api\V1\OnboardingAssignmentController;
use App\Http\Controllers\Api\V1\OnboardingTaskController;
use App\Http\Controllers\Api\V1\OnboardingTemplateController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\InterviewController;
use App\Http\Controllers\Api\V1\JobRequisitionController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\PositionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth routes
    Route::post('login', [AuthController::class, 'login']);

    // Protected + tenant-scoped routes
    Route::middleware(['auth:sanctum', 'set.tenant'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        // Core HR
        Route::get('departments/tree', [DepartmentController::class, 'tree']);
        Route::apiResource('departments', DepartmentController::class);

        Route::apiResource('positions', PositionController::class);

        Route::get('employees/org-chart', [EmployeeController::class, 'orgChart']);
        Route::apiResource('employees', EmployeeController::class);

        // Hiring / ATS
        Route::post('job-requisitions/{jobRequisition}/approve', [JobRequisitionController::class, 'approve']);
        Route::apiResource('job-requisitions', JobRequisitionController::class);

        Route::apiResource('applicants', ApplicantController::class);

        Route::patch('applications/{application}/stage', [ApplicationController::class, 'updateStage']);
        Route::apiResource('applications', ApplicationController::class);

        Route::apiResource('interviews', InterviewController::class);

        Route::post('offers/{offer}/send', [OfferController::class, 'send']);
        Route::patch('offers/{offer}/status', [OfferController::class, 'updateStatus']);
        Route::apiResource('offers', OfferController::class);

        // Onboarding
        Route::apiResource('onboarding-templates', OnboardingTemplateController::class);
        Route::apiResource('onboarding-tasks', OnboardingTaskController::class);
        Route::patch('onboarding-assignments/{onboardingAssignment}/tasks/{onboardingTask}/complete', [OnboardingAssignmentController::class, 'completeTask']);
        Route::apiResource('onboarding-assignments', OnboardingAssignmentController::class);
    });
});
