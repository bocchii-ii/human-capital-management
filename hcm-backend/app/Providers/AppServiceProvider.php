<?php

namespace App\Providers;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Interview;
use App\Models\JobRequisition;
use App\Models\Lesson;
use App\Models\LearningPath;
use App\Models\LearningPathCourse;
use App\Models\Offer;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\Position;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Policies\ApplicantPolicy;
use App\Policies\ApplicationPolicy;
use App\Policies\CertificatePolicy;
use App\Policies\CourseModulePolicy;
use App\Policies\CoursePolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\InterviewPolicy;
use App\Policies\JobRequisitionPolicy;
use App\Policies\LearningPathCoursePolicy;
use App\Policies\LearningPathPolicy;
use App\Policies\LessonPolicy;
use App\Policies\OfferPolicy;
use App\Policies\OnboardingAssignmentPolicy;
use App\Policies\OnboardingTaskPolicy;
use App\Policies\OnboardingTemplatePolicy;
use App\Policies\PositionPolicy;
use App\Policies\QuestionOptionPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\QuizAttemptPolicy;
use App\Policies\QuizPolicy;
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

        // Training / LMS
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(CourseModule::class, CourseModulePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(QuestionOption::class, QuestionOptionPolicy::class);
        Gate::policy(QuizAttempt::class, QuizAttemptPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(Certificate::class, CertificatePolicy::class);
        Gate::policy(LearningPath::class, LearningPathPolicy::class);
        Gate::policy(LearningPathCourse::class, LearningPathCoursePolicy::class);
    }
}
