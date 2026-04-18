<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\LearningPathResource;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\LearningPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class LearningPathController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LearningPath::class);

        $tenant = app('tenant');

        $paths = LearningPath::where('tenant_id', $tenant->id)
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('target_department_id'), fn ($q) => $q->where('target_department_id', $request->target_department_id))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return LearningPathResource::collection($paths);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LearningPath::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'target_role'          => ['nullable', 'string', 'max:255'],
            'target_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active'            => ['boolean'],
        ]);

        $path = LearningPath::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new LearningPathResource($path))->response()->setStatusCode(201);
    }

    public function show(LearningPath $learningPath): LearningPathResource
    {
        $this->authorizeTenant($learningPath);
        $this->authorize('view', $learningPath);

        $learningPath->load('pathCourses.course');

        return new LearningPathResource($learningPath);
    }

    public function update(Request $request, LearningPath $learningPath): LearningPathResource
    {
        $this->authorizeTenant($learningPath);
        $this->authorize('update', $learningPath);

        $data = $request->validate([
            'title'                => ['sometimes', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'target_role'          => ['nullable', 'string', 'max:255'],
            'target_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active'            => ['boolean'],
        ]);

        $learningPath->update($data);

        return new LearningPathResource($learningPath);
    }

    public function destroy(LearningPath $learningPath): JsonResponse
    {
        $this->authorizeTenant($learningPath);
        $this->authorize('delete', $learningPath);

        $learningPath->delete();

        return response()->json(['message' => 'Learning path deleted.']);
    }

    public function assign(Request $request, LearningPath $learningPath): JsonResponse
    {
        $this->authorizeTenant($learningPath);
        $this->authorize('assign', $learningPath);

        $tenant = app('tenant');

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'due_date'    => ['nullable', 'date'],
        ]);

        abort_if(
            ! Employee::where('id', $data['employee_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Employee not found in this tenant.'
        );

        $pathCourses = $learningPath->pathCourses()->with('course')->get();

        $created  = 0;
        $skipped  = 0;
        $enrollments = [];

        DB::transaction(function () use ($pathCourses, $data, $learningPath, $tenant, &$created, &$skipped, &$enrollments) {
            foreach ($pathCourses as $pathCourse) {
                $existing = Enrollment::where('employee_id', $data['employee_id'])
                    ->where('course_id', $pathCourse->course_id)
                    ->first();

                if ($existing && $existing->status !== 'withdrawn') {
                    $skipped++;
                    $enrollments[] = $existing;
                    continue;
                }

                if ($existing && $existing->status === 'withdrawn') {
                    $existing->update([
                        'status'              => 'enrolled',
                        'learning_path_id'    => $learningPath->id,
                        'enrolled_by'         => request()->user()->id,
                        'progress_percentage' => 0.00,
                        'enrolled_at'         => now(),
                        'started_at'          => null,
                        'completed_at'        => null,
                        'due_date'            => $data['due_date'] ?? null,
                    ]);
                    $created++;
                    $enrollments[] = $existing->fresh();
                    continue;
                }

                $enrollment = Enrollment::create([
                    'tenant_id'        => $tenant->id,
                    'employee_id'      => $data['employee_id'],
                    'course_id'        => $pathCourse->course_id,
                    'learning_path_id' => $learningPath->id,
                    'enrolled_by'      => request()->user()->id,
                    'status'           => 'enrolled',
                    'enrolled_at'      => now(),
                    'due_date'         => $data['due_date'] ?? null,
                ]);
                $created++;
                $enrollments[] = $enrollment;
            }
        });

        return response()->json([
            'enrollments' => EnrollmentResource::collection(collect($enrollments)),
            'created'     => $created,
            'skipped'     => $skipped,
        ]);
    }

    private function authorizeTenant(LearningPath $learningPath): void
    {
        abort_if($learningPath->tenant_id !== app('tenant')->id, 403);
    }
}
