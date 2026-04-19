<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Hiring
            'hiring.requisition.view',
            'hiring.requisition.create',
            'hiring.requisition.edit',
            'hiring.requisition.delete',
            'hiring.requisition.approve',
            'hiring.application.view',
            'hiring.application.update',
            'hiring.interview.schedule',
            'hiring.offer.create',
            'hiring.offer.send',

            // Onboarding
            'onboarding.template.view',
            'onboarding.template.manage',
            'onboarding.assignment.view',
            'onboarding.assignment.manage',
            'onboarding.document.verify',

            // Training
            'training.course.view',
            'training.course.publish',
            'training.course.manage',
            'training.enrollment.manage',
            'training.report.view',

            // Core HR
            'hr.employee.view',
            'hr.employee.manage',
            'hr.department.manage',

            // Reporting & Audit
            'reporting.dashboard.view',
            'audit.logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'Super Admin' => $permissions,
            'HR Admin'    => $permissions,
            'Hiring Manager' => [
                'hiring.requisition.view', 'hiring.requisition.create', 'hiring.requisition.edit',
                'hiring.application.view', 'hiring.application.update',
                'hiring.interview.schedule', 'hiring.offer.create', 'hiring.offer.send',
                'hr.employee.view',
            ],
            'Trainer' => [
                'training.course.view', 'training.course.publish', 'training.course.manage',
                'training.enrollment.manage', 'training.report.view', 'reporting.dashboard.view',
            ],
            'Employee' => [
                'training.course.view',
                'hr.employee.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
