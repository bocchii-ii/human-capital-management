<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $employees = Employee::where('tenant_id', $tenant->id)
            ->with(['department', 'position', 'manager'])
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('employee_number', 'like', "%{$request->search}%");
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->integer('per_page', 15));

        return EmployeeResource::collection($employees);
    }

    public function orgChart(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        // Return top-level employees (no manager) with their direct reports nested 3 levels deep
        $employees = Employee::where('tenant_id', $tenant->id)
            ->whereNull('manager_id')
            ->where('status', 'active')
            ->with('directReports.directReports.directReports')
            ->get();

        return EmployeeResource::collection($employees);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manage', Employee::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'department_id'   => ['nullable', 'exists:departments,id'],
            'position_id'     => ['nullable', 'exists:positions,id'],
            'manager_id'      => ['nullable', 'exists:employees,id'],
            'hire_date'       => ['nullable', 'date'],
            'employment_type' => ['in:full_time,part_time,contract'],
            'work_location'   => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'status'    => 'active',
        ]));

        $employee->load(['department', 'position', 'manager']);

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorizeTenant($employee);

        $employee->load(['department', 'position', 'manager', 'directReports', 'user']);

        return new EmployeeResource($employee);
    }

    public function update(Request $request, Employee $employee): EmployeeResource
    {
        $this->authorizeTenant($employee);
        $this->authorize('manage', Employee::class);

        $data = $request->validate([
            'first_name'       => ['sometimes', 'string', 'max:100'],
            'last_name'        => ['sometimes', 'string', 'max:100'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'employee_number'  => ['nullable', 'string', 'max:50'],
            'department_id'    => ['nullable', 'exists:departments,id'],
            'position_id'      => ['nullable', 'exists:positions,id'],
            'manager_id'       => ['nullable', 'exists:employees,id'],
            'hire_date'        => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date'],
            'employment_type'  => ['in:full_time,part_time,contract'],
            'status'           => ['in:active,inactive,terminated'],
            'work_location'    => ['nullable', 'string', 'max:255'],
        ]);

        $employee->update($data);

        return new EmployeeResource($employee->load(['department', 'position', 'manager']));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorizeTenant($employee);
        $this->authorize('manage', Employee::class);

        $employee->delete();

        return response()->json(['message' => 'Employee deleted.']);
    }

    private function authorizeTenant(Employee $employee): void
    {
        abort_if($employee->tenant_id !== app('tenant')->id, 403);
    }
}
