<?php

namespace Modules\Grow\Tests\Feature;

use App\Business;
use App\User;
use Tests\TestCase;

/**
 * Base para feature tests do módulo Grow.
 *
 * Mesmo padrão de Essentials/PontoTestCase: não usa RefreshDatabase porque o
 * UltimatePOS tem 100+ migrations + triggers MySQL incompatíveis com SQLite.
 * Roda contra o DB local quando disponível e marca skipped caso contrário.
 *
 * Grow é um módulo legado (CodeCanyon Perfect Support) com 797+ rotas em
 * Modules/Grow/Routes/web.php (a maioria comentada). A camada nova fica
 * em Modules/Grow/Http/Controllers/InstallController.php (segue
 * BaseModuleInstallController, ADR 0024).
 *
 * IMPORTANTE: o módulo está marcado como `false` em `modules_statuses.json`
 * por padrão (não habilitado em produção). Tests que dependem das rotas
 * REGISTRADAS são skipped automaticamente via `skipIfModuleDisabled()`.
 * Tests de reflexão (classe estende quem? metadados?) rodam sempre.
 */
abstract class GrowTestCase extends TestCase
{
    protected ?User $admin = null;
    protected ?Business $business = null;

    protected function actAsAdmin(): User
    {
        session()->flush();
        auth()->logout();

        try {
            if (! $this->business) {
                $this->business = Business::first();
            }
            if (! $this->business) {
                $this->markTestSkipped('Nenhum business encontrado — UltimatePOS seeder não rodou.');
            }

            if (! $this->admin) {
                $this->admin = User::where('business_id', $this->business->id)->first();
            }
        } catch (\Illuminate\Database\QueryException $e) {
            $this->markTestSkipped('DB sem tabelas do UltimatePOS (SQLite vazio): ' . $e->getMessage());
        }

        if (! $this->admin) {
            $this->markTestSkipped('Nenhum user encontrado no business.');
        }

        $this->actingAs($this->admin);
        return $this->admin;
    }

    protected function assertRedirectsToLogin($response): void
    {
        $response->assertStatus(302);
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('/login', $location, "Esperado redirect para /login, recebi: {$location}");
    }

    protected function skipIfModuleDisabled(): void
    {
        $statusFile = base_path('modules_statuses.json');
        if (! file_exists($statusFile)) {
            return;
        }

        $statuses = json_decode((string) file_get_contents($statusFile), true);
        if (! is_array($statuses)) {
            return;
        }

        if (array_key_exists('Grow', $statuses) && $statuses['Grow'] !== true) {
            $this->markTestSkipped('Modules/Grow está desativado em modules_statuses.json — rotas não registradas.');
        }
    }
}
