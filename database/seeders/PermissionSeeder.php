<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'User Management', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Pasien', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Pendamping', 'guard_name' => 'web']);
    }
}
