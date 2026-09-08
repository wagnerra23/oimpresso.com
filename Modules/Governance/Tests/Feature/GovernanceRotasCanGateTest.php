<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Governance — gate `can:` por ROTA (ADR 0392 §D-D passo 2, aplicado 2026-09-08).
 *
 * O QUE ESTE TESTE MEDE, e por que assim:
 *   Ele pergunta ao REGISTRY DE ROTAS do Laravel (`Route::getRoutes()` +
 *   `gatherMiddleware()`), não ao TEXTO do `routes.php`. Registro é pergunta do
 *   registry vivo — grep de arquivo mediria o disco, não o que a aplicação
 *   resolveu (§5 2026-07-28, "teste que afirma registrado medindo o disco").
 *
 *   Roda na lane sqlite (`.github/ci-sqlite-pest.list`), que sobe SEM migrate:
 *   por isso o teste não usa `actingAs` nem toca banco. Ele prova que o
 *   middleware está ARMADO na rota; quem executa a decisão é o Laravel.
 *
 * ⚠️ O QUE ELE NÃO PROVA — declarado para ninguém ler verde onde não há:
 *   Que o acesso está FECHADO. `AuthServiceProvider` registra um `Gate::before`
 *   que devolve `true` para quem tem a role `Admin#{business_id}` em qualquer
 *   ability fora de backup/superadmin/manage_modules — medido em 2026-09-08.
 *   Logo `can:` barra usuário não-admin sem a permission, e NÃO barra o admin
 *   de um business. Fechar aquele caminho é o passo 1 da ADR 0392 (o conflito
 *   A×B da CONCESSÃO), decisão [W] em aberto.
 *
 * Refs:
 *   - memory/decisions/0392-fronteira-governance-audiencia-enforcement-na-concessao.md
 *   - memory/decisions/0393-governanca-da-empresa-aparece-no-fluxo.md
 *   - Modules/Governance/Http/Controllers/DataController.php (user_permissions)
 */

/** Middleware efetivo da rota nomeada, resolvido pelo registry (não pelo arquivo). */
function govMiddlewareDaRota(string $nome): array
{
    $rota = Route::getRoutes()->getByName($nome);

    expect($rota)->not->toBeNull("rota `{$nome}` não está registrada no router");

    return $rota->gatherMiddleware();
}

describe('Governance — gate can: armado por rota', function () {

    it('cada tela sem gate próprio passa a exigir a permission da sidebar', function () {
        $esperado = [
            'governance.policies.index'      => 'can:governance.dashboard.view',
            'governance.policies.toggle'     => 'can:governance.policies.edit',
            'governance.audit.index'         => 'can:governance.audit.view',
            'governance.drift.index'         => 'can:governance.dashboard.view',
            'governance.module-grades.index' => 'can:governance.dashboard.view',
            'governance.module-grades.show'  => 'can:governance.dashboard.view',
            'governance.ds-rollout.index'    => 'can:governance.dashboard.view',
        ];

        foreach ($esperado as $nome => $middleware) {
            expect(govMiddlewareDaRota($nome))->toContain(
                $middleware,
                "rota `{$nome}` deveria exigir `{$middleware}` (ADR 0392 §D-D passo 2)"
            );
        }
    });

    it('as 3 permissions usadas são as que o DataController DECLARA (nenhuma inventada)', function () {
        // Criar permission nova exige ADR + migration própria — regra explícita
        // registrada no docblock do QualidadeIaController. Este teste trava a
        // reincidência: se alguém gatear uma rota com permission não declarada,
        // o checkbox não existe em /roles/{id}/edit e ninguém consegue conceder.
        $declaradas = collect((new \Modules\Governance\Http\Controllers\DataController())->user_permissions())
            ->pluck('value')
            ->all();

        $usadas = collect([
            'governance.policies.index', 'governance.policies.toggle', 'governance.audit.index',
            'governance.drift.index', 'governance.module-grades.index',
            'governance.module-grades.show', 'governance.ds-rollout.index',
        ])
            ->flatMap(fn (string $n) => govMiddlewareDaRota($n))
            ->filter(fn (string $m) => str_starts_with($m, 'can:governance.'))
            ->map(fn (string $m) => substr($m, strlen('can:')))
            ->unique()
            ->values()
            ->all();

        expect($usadas)->not->toBeEmpty('nenhuma rota gateada — o teste estaria vazio');

        foreach ($usadas as $permission) {
            expect($declaradas)->toContain(
                $permission,
                "`{$permission}` não é declarada em DataController::user_permissions() — "
                . 'sem checkbox em /roles/{id}/edit, ninguém consegue conceder'
            );
        }
    });

    it('CONTROLE NEGATIVO: a redirect de entrada segue sem gate (senão o teste acima passaria por acidente)', function () {
        // `/governance` é um redirect 302 pra /ia (Wagner 2026-05-22) e NÃO deve
        // exigir permission — se alguém gatear, quem não tem a permission perde
        // o redirect em vez de ser levado ao hub. Este caso existe para provar
        // que o teste de cima distingue rota gateada de não-gateada.
        $middleware = govMiddlewareDaRota('governance.admin.dashboard');

        expect(collect($middleware)->filter(fn ($m) => str_starts_with($m, 'can:'))->all())
            ->toBeEmpty('a redirect de entrada não deve exigir permission');
    });
});
