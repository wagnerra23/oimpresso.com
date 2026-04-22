<?php

namespace Modules\PontoWr2\Tests\Feature;

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
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
        $role = Role::where('name', "Admin#{$businessId}")->first();
        if ($role) {
            foreach ($perms as $name) {
                $p = Permission::where('name', $name)->first();
                if ($p && !$role->hasPermissionTo($p)) $role->givePermissionTo($p);
            }
        }
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
     */
    protected function inertiaGet(string $url, array $queryParams = [])
    {
        if (!empty($queryParams)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
        }
        return $this->withHeaders([
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => '1',
            'Accept'            => 'text/html',
        ])->get($url);
    }
}
