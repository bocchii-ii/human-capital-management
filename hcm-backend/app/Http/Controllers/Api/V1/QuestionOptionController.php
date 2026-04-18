<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionOptionResource;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuestionOptionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', QuestionOption::class);

        $tenant = app('tenant');

        $options = QuestionOption::where('tenant_id', $tenant->id)
            ->when($request->filled('question_id'), fn ($q) => $q->where('question_id', $request->question_id))
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 15));

        return QuestionOptionResource::collection($options);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', QuestionOption::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_text' => ['required', 'string', 'max:500'],
            'is_correct'  => ['boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        abort_if(
            ! Question::where('id', $data['question_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Question not found in this tenant.'
        );

        $option = QuestionOption::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new QuestionOptionResource($option))->response()->setStatusCode(201);
    }

    public function show(QuestionOption $questionOption): QuestionOptionResource
    {
        $this->authorizeTenant($questionOption);
        $this->authorize('view', $questionOption);

        return new QuestionOptionResource($questionOption);
    }

    public function update(Request $request, QuestionOption $questionOption): QuestionOptionResource
    {
        $this->authorizeTenant($questionOption);
        $this->authorize('update', $questionOption);

        $data = $request->validate([
            'option_text' => ['sometimes', 'string', 'max:500'],
            'is_correct'  => ['boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $questionOption->update($data);

        return new QuestionOptionResource($questionOption);
    }

    public function destroy(QuestionOption $questionOption): JsonResponse
    {
        $this->authorizeTenant($questionOption);
        $this->authorize('delete', $questionOption);

        $questionOption->delete();

        return response()->json(['message' => 'Option deleted.']);
    }

    private function authorizeTenant(QuestionOption $questionOption): void
    {
        abort_if($questionOption->tenant_id !== app('tenant')->id, 403);
    }
}
