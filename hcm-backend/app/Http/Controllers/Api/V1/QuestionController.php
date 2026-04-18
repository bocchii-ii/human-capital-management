<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuestionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Question::class);

        $tenant = app('tenant');

        $questions = Question::where('tenant_id', $tenant->id)
            ->when($request->filled('quiz_id'), fn ($q) => $q->where('quiz_id', $request->quiz_id))
            ->when($request->filled('search'), fn ($q) => $q->where('question_text', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 15));

        return QuestionResource::collection($questions);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Question::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'quiz_id'       => ['required', 'integer'],
            'question_text' => ['required', 'string'],
            'question_type' => ['required', 'in:single_choice,multiple_choice,true_false'],
            'points'        => ['nullable', 'integer', 'min:1'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
            'explanation'   => ['nullable', 'string'],
        ]);

        abort_if(
            ! Quiz::where('id', $data['quiz_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Quiz not found in this tenant.'
        );

        $question = Question::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new QuestionResource($question))->response()->setStatusCode(201);
    }

    public function show(Question $question): QuestionResource
    {
        $this->authorizeTenant($question);
        $this->authorize('view', $question);

        $question->load('options');

        return new QuestionResource($question);
    }

    public function update(Request $request, Question $question): QuestionResource
    {
        $this->authorizeTenant($question);
        $this->authorize('update', $question);

        $data = $request->validate([
            'question_text' => ['sometimes', 'string'],
            'question_type' => ['sometimes', 'in:single_choice,multiple_choice,true_false'],
            'points'        => ['nullable', 'integer', 'min:1'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
            'explanation'   => ['nullable', 'string'],
        ]);

        $question->update($data);

        return new QuestionResource($question->load('options'));
    }

    public function destroy(Question $question): JsonResponse
    {
        $this->authorizeTenant($question);
        $this->authorize('delete', $question);

        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }

    private function authorizeTenant(Question $question): void
    {
        abort_if($question->tenant_id !== app('tenant')->id, 403);
    }
}
