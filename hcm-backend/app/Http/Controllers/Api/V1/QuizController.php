<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuizResource;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuizController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quiz::class);

        $tenant = app('tenant');

        $quizzes = Quiz::where('tenant_id', $tenant->id)
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->lesson_id))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 15));

        return QuizResource::collection($quizzes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Quiz::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'lesson_id'          => ['required', 'integer'],
            'title'              => ['nullable', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'pass_threshold'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_attempts'       => ['nullable', 'integer', 'min:1'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'shuffle_questions'  => ['boolean'],
        ]);

        abort_if(
            ! Lesson::where('id', $data['lesson_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Lesson not found in this tenant.'
        );

        abort_if(
            Quiz::where('lesson_id', $data['lesson_id'])->exists(),
            422,
            'A quiz already exists for this lesson.'
        );

        $quiz = Quiz::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new QuizResource($quiz))->response()->setStatusCode(201);
    }

    public function show(Quiz $quiz): QuizResource
    {
        $this->authorizeTenant($quiz);
        $this->authorize('view', $quiz);

        $quiz->load('questions.options');

        return new QuizResource($quiz);
    }

    public function update(Request $request, Quiz $quiz): QuizResource
    {
        $this->authorizeTenant($quiz);
        $this->authorize('update', $quiz);

        $data = $request->validate([
            'title'              => ['nullable', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'pass_threshold'     => ['sometimes', 'integer', 'min:0', 'max:100'],
            'max_attempts'       => ['nullable', 'integer', 'min:1'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'shuffle_questions'  => ['boolean'],
        ]);

        $quiz->update($data);

        return new QuizResource($quiz->load('questions.options'));
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        $this->authorizeTenant($quiz);
        $this->authorize('delete', $quiz);

        $quiz->delete();

        return response()->json(['message' => 'Quiz deleted.']);
    }

    private function authorizeTenant(Quiz $quiz): void
    {
        abort_if($quiz->tenant_id !== app('tenant')->id, 403);
    }
}
