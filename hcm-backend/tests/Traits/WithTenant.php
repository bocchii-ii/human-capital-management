<?php

namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait WithTenant
{
    protected Tenant $tenant;
    protected User $adminUser;

    protected function setUpTenant(): void
    {
        $this->tenant = Tenant::factory()->create();
        app()[PermissionRegistrar::class]->setPermissionsTeamId($this->tenant->id);

        $this->seedPermissions();

        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->adminUser->assignRole('HR Admin');
    }

    protected function actingAsAdmin(): static
    {
        return $this->actingAs($this->adminUser, 'sanctum');
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    protected function userWithPermission(string ...$permissions): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'hiring.requisition.view', 'hiring.requisition.create', 'hiring.requisition.edit',
            'hiring.requisition.delete', 'hiring.requisition.approve',
            'hiring.application.view', 'hiring.application.update',
            'hiring.interview.schedule', 'hiring.offer.create', 'hiring.offer.send',
            'onboarding.template.view', 'onboarding.template.manage',
            'onboarding.assignment.view', 'onboarding.assignment.manage',
            'onboarding.document.verify',
            'training.course.view', 'training.course.publish', 'training.course.manage',
            'training.enrollment.manage', 'training.report.view',
            'hr.employee.view', 'hr.employee.manage', 'hr.department.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $allPermissions = array_values($permissions);

        $roles = [
            'Super Admin'    => $allPermissions,
            'HR Admin'       => $allPermissions,
            'Hiring Manager' => [
                'hiring.requisition.view', 'hiring.requisition.create', 'hiring.requisition.edit',
                'hiring.application.view', 'hiring.application.update',
                'hiring.interview.schedule', 'hiring.offer.create', 'hiring.offer.send',
                'hr.employee.view',
            ],
            'Trainer'   => ['training.course.view', 'training.course.publish', 'training.course.manage', 'training.enrollment.manage', 'training.report.view'],
            'Employee'  => ['training.course.view', 'hr.employee.view'],
        ];

        foreach ($roles as $name => $perms) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }

    protected function withTenantHeader(array $headers = []): array
    {
        return array_merge(['X-Tenant' => $this->tenant->slug], $headers);
    }
}
