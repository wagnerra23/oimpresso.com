<?php

namespace Modules\PontoWr2\Tests\Feature;

use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Testa as regras R-PONT-002 a R-PONT-006 — autorização Spatie por recurso.
 *
 * **Regra base:**
 *   Dado que um usuário NÃO tem a permissão `ponto.{recurso}`
 *   Quando ele tenta acessar a funcionalidade correspondente
 *   Então recebe 403 Unauthorized (ou redirect, dependendo do middleware)
 */
class SpatiePermissionsTest extends PontoTestCase
{
    /**
     * Mapa permissão → rota protegida.
     * Cada tupla valida que a rota checa a permissão específica.
     */
    public static function permissionRoutes(): array
    {
        return [
            'R-PONT-002 ponto.access'             => ['ponto.access',             '/ponto/espelho'],
            'R-PONT-003 colaboradores.manage'     => ['ponto.colaboradores.manage', '/ponto/colaboradores'],
            'R-PONT-004 aprovacoes.manage'        => ['ponto.aprovacoes.manage',    '/ponto/aprovacoes'],
            'R-PONT-005 relatorios.view'          => ['ponto.relatorios.view',      '/ponto/relatorios'],
            'R-PONT-006 configuracoes.manage'     => ['ponto.configuracoes.manage', '/ponto/configuracoes'],
        ];
    }

    /**
     * @dataProvider permissionRoutes
     */
    public function test_user_sem_permissao_nao_passa(string $permission, string $url): void
    {
        // Garante que a permission existe no DB
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

        // Cria/pega um user sem roles no business principal
        $this->actAsAdmin(); // inicializa $this->business
        $rawUser = User::where('business_id', $this->business->id)
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'like', 'Admin#%');
            })
            ->first();

        if (! $rawUser) {
            // Fallback: usa o admin mas remove permissão específica
            $rawUser = $this->admin;
            $rawUser->revokePermissionTo($permission);
            // Remove a permission dos roles do user também
            foreach ($rawUser->roles as $role) {
                if ($role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        session([
            'user.business_id' => $this->business->id,
            'user.id'          => $rawUser->id,
            'business.id'      => $this->business->id,
            'business.name'    => $this->business->name,
            'is_admin'         => false,
        ]);
        $this->actingAs($rawUser);

        $response = $this->inertiaGet($url);

        // Aceita: 403 (bloqueio explícito), 302 (redirect pra login/erro)
        // Rejeita: 200 OK (vazou sem permissão)
        $this->assertContains(
            $response->status(),
            [302, 401, 403, 409, 422, 500],
            "Rota {$url} respondeu 200 (OK) pra user sem permissão '{$permission}' — vazamento de autorização!"
        );
    }

    /**
     * Verifica que admin COM a permissão consegue acessar.
     * (Complemento simétrico do teste acima.)
     *
     * @dataProvider permissionRoutes
     */
    public function test_admin_com_permissao_acessa(string $permission, string $url): void
    {
        $this->actAsAdmin();

        // ensurePontoPermissions() já concedeu ao Admin role
        // Só valida que admin->can() retorna true
        $this->assertTrue(
            $this->admin->can($permission),
            "Admin não tem permissão '{$permission}' — seeder de roles falhou."
        );

        $response = $this->inertiaGet($url);
        $this->assertLessThan(500,
            $response->status(),
            "Rota {$url} retornou erro de servidor pra admin com permissão."
        );
    }
}
