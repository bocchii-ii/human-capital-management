<?php

namespace App\Providers;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobRequisition;
use App\Models\Offer;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\Position;
use App\Policies\ApplicantPolicy;
use App\Policies\ApplicationPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\InterviewPolicy;
use App\Policies\JobRequisitionPolicy;
use App\Policies\OfferPolicy;
use App\Policies\OnboardingAssignmentPolicy;
use App\Policies\OnboardingTaskPolicy;
use App\Policies\OnboardingTemplatePolicy;
use App\Policies\PositionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Core HR
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);

        // Hiring / ATS
        Gate::policy(JobRequisition::class, JobRequisitionPolicy::class);
        Gate::policy(Applicant::class, ApplicantPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Interview::class, InterviewPolicy::class);
        Gate::policy(Offer::class, OfferPolicy::class);

        // Onboarding
        Gate::policy(OnboardingTemplate::class, OnboardingTemplatePolicy::class);
        Gate::policy(OnboardingTask::class, OnboardingTaskPolicy::class);
        Gate::policy(OnboardingAssignment::class, OnboardingAssignmentPolicy::class);
    }
}
