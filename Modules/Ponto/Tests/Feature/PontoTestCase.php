<?php

namespace Modules\Ponto\Tests\Feature;

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Base para feature tests do PontoWR2.
 *
 * Fornece:
 *  - `actAsAdmin()` — loga um user com role Admin#{business_id} e todas permissões ponto.*
 *  - Sessão já configurada com business.id (scope UltimatePOS)
 *
 * NÃO usa RefreshDatabase porque o UltimatePOS tem 100+ migrations + triggers
 * MySQL que não rodam bem em memory/sqlite. Testes rodam contra o DB real
 * (dev local — `oimpresso`) com cleanup manual por test.
 */
abstract class PontoTestCase extends TestCase
{
    protected ?User $admin = null;
    protected ?Business $business = null;

    protected function actAsAdmin(): User
    {
        if ($this->admin) {
            $this->actingAs($this->admin);
            return $this->admin;
        }

        // Pega o business existente ou cria um fake leve
        $this->business = Business::first();
        if (!$this->business) {
            $this->markTestSkipped('Nenhum business encontrado — precisa de seeder do UltimatePOS rodado.');
        }

        // Pega um user admin existente OU o primeiro user do business
        $this->admin = User::where('business_id', $this->business->id)->first();
        if (!$this->admin) {
            $this->markTestSkipped('Nenhum user encontrado no business.');
        }

        // Garante permissões ponto.* registradas e concedidas ao role Admin#{business_id}
        $this->ensurePontoPermissions($this->business->id);

        // Seta a session como se tivesse logado via UltimatePOS
        session([
            'user.business_id' => $this->business->id,
            'user.id'          => $this->admin->id,
            'business.id'      => $this->business->id,
            'business.name'    => $this->business->name,
            'is_admin'         => true,
        ]);

        $this->actingAs($this->admin);
        return $this->admin;
    }

    protected function ensurePontoPermissions(int $businessId): void
    {
        $perms = [
            'ponto.access', 'ponto.colaboradores.manage', 'ponto.aprovacoes.manage',
            'ponto.relatorios.view', 'ponto.configuracoes.manage',
        ];
        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        // A role PODE não existir na lane de CI (banco sem o seeder do UltimatePOS).
        // A 1ª versão fazia `if ($role) {...}` e, sem role, saía em silêncio SEM conceder
        // permissão nenhuma — todo teste que exige `ponto.*` caía em 403 e a lane inteira
        // ficava vermelha por SETUP, não por comportamento. Medido 2026-07-27 na lane
        // `PHP / Pest (Ponto · MySQL)`: 12+ testes falhando em ~0,19s cada, todos 403.
        //
        // Criar a role aqui segue a proibição Tier 0 de roles Spatie no UltimatePOS:
        // `roles.business_id` é NOT NULL com FK, e o nome leva o sufixo `#{business_id}`
        // (role global sem business_id viola a FK — lição do hotfix #624).
        if (!$role) {
            $attrs = ['name' => "Admin#{$businessId}", 'guard_name' => 'web'];
            if (Schema::hasColumn('roles', 'business_id')) $attrs['business_id'] = $businessId;
            $role = Role::firstOrCreate($attrs);
        }
        foreach ($perms as $name) {
            $p = Permission::where('name', $name)->first();
            if ($p && !$role->hasPermissionTo($p)) $role->givePermissionTo($p);
        }
        // Sem isto o usuário continua sem a permissão mesmo com a role povoada.
        if ($this->admin && !$this->admin->hasRole($role)) $this->admin->assignRole($role);
    }

    /**
     * Asserção custom: resposta é Inertia e renderiza o component esperado.
     */
    protected function assertInertiaComponent($response, string $component): void
    {
        $response->assertStatus(200);
        $response->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', $component);
    }

    /**
     * Envia request com header Inertia para garantir JSON response.
     *
     * X-Inertia-Version precisa bater com o que HandleInertiaRequests::version()
     * calcula (md5 de build-inertia/manifest.json). Sem bater, o Inertia retorna
     * 409 + X-Inertia-Location pra forcar reload no client.
     */
    protected function inertiaGet(string $url, array $queryParams = [])
    {
        if (!empty($queryParams)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
        }

        $manifestPath = public_path('build-inertia/manifest.json');
        $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

        return $this->withHeaders([
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => $version,
            'Accept'            => 'text/html',
        ])->get($url);
    }
}
