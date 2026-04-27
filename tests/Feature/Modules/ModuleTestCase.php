<?php

namespace Tests\Feature\Modules;

use App\Business;
use App\User;
use Tests\TestCase;

/**
 * Base TestCase compartilhado pelos testes dos módulos legados.
 *
 * Centraliza criação de business multi-tenant, user superadmin e
 * user comum para evitar duplicação no batch 7.
 */
abstract class ModuleTestCase extends TestCase
{
    protected ?Business $business = null;

    protected function makeBusiness(array $overrides = []): Business
    {
        return Business::create(array_merge([
            'name' => 'Empresa Teste',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'owner_id' => 0,
            'time_zone' => 'America/Sao_Paulo',
            'fy_start_month' => 1,
            'accounting_method' => 'fifo',
        ], $overrides));
    }

    protected function makeUser(array $overrides = []): User
    {
        $business = $this->business ?? ($this->business = $this->makeBusiness());

        return User::create(array_merge([
            'business_id' => $business->id,
            'surname' => 'Sr.',
            'first_name' => 'Usuario',
            'last_name' => 'Comum',
            'username' => 'comum_' . uniqid(),
            'email' => 'comum_' . uniqid() . '@teste.local',
            'password' => bcrypt('secret'),
            'language' => 'pt',
            'allow_login' => 1,
        ], $overrides));
    }

    protected function makeSuperadmin(): User
    {
        $username = config('constants.administrator_usernames');
        $username = $username ? explode(',', $username)[0] : 'WR23';

        return $this->makeUser([
            'username' => $username,
            'first_name' => 'Super',
            'last_name' => 'Admin',
        ]);
    }
}
