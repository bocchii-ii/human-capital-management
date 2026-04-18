<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuizAttemptResource;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Services\EnrollmentCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class QuizAttemptController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', QuizAttempt::class);

        $tenant  = app('tenant');
        $user    = $request->user();
        $canManage = $user->can('training.enrollment.manage');

        $attempts = QuizAttempt::where('tenant_id', $tenant->id)
            ->when(! $canManage, function ($q) use ($user) {
                $employeeId = $user->employee?->id;
                $q->where('employee_id', $employeeId ?? 0);
            })
            ->when($request->filled('quiz_id'), fn ($q) => $q->where('quiz_id', $request->quiz_id))
            ->when($request->filled('employee_id') && $canManage, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('passed'), fn ($q) => $q->where('passed', filter_var($request->passed, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('started_at')
            ->paginate($request->integer('per_page', 15));

        return QuizAttemptResource::collection($attempts);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', QuizAttempt::class);

        $tenant = app('tenant');
        $user   = $request->user();

        $data = $request->validate([
            'quiz_id'     => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
        ]);

        abort_if(
            ! Quiz::where('id', $data['quiz_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Quiz not found in this tenant.'
        );

        abort_if(
            ! Employee::where('id', $data['employee_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Employee not found in this tenant.'
        );

        $ownEmployeeId = $user->employee?->id;
        if ($data['employee_id'] !== $ownEmployeeId) {
            abort_if(! $user->can('training.enrollment.manage'), 403, 'Cannot create attempt for another employee.');
        }

        $quiz = Quiz::find($data['quiz_id']);

        if ($quiz->max_attempts !== null) {
            $existingCount = QuizAttempt::where('quiz_id', $quiz->id)
                ->where('employee_id', $data['employee_id'])
                ->count();

            abort_if(
                $existingCount >= $quiz->max_attempts,
                422,
                "Maximum of {$quiz->max_attempts} attempt(s) allowed for this quiz."
            );
        }

        $attemptNumber = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('employee_id', $data['employee_id'])
            ->count() + 1;

        $attempt = QuizAttempt::create([
            'tenant_id'      => $tenant->id,
            'quiz_id'        => $quiz->id,
            'employee_id'    => $data['employee_id'],
            'attempt_number' => $attemptNumber,
            'status'         => 'in_progress',
            'started_at'     => now(),
        ]);

        return (new QuizAttemptResource($attempt->load('quiz')))->response()->setStatusCode(201);
    }

    public function show(QuizAttempt $quizAttempt): QuizAttemptResource
    {
        $this->authorizeTenant($quizAttempt);
        $this->authorize('view', $quizAttempt);

        $quizAttempt->load(['quiz.questions.options', 'answers']);

        return new QuizAttemptResource($quizAttempt);
    }

    public function destroy(QuizAttempt $quizAttempt): JsonResponse
    {
        $this->authorizeTenant($quizAttempt);
        $this->authorize('delete', $quizAttempt);

        $quizAttempt->delete();

        return response()->json(['message' => 'Attempt deleted.']);
    }

    public function submit(Request $request, QuizAttempt $quizAttempt): QuizAttemptResource
    {
        $this->authorizeTenant($quizAttempt);
        $this->authorize('submit', $quizAttempt);

        abort_if($quizAttempt->status !== 'in_progress', 422, 'Attempt already submitted.');

        $data = $request->validate([
            'answers'                           => ['required', 'array'],
            'answers.*.question_id'             => ['required', 'integer'],
            'answers.*.selected_option_ids'     => ['present', 'array'],
            'answers.*.selected_option_ids.*'   => ['integer'],
        ]);

        $quiz = $quizAttempt->quiz()->with('questions.options')->first();

        $questionMap = $quiz->questions->keyBy('id');

        foreach ($data['answers'] as $answer) {
            abort_if(
                ! $questionMap->has($answer['question_id']),
                422,
                "Question {$answer['question_id']} does not belong to this quiz."
            );

            $question  = $questionMap->get($answer['question_id']);
            $optionIds = $question->options->pluck('id')->all();

            foreach ($answer['selected_option_ids'] as $optionId) {
                abort_if(
                    ! in_array($optionId, $optionIds),
                    422,
                    "Option {$optionId} does not belong to question {$answer['question_id']}."
                );
            }
        }

        DB::transaction(function () use ($quizAttempt, $data, $quiz) {
            $totalPoints  = 0;
            $earnedPoints = 0;

            $submittedByQuestionId = collect($data['answers'])->keyBy('question_id');

            foreach ($quiz->questions as $question) {
                $totalPoints += $question->points;

                $correctOptionIds = $question->options
                    ->where('is_correct', true)
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all();

                $submitted = $submittedByQuestionId->get($question->id);
                $selectedIds = $submitted
                    ? collect($submitted['selected_option_ids'])->sort()->values()->all()
                    : [];

                $isCorrect = ($correctOptionIds === $selectedIds);

                if ($isCorrect) {
                    $earnedPoints += $question->points;
                }

                QuizAttemptAnswer::create([
                    'quiz_attempt_id'     => $quizAttempt->id,
                    'question_id'         => $question->id,
                    'selected_option_ids' => $selectedIds,
                    'is_correct'          => $isCorrect,
                ]);
            }

            $scorePercentage = $totalPoints > 0
                ? round(($earnedPoints / $totalPoints) * 100, 2)
                : 0;

            $passed = $scorePercentage >= $quiz->pass_threshold;

            $quizAttempt->update([
                'status'           => 'submitted',
                'score_percentage' => $scorePercentage,
                'passed'           => $passed,
                'submitted_at'     => now(),
            ]);
        });

        // Auto-advance enrollment progress when the quiz was passed
        $quizAttempt->refresh();
        if ($quizAttempt->passed) {
            $lesson = $quiz->lesson;
            if ($lesson) {
                $enrollment = Enrollment::where('employee_id', $quizAttempt->employee_id)
                    ->whereHas('course.modules.lessons', fn ($q) => $q->where('lessons.id', $lesson->id))
                    ->where('status', '!=', 'withdrawn')
                    ->first();

                if ($enrollment) {
                    (new EnrollmentCompletionService())->markLessonCompleted($enrollment, $lesson);
                }
            }
        }

        return new QuizAttemptResource($quizAttempt->fresh()->load('answers'));
    }

    private function authorizeTenant(QuizAttempt $quizAttempt): void
    {
        abort_if($quizAttempt->tenant_id !== app('tenant')->id, 403);
    }
}
