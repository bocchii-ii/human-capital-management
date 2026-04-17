<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $tenant = \App\Models\Tenant::create([
            'name'      => 'Demo Company',
            'slug'      => 'demo',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name'      => 'Super Admin',
            'email'     => 'admin@demo.com',
            'password'  => bcrypt('password'),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $admin->assignRole('Super Admin');
    }
}
