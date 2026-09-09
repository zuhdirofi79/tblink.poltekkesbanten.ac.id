<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $u = User::firstOrNew(['username' => 'admin']);
        $u->name = 'Admin';
        $u->password = bcrypt(env('APP_DEBUG', false) ? 'password' : 'wcT6XWB2WXds3tG');
        $u->save();
        $u->givePermissionTo('User Management');
        $u->givePermissionTo('Superadmin');
    }
}
