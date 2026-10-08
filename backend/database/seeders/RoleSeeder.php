<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const ROLES = ['admin', 'qa_lead', 'qa_reviewer', 'field_ops', 'read_only'];

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

            $email = "{$role}@metrix.test";

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => Str::headline($role),
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }
}
