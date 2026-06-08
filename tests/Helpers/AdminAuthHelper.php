<?php

namespace Tests\Helpers;

use App\Business;
use App\User;
use Spatie\Permission\Models\Role;

/**
 * Helper pra Pest matriz auth Admin Center (US-ADM-009).
 *
 * Usa firstOrCreate em vez de factory pq UserFactory está vazio no projeto
 * e Wagner user_id=1 + business_id=1 são fixos por ADR 0122.
 */
class AdminAuthHelper
{
    /**
     * Wagner canônico — user_id=1, business_id=1, role superadmin.
     * Cenário 1 (passa pelos 3 gates AND).
     */
    public static function createWagnerUser(): User
    {
        Business::firstOrCreate(
            ['id' => 1],
            ['name' => 'Wagner Teste', 'currency_id' => 1]
        );

        $user = User::firstOrCreate(
            ['id' => 1],
            [
                'username'    => 'wagner',
                'email'       => 'wagner@test.local',
                'password'    => bcrypt('secret'),
                'business_id' => 1,
                'first_name'  => 'Wagner',
                'last_name'   => 'Tester',
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'web']
        );

        if (! $user->hasRole('superadmin')) {
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Maiara — user_id=999 (mismatch wagner_user_id), com role superadmin.
     * Cenário 3: gate user_id falha mesmo com role.
     */
    public static function createMaiaraUser(): User
    {
        Business::firstOrCreate(
            ['id' => 1],
            ['name' => 'Wagner Teste', 'currency_id' => 1]
        );

        $user = User::firstOrCreate(
            ['id' => 999],
            [
                'username'    => 'maiara',
                'email'       => 'maiara@test.local',
                'password'    => bcrypt('secret'),
                'business_id' => 1,
                'first_name'  => 'Maiara',
                'last_name'   => 'Tester',
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'web']
        );
        if (! $user->hasRole('superadmin')) {
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Wagner SEM role — simula DB corruption / restore parcial.
     * Cenário 2: gate role falha mesmo com user_id+business_id corretos.
     */
    public static function createWagnerWithoutRole(): User
    {
        Business::firstOrCreate(
            ['id' => 1],
            ['name' => 'Wagner Teste', 'currency_id' => 1]
        );

        $user = User::firstOrCreate(
            ['id' => 1],
            [
                'username'    => 'wagner',
                'email'       => 'wagner@test.local',
                'password'    => bcrypt('secret'),
                'business_id' => 1,
                'first_name'  => 'Wagner',
                'last_name'   => 'Tester',
            ]
        );

        $user->roles()->detach();

        return $user;
    }
}
