<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;

class EnrollmentCompletionService
{
    public function __construct(
        private CertificateService $certificateService,
        private NotificationService $notificationService,
        private AuditService $auditService,
    ) {}

    public function markLessonCompleted(Enrollment $enrollment, Lesson $lesson): void
    {
        LessonProgress::updateOrCreate(
            ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
            ['tenant_id' => $enrollment->tenant_id, 'status' => 'completed', 'completed_at' => now()]
        );

        $this->recompute($enrollment);
    }

    public function recompute(Enrollment $enrollment): void
    {
        $course = $enrollment->course()->with('modules.lessons')->first();

        $requiredLessons = $course->modules
            ->flatMap->lessons
            ->where('is_required', true);

        $completedLessonIds = LessonProgress::where('enrollment_id', $enrollment->id)
            ->where('status', 'completed')
            ->pluck('lesson_id');

        $requiredTotal     = $requiredLessons->count();
        $requiredCompleted = $requiredLessons->whereIn('id', $completedLessonIds)->count();

        $percentage = $requiredTotal > 0
            ? round(($requiredCompleted / $requiredTotal) * 100, 2)
            : 0;

        $attrs = ['progress_percentage' => $percentage];

        $wasCompleted = $enrollment->status === 'completed';

        if ($requiredTotal > 0 && $requiredCompleted === $requiredTotal) {
            $attrs['status']       = 'completed';
            $attrs['completed_at'] = $enrollment->completed_at ?? now();
        } elseif ($requiredCompleted > 0 && $enrollment->status === 'enrolled') {
            $attrs['status']     = 'in_progress';
            $attrs['started_at'] = $enrollment->started_at ?? now();
        }

        $enrollment->update($attrs);

        if (! $wasCompleted && ($attrs['status'] ?? null) === 'completed') {
            $fresh = $enrollment->fresh();
            $this->certificateService->generate($fresh);

            $this->auditService->log(
                'enrollment.completed',
                $fresh,
                ['status' => 'in_progress'],
                ['status' => 'completed'],
                $fresh->tenant_id,
            );

            $userId = $fresh->employee?->user_id ?? $fresh->employee()->first()?->user_id;
            if ($userId) {
                $courseName = $fresh->course?->title ?? $fresh->course()->first()?->title ?? 'a course';
                $this->notificationService->create(
                    $fresh->tenant_id,
                    $userId,
                    'enrollment.completed',
                    'Course Completed',
                    "Congratulations! You have completed \"{$courseName}\".",
                    ['enrollment_id' => $fresh->id, 'course_id' => $fresh->course_id],
                );
            }
        }
    }
}
