<?php

namespace Modules\Essentials\Tests\Feature;

use App\Business;
use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Base para feature tests do módulo Essentials.
 *
 * Mesmo padrão do PontoTestCase: não usa RefreshDatabase (100+ migrations +
 * triggers MySQL do UltimatePOS). Roda contra o DB local `oimpresso` com
 * cleanup manual quando necessário.
 */
abstract class EssentialsTestCase extends TestCase
{
    protected ?User $admin = null;
    protected ?Business $business = null;

    protected function actAsAdmin(): User
    {
        // Começa cada teste com sessão limpa — SetSessionData popula o resto
        session()->flush();
        auth()->logout();

        if (!$this->business) {
            $this->business = Business::first();
        }
        if (!$this->business) {
            $this->markTestSkipped('Nenhum business encontrado — precisa de seeder do UltimatePOS rodado.');
        }

        if (!$this->admin) {
            $this->admin = User::where('business_id', $this->business->id)->first();
        }
        if (!$this->admin) {
            $this->markTestSkipped('Nenhum user encontrado no business.');
        }

        $this->ensureEssentialsPermissions($this->business->id);

        $this->actingAs($this->admin);
        return $this->admin;
    }

    protected function ensureEssentialsPermissions(int $businessId): void
    {
        $perms = [
            'essentials.assign_todos',
            'essentials.add_todos',
            'essentials.edit_todos',
            'essentials.delete_todos',
            'essentials.create_message',
            'essentials.view_message',
        ];
        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::where('name', "Admin#{$businessId}")->first();
        if ($role) {
            foreach ($perms as $name) {
                $p = Permission::where('name', $name)->first();
                if ($p && !$role->hasPermissionTo($p)) {
                    $role->givePermissionTo($p);
                }
            }
        }
    }

    protected function assertInertiaComponent($response, string $component): void
    {
        $response->assertStatus(200);
        $response->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', $component);
    }

    protected function inertiaGet(string $url, array $queryParams = [])
    {
        if (!empty($queryParams)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
        }

        // Inertia retorna 409 se X-Inertia-Version do request não bate com a
        // versão real do servidor (hash do manifest). Pega a versão atual
        // via middleware pra evitar falsos 409.
        $version = $this->currentInertiaVersion();

        return $this->withHeaders([
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => $version ?? '',
            'Accept'            => 'text/html',
        ])->get($url);
    }

    protected function currentInertiaVersion(): ?string
    {
        $middleware = new \App\Http\Middleware\HandleInertiaRequests;
        try {
            return $middleware->version(request());
        } catch (\Throwable $e) {
            return null;
        }
    }
}
