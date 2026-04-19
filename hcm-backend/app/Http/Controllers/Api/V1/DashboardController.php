<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\JobRequisition;
use App\Models\OnboardingAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', \App\Models\Tenant::class);

        $tenant    = app('tenant');
        $tenantId  = $tenant->id;
        $startOfMonth = now()->startOfMonth();

        return response()->json([
            'employees' => [
                'total'          => Employee::where('tenant_id', $tenantId)->count(),
                'active'         => Employee::where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'new_this_month' => Employee::where('tenant_id', $tenantId)->where('created_at', '>=', $startOfMonth)->count(),
            ],
            'hiring' => [
                'open_requisitions'    => JobRequisition::where('tenant_id', $tenantId)->where('status', 'approved')->count(),
                'applications_this_month' => Application::where('tenant_id', $tenantId)->where('created_at', '>=', $startOfMonth)->count(),
                'hires_this_month'     => Application::where('tenant_id', $tenantId)->where('stage', 'hired')->where('stage_changed_at', '>=', $startOfMonth)->count(),
            ],
            'onboarding' => [
                'pending'     => OnboardingAssignment::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
                'in_progress' => OnboardingAssignment::where('tenant_id', $tenantId)->where('status', 'in_progress')->count(),
                'completed'   => OnboardingAssignment::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
            ],
            'training' => [
                'active_enrollments'    => Enrollment::where('tenant_id', $tenantId)->whereIn('status', ['enrolled', 'in_progress'])->count(),
                'completions_this_month' => Enrollment::where('tenant_id', $tenantId)->where('status', 'completed')->where('completed_at', '>=', $startOfMonth)->count(),
                'certificates_issued'   => Certificate::where('tenant_id', $tenantId)->count(),
            ],
        ]);
    }

    public function hiring(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', \App\Models\Tenant::class);

        $tenantId = app('tenant')->id;

        $requisitionsByStatus = JobRequisition::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $applicationsByStage = Application::where('tenant_id', $tenantId)
            ->selectRaw('stage, count(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage');

        $hiresPerMonth = Application::where('tenant_id', $tenantId)
            ->where('stage', 'hired')
            ->whereNotNull('stage_changed_at')
            ->where('stage_changed_at', '>=', now()->subMonths(12))
            ->selectRaw("strftime('%Y-%m', stage_changed_at) as month, count(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'requisitions_by_status' => $requisitionsByStatus,
            'applications_by_stage'  => $applicationsByStage,
            'hires_per_month'        => $hiresPerMonth,
        ]);
    }

    public function onboarding(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', \App\Models\Tenant::class);

        $tenantId = app('tenant')->id;

        $byStatus = OnboardingAssignment::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total     = $byStatus->sum();
        $completed = $byStatus->get('completed', 0);
        $rate      = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        return response()->json([
            'assignments_by_status' => $byStatus,
            'completion_rate'       => $rate,
            'total_assignments'     => $total,
        ]);
    }

    public function training(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', \App\Models\Tenant::class);

        $tenantId     = app('tenant')->id;
        $startOfMonth = now()->startOfMonth();

        $enrollmentsByStatus = Enrollment::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total     = $enrollmentsByStatus->sum();
        $completed = $enrollmentsByStatus->get('completed', 0);
        $rate      = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        $coursesByCategory = Course::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        return response()->json([
            'enrollments_by_status'   => $enrollmentsByStatus,
            'completion_rate'         => $rate,
            'total_enrollments'       => $total,
            'certificates_issued'     => Certificate::where('tenant_id', $tenantId)->count(),
            'certificates_this_month' => Certificate::where('tenant_id', $tenantId)->where('issued_at', '>=', $startOfMonth)->count(),
            'courses_by_category'     => $coursesByCategory,
        ]);
    }
}
