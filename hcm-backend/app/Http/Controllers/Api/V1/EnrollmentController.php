<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Services\CertificateService;
use App\Services\EnrollmentCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnrollmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Enrollment::class);

        $tenant    = app('tenant');
        $user      = $request->user();
        $canManage = $user->can('training.enrollment.manage');

        $enrollments = Enrollment::where('tenant_id', $tenant->id)
            ->when(! $canManage, fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0))
            ->when($request->filled('employee_id') && $canManage, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->course_id))
            ->when($request->filled('learning_path_id'), fn ($q) => $q->where('learning_path_id', $request->learning_path_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->with(['employee', 'course'])
            ->orderByDesc('enrolled_at')
            ->paginate($request->integer('per_page', 15));

        return EnrollmentResource::collection($enrollments);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Enrollment::class);

        $tenant    = app('tenant');
        $user      = $request->user();
        $canManage = $user->can('training.enrollment.manage');

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'course_id'   => ['required', 'integer'],
            'due_date'    => ['nullable', 'date'],
        ]);

        abort_if(
            ! Employee::where('id', $data['employee_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Employee not found in this tenant.'
        );

        abort_if(
            ! Course::where('id', $data['course_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Course not found in this tenant.'
        );

        // Self-enroll check: non-managers can only enroll their own employee record
        if (! $canManage) {
            abort_if(
                $user->employee?->id !== $data['employee_id'],
                403,
                'You can only enroll yourself.'
            );
        }

        $course = Course::find($data['course_id']);
        abort_if($course->status !== 'published', 422, 'Course must be published to enroll.');

        $existing = Enrollment::where('employee_id', $data['employee_id'])
            ->where('course_id', $data['course_id'])
            ->first();

        if ($existing) {
            abort_if($existing->status !== 'withdrawn', 422, 'Already enrolled in this course.');

            // Reactivate withdrawn enrollment
            $existing->update([
                'status'              => 'enrolled',
                'enrolled_by'         => $user->id,
                'progress_percentage' => 0.00,
                'enrolled_at'         => now(),
                'started_at'          => null,
                'completed_at'        => null,
                'due_date'            => $data['due_date'] ?? null,
            ]);

            return (new EnrollmentResource($existing->load(['employee', 'course'])))->response()->setStatusCode(201);
        }

        $enrollment = Enrollment::create([
            'tenant_id'           => $tenant->id,
            'employee_id'         => $data['employee_id'],
            'course_id'           => $data['course_id'],
            'enrolled_by'         => $user->id,
            'status'              => 'enrolled',
            'progress_percentage' => 0.00,
            'enrolled_at'         => now(),
            'due_date'            => $data['due_date'] ?? null,
        ]);

        return (new EnrollmentResource($enrollment->load(['employee', 'course'])))->response()->setStatusCode(201);
    }

    public function show(Enrollment $enrollment): EnrollmentResource
    {
        $this->authorizeTenant($enrollment);
        $this->authorize('view', $enrollment);

        $enrollment->load(['employee', 'course.modules.lessons', 'lessonProgress']);

        return new EnrollmentResource($enrollment);
    }

    public function update(Request $request, Enrollment $enrollment): EnrollmentResource
    {
        $this->authorizeTenant($enrollment);
        $this->authorize('update', $enrollment);

        $data = $request->validate([
            'due_date' => ['nullable', 'date'],
        ]);

        $enrollment->update($data);

        return new EnrollmentResource($enrollment->load(['employee', 'course']));
    }

    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $this->authorizeTenant($enrollment);
        $this->authorize('delete', $enrollment);

        $enrollment->delete();

        return response()->json(['message' => 'Enrollment deleted.']);
    }

    public function start(Request $request, Enrollment $enrollment): EnrollmentResource
    {
        $this->authorizeTenant($enrollment);
        $this->authorize('start', $enrollment);

        abort_if($enrollment->status !== 'enrolled', 422, 'Enrollment is not in the enrolled state.');

        $enrollment->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        return new EnrollmentResource($enrollment->load(['employee', 'course']));
    }

    public function withdraw(Request $request, Enrollment $enrollment): EnrollmentResource
    {
        $this->authorizeTenant($enrollment);
        $this->authorize('withdraw', $enrollment);

        abort_if($enrollment->status === 'completed', 422, 'Cannot withdraw a completed enrollment.');

        $enrollment->update(['status' => 'withdrawn']);

        return new EnrollmentResource($enrollment->load(['employee', 'course']));
    }

    public function completeLesson(
        Request $request,
        Enrollment $enrollment,
        Lesson $lesson,
        EnrollmentCompletionService $completionService
    ): EnrollmentResource {
        $this->authorizeTenant($enrollment);
        $this->authorize('completeLesson', $enrollment);

        abort_if(
            in_array($enrollment->status, ['withdrawn', 'completed']),
            422,
            'Enrollment is not active.'
        );

        // Verify lesson belongs to this enrollment's course
        $course = $enrollment->course()->with('modules.lessons')->first();
        $lessonIds = $course->modules->flatMap->lessons->pluck('id');

        abort_if(
            ! $lessonIds->contains($lesson->id),
            422,
            'Lesson does not belong to this enrollment\'s course.'
        );

        // Quiz-lesson gate: must have a passed attempt
        if ($lesson->content_type === 'quiz') {
            $quiz = $lesson->quiz;
            abort_if($quiz === null, 422, 'Quiz configuration missing for this lesson.');

            $hasPassed = QuizAttempt::where('quiz_id', $quiz->id)
                ->where('employee_id', $enrollment->employee_id)
                ->where('passed', true)
                ->exists();

            abort_if(! $hasPassed, 422, 'Pass the quiz before marking the lesson complete.');
        }

        $completionService->markLessonCompleted($enrollment, $lesson);

        return new EnrollmentResource($enrollment->fresh()->load(['employee', 'course', 'lessonProgress']));
    }

    public function issueCertificate(
        Request $request,
        Enrollment $enrollment,
        CertificateService $certificateService
    ): CertificateResource {
        $this->authorizeTenant($enrollment);
        $this->authorize('issueCertificate', $enrollment);

        abort_unless($enrollment->status === 'completed', 422, 'Enrollment must be completed to issue a certificate.');

        $certificate = $certificateService->generate($enrollment);

        return new CertificateResource($certificate->load(['employee', 'course']));
    }

    private function authorizeTenant(Enrollment $enrollment): void
    {
        abort_if($enrollment->tenant_id !== app('tenant')->id, 403);
    }
}
