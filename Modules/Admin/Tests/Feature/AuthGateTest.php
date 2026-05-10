<?php

namespace Modules\Admin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pest tests pra middleware stack do Admin Center (ADR 0122 + Sprint 1 US-ADM-009).
 *
 * Cobertura crítica:
 * - tailscale-only bloqueia IP fora CIDR
 * - is-wagner bloqueia usuário não-Wagner
 * - 3 condições AND do is-wagner (user_id + business_id + role)
 * - fallback_username funciona quando user_id=1 não existe
 *
 * Pendente Sprint 1: implementar com factories após scaffold em prod.
 * Esse arquivo é placeholder pra `phpunit.xml` reconhecer Modules/Admin/Tests/.
 */
class AuthGateTest extends TestCase
{
    public function test_placeholder_scaffold_compiles(): void
    {
        $this->assertTrue(class_exists(\Modules\Admin\Http\Middleware\IsWagner::class));
        $this->assertTrue(class_exists(\Modules\Admin\Http\Middleware\TailscaleOnly::class));
        $this->assertTrue(class_exists(\Modules\Admin\Providers\AdminServiceProvider::class));
    }

    /**
     * @todo US-ADM-009 — implementar matriz 6 cenários:
     *   - Wagner Tailscale + role        → 200
     *   - Wagner Tailscale SEM role      → 403 (DB corruption)
     *   - Maiara Tailscale + role        → 403 (gate user_id)
     *   - Wagner externo (sem Tailscale) → 403 (gate IP)
     *   - sem auth Tailscale             → 403 (gate auth)
     *   - sem auth externo               → 403 (Tailscale primeiro)
     */
    public function test_todo_implementar_matriz_seis_cenarios(): void
    {
        $this->markTestSkipped('US-ADM-009 — implementar após factories user/business em homolog.');
    }
}
