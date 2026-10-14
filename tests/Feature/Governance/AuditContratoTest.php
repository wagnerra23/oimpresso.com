<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Governance\Http\Controllers\AuditController;
use Modules\Governance\Services\AuditDrillDownService;

/**
 * governance/Audit — contrato de servidor do Audit.casos.md (UC-GAUD-03, -07, -08).
 *
 * De onde os casos vêm: Audit.charter.md (lei) · SPEC US-GOV-003 · RUNBOOK-audit.md ·
 * governance-audit-gap.md · SCOPE.md (routes). O controller entra só para CONFIRMAR.
 *
 * Roda sem banco de propósito: a lane sqlite (`.github/ci-sqlite-pest.list`) sobe
 * sem migrate, e `mcp_audit_log` exige FK real para `users`/`business` — inserir ali
 * deixaria lixo append-only (trigger ADR 0084) em banco persistente. Por isso:
 *   - rota e middleware são perguntados ao REGISTRY vivo (`gatherMiddleware()`),
 *     não ao texto do routes.php (§5 2026-07-28);
 *   - os KPIs são medidos no `kpisFor()` real, que é função pura sobre a amostra.
 *
 * ⚠️ O que NÃO prova: o teto de 200 no SQL, o recorte por período e o fail-safe do
 * actor (slug inválido → zero linhas). Esses dependem de banco e ficaram como
 * [BACKLOG] no casos.md — não estão cobertos aqui.
 *
 * ⚠️ Nenhum `toContain(needle, mensagem)`: o `toContain` do Pest é variádico e o
 * 2º argumento vira needle (§5 2026-09-05). Onde há mensagem, assert do PHPUnit.
 */

/** Middleware efetivo da rota nomeada, resolvido pelo registry. */
function auditMiddlewareDaRota(string $nome): array
{
    $rota = Route::getRoutes()->getByName($nome);
    test()->assertNotNull($rota, "rota `{$nome}` não está registrada no router");

    return $rota->gatherMiddleware();
}

describe('governance/Audit — contrato de servidor', function () {

    it('UC-GAUD-03 · os 3 KPIs são contados sobre a amostra entregue (total · errors · users distintos)', function () {
        // Amostra discriminante: 3 linhas, 2 usuários, 1 erro — nenhum valor coincide
        // com outro, então trocar um KPI pelo vizinho derruba o assert.
        $amostra = collect([
            (object) ['user_id' => 7, 'status' => 'ok'],
            (object) ['user_id' => 7, 'status' => 'error'],
            (object) ['user_id' => 9, 'status' => 'ok'],
        ]);

        $kpis = (new AuditDrillDownService())->kpisFor($amostra);

        expect($kpis)->toBe(['total' => 3, 'errors' => 1, 'unique_users' => 2]);
    });

    it('UC-GAUD-03 · amostra vazia dá zero nos 3 KPIs (não inventa movimento)', function () {
        $kpis = (new AuditDrillDownService())->kpisFor(collect());

        expect($kpis)->toBe(['total' => 0, 'errors' => 0, 'unique_users' => 0]);
    });

    it('UC-GAUD-07 · a rota exige login, a permission governance.audit.view e o throttle de 30/min', function () {
        $mw = auditMiddlewareDaRota('governance.audit.index');

        $this->assertContains('auth', $mw, 'rota do audit sem `auth`');
        $this->assertContains('can:governance.audit.view', $mw, 'rota do audit sem o gate `can:governance.audit.view`');
        $this->assertContains('throttle:30,1', $mw, 'rota do audit sem o throttle 30/min do charter/SCOPE');
    });

    it('UC-GAUD-08 · nenhuma rota de /governance/audit escreve — só GET/HEAD', function () {
        $rotas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'governance/audit'));

        // Controle positivo: sem isto, "nenhuma rota escreve" passaria por vácuo.
        $this->assertGreaterThan(0, $rotas->count(), 'nenhuma rota governance/audit* registrada — o teste estaria medindo o vazio');

        $verbos = $rotas->flatMap(fn ($r) => $r->methods())->unique()->sort()->values()->all();
        $this->assertSame(['GET', 'HEAD'], $verbos, 'rota de escrita em /governance/audit: o audit log é read-only (ADR 0084)');
    });

    it('UC-GAUD-08 · o controller do audit não expõe ação além da leitura', function () {
        $publicos = collect((new ReflectionClass(AuditController::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === AuditController::class)
            ->map(fn (ReflectionMethod $m) => $m->getName())
            ->reject(fn (string $n) => $n === '__construct')
            ->values()
            ->all();

        $this->assertSame(['index'], $publicos, 'AuditController ganhou ação pública além de index()');
    });
});
