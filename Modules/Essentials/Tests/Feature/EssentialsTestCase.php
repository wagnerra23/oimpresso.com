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
        if ($this->admin) {
            $this->actingAs($this->admin);
            $this->primeSession();
            return $this->admin;
        }

        $this->business = Business::first();
        if (!$this->business) {
            $this->markTestSkipped('Nenhum business encontrado — precisa de seeder do UltimatePOS rodado.');
        }

        $this->admin = User::where('business_id', $this->business->id)->first();
        if (!$this->admin) {
            $this->markTestSkipped('Nenhum user encontrado no business.');
        }

        $this->ensureEssentialsPermissions($this->business->id);
        $this->primeSession();
        $this->actingAs($this->admin);
        return $this->admin;
    }

    protected function primeSession(): void
    {
        session([
            'user.business_id' => $this->business->id,
            'user.id'          => $this->admin->id,
            'business.id'      => $this->business->id,
            'business.name'    => $this->business->name,
            'is_admin'         => true,
        ]);
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
        return $this->withHeaders([
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => '1',
            'Accept'            => 'text/html',
        ])->get($url);
    }
}
